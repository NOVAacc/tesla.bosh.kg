import fs from 'node:fs/promises';
import path from 'node:path';

export const ENDPOINT = 'https://api.itcharge.ru/graphql/v1';
export const QUERY = `query ChargingStationsList($page: Page) {
 ChargingStationsList(page: $page) {
  count
  rows { id name active address blacklisted forceBlocked coordinates { latitude longitude }
   evses { id connectors { id type status pending chargingFinished } } }
 }
}`;
// Filter verified from the native SPARK KG application. It matches devices
// equipped with CCS2, but does NOT identify which specific port is CCS2.
export const CCS2_QUERY = `query ChargingStationsList($page: Page, $connectors: ConnectorType) {
 ChargingStationsList(page: $page, connectors: $connectors) {
  count rows { id }
 }
}`;
export async function loadCcs2Ids({fetcher=fetch,timeoutMs=15000}={}) {
 const ids = new Set();let total=null;
 for(let page=1;page<=100;page++) {
  const controller=new AbortController();const timer=setTimeout(()=>controller.abort(),timeoutMs);
  let response;
  try { response=await fetcher(ENDPOINT,{method:'POST',headers:{'content-type':'application/json',tenant:'spark','x-application-version':'2.3.3-ios'},body:JSON.stringify({operationName:'ChargingStationsList',query:CCS2_QUERY,variables:{page:{size:100,number:page},connectors:{type:{in:['C_CCS2']}}}}),signal:controller.signal}); }
  finally {clearTimeout(timer)}
  if(!response.ok)throw new Error(`SPARK CCS2 HTTP ${response.status}`);
  const payload=await response.json();const result=payload.data?.ChargingStationsList;
  if(payload.errors?.length||!result||!Array.isArray(result.rows))throw new Error(`SPARK CCS2 GraphQL: ${JSON.stringify(payload.errors||payload).slice(0,300)}`);
  if(total===null)total=result.count;
  if(result.count!==total)throw new Error('SPARK CCS2 count changed');
  for(const row of result.rows)ids.add(row.id);
  if(ids.size>=total) return ids;
  if(!result.rows.length) throw new Error('SPARK CCS2 pagination ended early');
 }
 throw new Error('SPARK CCS2 pagination exceeded maximum');
}
export function assignStationTypes(rows, ccs2Ids) {
 return {...rows,rows:rows.rows.map(station=>({...station,supportedConnectorTypes:ccs2Ids.has(station.id)?['CCS2']:[]}))};
}
export const SNAPSHOT = path.resolve('data/spark-snapshot.json');
const PAGE_SIZE = 100;

export async function loadFromApi({fetcher = fetch, timeoutMs = 15000} = {}) {
  const results = [];
  let total = null;
  let page = 1;
  do {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), timeoutMs);
    let response;
    try {
      response = await fetcher(ENDPOINT, {
        method: 'POST',
        headers: {'content-type': 'application/json', tenant: 'spark', 'x-application-version': '2.3.3-ios'},
        body: JSON.stringify({operationName: 'ChargingStationsList', query: QUERY,
          variables: {page: {size: PAGE_SIZE, number: page}}}),
        signal: controller.signal
      });
    } finally { clearTimeout(timeout); }
    if (!response.ok) throw new Error(`SPARK HTTP ${response.status} at page ${page}`);
    const payload = await response.json();
    if (payload.errors?.length || !payload.data?.ChargingStationsList)
      throw new Error(`SPARK GraphQL error at page ${page}: ${JSON.stringify(payload.errors ?? payload)}`);
    const {count, rows} = payload.data.ChargingStationsList;
    if (!Number.isSafeInteger(count) || !Array.isArray(rows)) throw new Error('Invalid SPARK list response');
    if (total !== null && count !== total) throw new Error('SPARK count changed during pagination; retry later');
    total = count;
    results.push(...rows);
    if (results.length > total || page > 100) throw new Error('Invalid SPARK pagination');
    if (rows.length === 0 && results.length < total) throw new Error('Premature empty SPARK page');
    page++;
  } while (results.length < total);
  const ids = new Set(results.map(r => r.id));
  if (ids.size !== results.length) throw new Error('Duplicate SPARK IDs; inconsistent pagination');
  return {count: total, rows: results};
}

export async function loadSnapshot(file = SNAPSHOT) {
  const data = JSON.parse(await fs.readFile(file, 'utf8'));
  if (!Array.isArray(data.rows)) throw new Error('Bad SPARK snapshot');
  return data;
}
export async function writeSnapshot(data, file = SNAPSHOT) {
  const temporary = `${file}.tmp`;
  await fs.mkdir(path.dirname(file), {recursive: true});
  await fs.writeFile(temporary, JSON.stringify(data, null, 2), 'utf8');
  await fs.rename(temporary, file);
}

export function normalizeStation(station) {
  const lat = station.coordinates?.latitude, lng = station.coordinates?.longitude;
  if (!Number.isFinite(lat) || !Number.isFinite(lng) || Math.abs(lat)>90 || Math.abs(lng)>180) return null;
  const connectors = (station.evses || []).flatMap(ev => (ev.connectors || []).map(c => ({
    id: `${station.id}:${ev.id}:${c.id}`,
    evseId: ev.id, connectorId: c.id,
    type: c.type ?? null,
    status: c.status || 'UNKNOWN', pending: Boolean(c.pending), chargingFinished: Boolean(c.chargingFinished),
    available: Boolean(station.active) && !station.forceBlocked && !station.blacklisted && c.status === 'AVAILABLE' && !c.pending
  })));
  return {
    id: `spark:${station.id}`, sourceId: station.id, operator: 'SPARK KG',
    name: station.name, address: station.address || '', lat, lng,
    active: Boolean(station.active), blocked: Boolean(station.forceBlocked || station.blacklisted),
    supportedConnectorTypes: station.supportedConnectorTypes || [],
    powerKw: null, // station names may contain DC 120 but that's not authoritative
    connectors, availableConnectors: connectors.filter(c=>c.available).length,
    totalConnectors: connectors.length
  };
}
export function normalizeAll(raw) { return raw.rows.map(normalizeStation).filter(Boolean); }

// One map point per geographic site. Exact normalized address clusters devices at same site;
// fallback to station ID if address is missing. No distance-based merges across roads.
export function groupSites(stations) {
  const groups = new Map();
  for (const s of stations) {
    const addressKey = s.address.trim().toLowerCase().replace(/\s+/g,' ');
    const key = addressKey ? `spark:site:${addressKey}` : s.id;
    if (!groups.has(key)) groups.set(key, {id:key, operator:'SPARK KG',name:s.name.replace(/\s+№\d+\b.*$/, ''),
      address:s.address, lat:s.lat, lng:s.lng, devices:[], supportedConnectorTypes:[], availableConnectors:0, totalConnectors:0});
    const g=groups.get(key);
    g.devices.push(s);
    for(const t of s.supportedConnectorTypes || [])if(!g.supportedConnectorTypes.includes(t))g.supportedConnectorTypes.push(t);
    g.availableConnectors += s.availableConnectors;
    g.totalConnectors += s.totalConnectors;
  }
  return [...groups.values()];
}
