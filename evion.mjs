export const ENDPOINT='https://electrifly.ru/api/location/list';
export async function loadEvion({fetcher=fetch,timeoutMs=15000}={}) {
 const controller=new AbortController();
 const timer=setTimeout(()=>controller.abort(),timeoutMs);
 try {
  const response=await fetcher(ENDPOINT,{headers:{accept:'application/json, text/plain, */*',protocol:'kg.evion.app|1.10.1|211|ios|27.3','user-agent':'kg.evion.app','accept-language':'ru'},signal:controller.signal});
  if(!response.ok)throw new Error('EVION HTTP '+response.status);
  const rows=await response.json();
  if(!Array.isArray(rows)||!rows.length)throw new Error('EVION: пустой или некорректный каталог');
  return rows;
 }finally{clearTimeout(timer)}
}
export const ACTIVE_ENDPOINT='https://electrifly.ru/api/transaction/active/list';
export async function loadEvionActive({fetcher=fetch,timeoutMs=12000}={}) {
 const controller=new AbortController();
 const timer=setTimeout(()=>controller.abort(),timeoutMs);
 try {
  const response=await fetcher(ACTIVE_ENDPOINT,{headers:{accept:'application/json, text/plain, */*',protocol:'kg.evion.app|1.10.1|211|ios|27.3','user-agent':'kg.evion.app','accept-language':'ru'},signal:controller.signal});
  if(!response.ok)throw new Error('EVION active HTTP '+response.status);
  const rows=await response.json();
  if(!Array.isArray(rows))throw new Error('EVION: некорректные активные сессии');
  return rows;
 }finally{clearTimeout(timer)}
}
export function evionPercentages(sessions) {
 const byConnector=new Map();
 for(const session of sessions) {
  if(session?.completed!==false)continue;
  const connectorId=session.connector?._id, locationId=session.location?._id;
  if(!connectorId||!locationId)continue;
  const v=session.soc??session.socEnd;
  if(v===null||v===undefined||v===''||!Number.isFinite(Number(v))||Number(v)<0||Number(v)>100)continue;
  const key=String(locationId)+':'+String(connectorId);
  const start=Number(session.startTime)||0;
  if(!byConnector.has(key)||start>byConnector.get(key).start)byConnector.set(key,{start,percent:Number(v)});
 }
 return byConnector;
}
export function attachEvionPercentages(stations,sessions) {
 const percent=evionPercentages(sessions);
 return stations.map(station=>({
  ...station,
  connectors:station.connectors.map(c=>{
   const charging=String(c.status).toUpperCase()==='CHARGING'||String(c.status).toUpperCase()==='OCCUPIED';
   const key=station.sourceId+':'+c.id;
   return {...c,percentage:charging&&percent.has(key)?percent.get(key).percent:null};
  })
 }));
}
export function normalizeEvion(rows) {
 return rows.map(s=>{
  const lat=Number(s.coordinates?.latitude),lng=Number(s.coordinates?.longitude);
  if(!Number.isFinite(lat)||!Number.isFinite(lng)||lat<39||lat>44||lng<69||lng>81)return null;
  const connectors=(s.evses||[]).flatMap(evse=>(evse.connectors||[]).map(c=>{
   const status=String(c.status||'UNKNOWN').toUpperCase(),ocppStatus=String(c.ocppStatus||'').toUpperCase();
   const emergency=Boolean(evse.emergency);
   return {id:String(c._id||''),connectorId:c._id||null,evseId:evse._id||null,type:c.standard||null,
    status:emergency?'UNAVAILABLE':ocppStatus==='FINISHING'?'FINISHING':ocppStatus==='PREPARING'?'PREPARING':status,
    ocppStatus:c.ocppStatus||null,percentage:null,
    powerKw:Number.isFinite(Number(c.power))?Number(c.power):null,
    tariff:(c.tariffs||[]).map(t=>t.text).filter(Boolean).join(', ')||null,
    available:status==='AVAILABLE'&&ocppStatus==='AVAILABLE'&&!emergency};
  }));
  if(!connectors.length)return null;
  return {id:'evion:'+String(s._id),sourceId:String(s._id),operator:'EVION',name:s.name||'EVION',
   address:s.address||'',lat,lng,active:true,blocked:false,
   connectors,powerKw:Math.max(0,...connectors.map(c=>c.powerKw||0))||null,
   availableConnectors:connectors.filter(c=>c.available).length,totalConnectors:connectors.length};
 }).filter(Boolean);
}
