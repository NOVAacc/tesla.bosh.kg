import test from 'node:test';import assert from 'node:assert/strict';import fs from 'node:fs';import {normalizeAll,groupSites,loadCcs2Ids,assignStationTypes} from './spark.mjs';import {normalizeCharge24,loadCharge24} from './charge24.mjs';
const data=JSON.parse(fs.readFileSync('data/spark-snapshot.json','utf8'));
test('SPARK: все 388 устройства',()=>assert.equal(normalizeAll(data).length,388));
test('SPARK: площадки',()=>assert.ok(groupSites(normalizeAll(data)).length>100));
test('Charge24: координаты, статус и процент',()=>{const n=normalizeCharge24([{id:'1',name:'Test',lat:42.8,lng:74.6,connectors:[{connectorId:1,status:'Charging',data:{name:'GBT',percentage:55}}]}]);assert.equal(n.length,1);assert.equal(n[0].connectors[0].percentage,55)});

test('Charge24: первый запрос передаёт обязательный непустой checkpoint',async()=>{let input;const fetcher=async(_u,opts)=>{input=JSON.parse(opts.body);return {ok:true,json:async()=>({data:{pullPoint:{documents:[],checkpoint:null}}})}};await loadCharge24({fetcher});assert.equal(input.variables.checkpoint.id,'0');assert.equal(input.variables.checkpoint.updatedAt,0);assert.equal(typeof input.variables.checkpoint.updatedAt,'number');assert.match(input.query,/PointInputCheckpoint!/)});


test('Charge24: курсор следующей страницы сохраняет числовой updatedAt',async()=>{
 const requests=[];
 const fetcher=async(_u,opts)=>{
  const b=JSON.parse(opts.body);requests.push(b);
  const data=requests.length===1
   ? {documents:Array(25).fill({id:'x'}),checkpoint:{id:'abc',updatedAt:1730000000}}
   : {documents:[],checkpoint:null};
  return {ok:true,json:async()=>({data:{pullPoint:data}})};
 };
 await loadCharge24({fetcher});
 assert.equal(requests[1].variables.checkpoint.updatedAt,1730000000);
 assert.equal(requests[1].variables.checkpoint.id,'abc');
});

test('SPARK: распознаём CCS2 на уровне станции через подтверждённый GraphQL-фильтр',async()=>{
 const calls=[];const fetcher=async(_url,opts)=>{const body=JSON.parse(opts.body);calls.push(body);return {ok:true,json:async()=>({data:{ChargingStationsList:{count:1,rows:[{id:'spark-test'}]}}})}};
 const ids=await loadCcs2Ids({fetcher});assert.deepEqual([...ids],['spark-test']);assert.deepEqual(calls[0].variables.connectors,{type:{in:['C_CCS2']}});
 const rows=assignStationTypes({count:2,rows:[{id:'spark-test'},{id:'spark-other'}]},ids).rows;
 assert.deepEqual(rows[0].supportedConnectorTypes,['CCS2']);assert.deepEqual(rows[1].supportedConnectorTypes,[]);
});

test('SPARK: тип разъёма привязан к конкретному порту',()=>{
 const raw={rows:[{id:'mixed',name:'Mixed',active:true,coordinates:{latitude:42.8,longitude:74.6},evses:[{id:1,connectors:[{id:1,type:'GBT_DC',activeTransactionBrief:{percentage:62}},{id:2,type:'C_CCS2',activeTransactionBrief:{percentage:0}},{id:3}]}]}]};
 const [station]=normalizeAll(raw);
 assert.deepEqual(station.connectors.map(c=>c.type),['GBT_DC','C_CCS2',null]);
 assert.deepEqual(station.connectors.map(c=>c.percentage),[62,0,null]);
});

test('SPARK card: API uses station UUID instead of numeric seqNo',()=>{
 const php=fs.readFileSync('public/api/stations.php','utf8');
 assert.match(php,/query ChargingStationCard\(\$id: String\)/);
 assert.match(php,/ChargingStationCard\(id: \$id\)/);
 assert.match(php,/\['id' => \$stationId\]/);
 assert.doesNotMatch(php,/ChargingStationCard\(seqNo:/);
});
test('SPARK popup requests UUID and refreshes active charging data',()=>{
 const html=fs.readFileSync('public/index.html','utf8');
 assert.match(html,/sparkCard='\+encodeURIComponent\(device.sourceId\)/);
 assert.match(html,/setInterval\(refresh,60000\)/);
});

test('SPARK card includes prices and explains missing credentials',()=>{
 const php=fs.readFileSync('public/api/stations.php','utf8');
 const html=fs.readFileSync('public/index.html','utf8');
 assert.match(php,/tariff \{ type amount currency \}/);
 assert.match(php,/function sparkCardDetails\(string \$stationId\)/);
 assert.match(php,/credential_not_configured/);
 assert.match(html,/data\.connectors\?/);
 assert.match(html,/tariffText\(c\.tariff\)/);
});

test('Mobile navigation: persistent tabs and bottom sheets are wired',()=>{
 const html=fs.readFileSync('public/index.html','utf8');
 for(const tab of ['map','search','filters'])assert.match(html,new RegExp('data-mobile-tab="'+tab+'"'));
 for(const id of ['searchSheet','filterSheet','mobileShade','mobileRefresh','mobileReset','applyFilters'])assert.ok(html.includes('id="'+id+'"'));
 assert.match(html,/function syncMobileLayout\(\)/);
 assert.match(html,/function openSheet\(name\)/);
 assert.match(html,/map\.invalidateSize\(\)/);
 assert.match(html,/visibleMarkers\.set\(s\.id,markerRef\)/);
});

test('Voyager tiles: configured CARTO key with OSM fallback',()=>{
 const html=fs.readFileSync('public/index.html','utf8');
 const workflow=fs.readFileSync('.github/workflows/deploy.yml','utf8');
 assert.match(html,/rastertiles\/voyager/);
 assert.match(html,/map-config\.js/);
 assert.match(html,/tileerror/);
 assert.match(html,/osmTiles\.addTo\(map\)/);
 assert.match(workflow,/CARTO_BASEMAP_KEY/);
});

test('Mobile stations use bottom details panel and visual charging indicators',()=>{
 const html=fs.readFileSync('public/index.html','utf8');
 for(const id of ['stationPanel','stationPanelBody','closeStation','stationName','stationOperator','stationAddress'])assert.ok(html.includes('id="'+id+'"'));
 assert.match(html,/function openStationPanel\(site\)/);
 assert.match(html,/function closeStationPanel\(\)/);
 assert.match(html,/class="station-percent"/);
 assert.match(html,/class="station-battery-fill"/);
 assert.match(html,/class="station-charge-progress"/);
 assert.match(html,/if\(mobileMQ\.matches\)\{markerRef\.on\('click',\(\)=>openStationPanel\(s\)\)\}/);
 assert.match(html,/if\(mobileMQ\.matches\)\{openStationPanel\(station\)\}/);
 assert.match(html,/startSparkDetails\(site,/);
});

test('Mobile station panel closes on map tab, background tap and viewport switch',()=>{
 const html=fs.readFileSync('public/index.html','utf8');
 assert.match(html,/if\(name==='map'\)\{closeSheet\(\);closeStationPanel\(\);return\}/);
 assert.match(html,/map\.on\('click',\(\)=>\{if\(mobileMQ\.matches&&selectedStation\)closeStationPanel\(\)\}\)/);
 assert.match(html,/function syncMobileLayout\(\)\{\s*closeStationPanel\(\)/);
});
