import test from 'node:test';import assert from 'node:assert/strict';import fs from 'node:fs';import {normalizeAll,groupSites,loadCcs2Ids,assignStationTypes} from './spark.mjs';import {normalizeCharge24,loadCharge24} from './charge24.mjs';
import {loadWeWay,normalizeWeWay} from './weway.mjs';
import {loadEvion,loadEvionActive,normalizeEvion,attachEvionPercentages} from './evion.mjs';
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
 assert.match(html,/setInterval\(update,60000\)/);
});

test('SPARK card includes prices and keeps credential status private',()=>{
 const php=fs.readFileSync('public/api/stations.php','utf8');
 const html=fs.readFileSync('public/index.html','utf8');
 assert.match(php,/tariff \{ type amount currency \}/);
 assert.match(php,/function sparkCardDetails\(string \$stationId\)/);
 assert.doesNotMatch(php,/credential_not_configured/);
 assert.match(html,/data\.connectors\?/);
 assert.match(html,/tariffText\(c\.tariff\)/);
});

function renderMobileNav(page){
 const source=fs.readFileSync('public/mobile-nav.js','utf8');
 const nav={setAttribute(){}};
 const document={
  currentScript:{dataset:{page},replaceWith(element){assert.equal(element,nav)}},
  createElement(tag){assert.equal(tag,'nav');return nav},
 };
 new Function('document',source)(document);
 return nav.innerHTML;
}

test('Mobile navigation: persistent tabs and bottom sheets are wired',()=>{
 const html=fs.readFileSync('public/index.html','utf8');
 assert.match(html,/<script src="\/mobile-nav\.js" data-page="map"><\/script>/);
 const dock=renderMobileNav('map');
 for(const tab of ['map','list','filters'])assert.match(dock,new RegExp('data-mobile-tab="'+tab+'"'));
 assert.doesNotMatch(dock,/data-mobile-tab="search"/);
 for(const id of ['searchSheet','filterSheet','mobileShade','mobileRefresh','mobileReset','applyFilters'])assert.ok(html.includes('id="'+id+'"'));
 assert.match(html,/function syncMobileLayout\(\)/);
 assert.match(html,/function openSheet\(name\)/);
 assert.match(html,/map\.invalidateSize\(\)/);
 assert.match(html,/visibleMarkers\.set\(s\.id,markerRef\)/);
});

test('Subtle Grayscale basemap: configured CARTO key with OSM fallback',()=>{
 const html=fs.readFileSync('public/index.html','utf8');
 const workflow=fs.readFileSync('.github/workflows/deploy.yml','utf8');
 assert.match(html,/rastertiles\/light_all/);
 assert.match(html,/map-config\.js/);
 assert.match(html,/tileerror/);
 assert.match(html,/positronTiles/);
 assert.match(html,/\.leaflet-tile-pane\{filter:grayscale\(1\)/);
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
 assert.match(html,/if\(mobileMQ\.matches\)\{setListMode\(false\);openStationPanel\(station\)\}/);
 assert.match(html,/startSparkDetails\(site,/);
});

test('Mobile station panel closes on map tab, background tap and viewport switch',()=>{
 const html=fs.readFileSync('public/index.html','utf8');
 assert.match(html,/if\(name==='map'\)\{closeSheet\(\);setListMode\(false\);closeStationPanel\(\);return\}/);
 assert.match(html,/map\.on\('click',\(\)=>\{if\(mobileMQ\.matches&&selectedStation\)closeStationPanel\(\)\}\)/);
 assert.match(html,/function syncMobileLayout\(\)\{\s*closeStationPanel\(\)/);
});

test('Nearest list: tabs, geolocation, distance and filtering are wired',()=>{
 const html=fs.readFileSync('public/index.html','utf8');
 for(const id of ['stationListView','nearbyLocation','nearbyContent','desktopList'])assert.ok(html.includes('id="'+id+'"'));
 assert.match(renderMobileNav('map'),/data-mobile-tab="list"/);
 assert.match(html,/function distanceKm\(a,b\)/);
 assert.match(html,/function nearbySortedStations\(\)/);
 assert.match(html,/function setListMode\(enabled\)/);
 assert.match(html,/navigator\.geolocation\.getCurrentPosition/);
 assert.match(html,/stations\.filter\(s=>validCoordinates\(s\)&&matches\(s\)\)/);
 assert.match(html,/distanceKm\(userCoords,a\)-distanceKm\(userCoords,b\)/);
 assert.match(html,/if\(name==='list'\)\{closeSheet\(\);closeStationPanel\(\);setListMode\(true\);return\}/);
});

test('WeWay: public catalog requires no credentials and all rows',async()=>{
 let opts,url;const data={page:1,size:100,pages:1,total:1,items:[{id:30,identity:'Ww330',name:'Киркомстром',latitude:'42.868758',longitude:'74.509578',state:1,online:true,promotions:[],connectors:[{connector_type:'GB/T (DC)',items:[{ocpp_16_id:1,status:'Available',power:80},{ocpp_16_id:2,status:'Charging',power:80}]}]}]};
 const rows=await loadWeWay({fetcher:async(u,o)=>{url=u;opts=o;return {ok:true,json:async()=>data}}});
 assert.match(url,/size=100/);assert.deepEqual(opts.headers,{accept:'application/json'});
 const [station]=normalizeWeWay(rows);
 assert.equal(station.id,'weway:Ww330');
 assert.equal(station.availableConnectors,1);
 assert.equal(station.connectors[0].type,'GB/T (DC)');
 assert.equal(station.connectors[1].status,'CHARGING');
 assert.equal(station.connectors[1].percentage,null);
 assert.equal(station.powerKw,80);
});
test('WeWay: offline and restricted stations are not presented as free',()=>{
 const [station]=normalizeWeWay([{id:1,identity:'ww1',name:'Private',latitude:'42.8',longitude:'74.6',online:false,state:1,promotions:['Станция доступна только для сотрудников'],connectors:[{connector_type:'Tesla NACS',items:[{ocpp_16_id:1,status:'Available',power:120}]}]}]);
 assert.equal(station.active,false);assert.equal(station.restricted,true);
 assert.equal(station.connectors[0].available,false);
 assert.equal(station.availableConnectors,0);
});
test('WeWay: incomplete catalog must fail instead of appearing complete',async()=>{
 await assert.rejects(loadWeWay({fetcher:async()=>({ok:true,json:async()=>({pages:2,total:41,items:[{}]})})}),/неполный каталог/);
});
test('WeWay: production PHP and frontend include operator',()=>{
 const php=fs.readFileSync('public/api/stations.php','utf8');
 const html=fs.readFileSync('public/index.html','utf8');
 assert.match(php,/function wewayStations\(\)/);
 assert.match(php,/size=100/);
 assert.match(php,/'weway' => \$weway/);
 assert.match(html,/id="weway"/);
 assert.match(html,/filter\.weway/);
 assert.match(html,/x\.operator==='Charge24'\|\|x\.operator==='WeWay'/);
});

test('EVION: public location list, live availability, tariffs and emergency flag',async()=>{
 let url,headers;
 const rows=await loadEvion({fetcher:async(u,o)=>{url=u;headers=o.headers;return {ok:true,json:async()=>[{_id:'loc1',name:'EVION Test',address:'Бишкек',coordinates:{latitude:42.86,longitude:74.61},evses:[{_id:'evse1',emergency:false,connectors:[{_id:'a',standard:'GBT_DC',status:'AVAILABLE',ocppStatus:'Available',power:80,tariffs:[{text:'14 сом/кВт⋅ч'}]},{_id:'b',standard:'GBT_DC',status:'CHARGING',ocppStatus:'Charging',power:80}]}]}]}}});
 assert.match(url,/electrifly.ru\/api\/location\/list/);
 assert.match(headers.protocol,/kg.evion.app/);
 const [station]=normalizeEvion(rows);
 assert.equal(station.operator,'EVION');assert.equal(station.availableConnectors,1);
 assert.equal(station.connectors[0].tariff,'14 сом/кВт⋅ч');
 assert.equal(station.connectors[0].type,'GBT_DC');
 assert.equal(station.connectors[1].percentage,null);
 const emergency=normalizeEvion([{_id:'loc2',coordinates:{latitude:42.8,longitude:74.6},evses:[{emergency:true,connectors:[{status:'AVAILABLE',ocppStatus:'Available'}]}]}]);
 assert.equal(emergency[0].connectors[0].available,false);
 assert.equal(emergency[0].connectors[0].status,'UNAVAILABLE');
});
test('EVION: production PHP and client include network and filters',()=>{
 const php=fs.readFileSync('public/api/stations.php','utf8');
 const html=fs.readFileSync('public/index.html','utf8');
 assert.match(php,/function evionStations\(\)/);assert.match(php,/electrifly.ru\/api\/location\/list/);
 assert.match(php,/'evion' => \$evion/);
 assert.match(html,/id="evion"/);assert.match(html,/filter\.evion/);
 assert.match(html,/x\.operator==='EVION'/);
});

test('EVION active session matches exact connector and correct location, no customer information',async()=>{
 let url;
 const sessions=await loadEvionActive({fetcher:async(u)=>{url=u;return {ok:true,json:async()=>[
  {completed:false,startTime:100,soc:63,location:{_id:'loc1'},connector:{_id:'portA'},owner:{name:'private phone'}},
  {completed:false,startTime:200,soc:79,location:{_id:'loc1'},connector:{_id:'portA'}},
  {completed:false,startTime:300,soc:99,location:{_id:'loc2'},connector:{_id:'portA'}},
  {completed:true,startTime:400,soc:100,location:{_id:'loc1'},connector:{_id:'portA'}},
  {completed:false,startTime:350,soc:150,location:{_id:'loc1'},connector:{_id:'portA'}}]}}});
 assert.match(url,/transaction\/active\/list/);
 const rows=[{_id:'loc1',coordinates:{latitude:42.8,longitude:74.6},evses:[{connectors:[{_id:'portA',status:'CHARGING',ocppStatus:'Charging'},{_id:'portB',status:'AVAILABLE',ocppStatus:'Available'}]}]},
  {_id:'loc2',coordinates:{latitude:42.8,longitude:74.7},evses:[{connectors:[{_id:'portA',status:'AVAILABLE',ocppStatus:'Available'}]}]}];
 const result=attachEvionPercentages(normalizeEvion(rows),sessions);
 assert.equal(result[0].connectors[0].percentage,79);
 assert.equal(result[0].connectors[1].percentage,null);
 assert.equal(result[1].connectors[0].percentage,null);
 assert.doesNotMatch(JSON.stringify(result),/private phone|owner|account/);
});
test('EVION production backend matches charging sessions without exposing personal data',()=>{
 const php=fs.readFileSync('public/api/stations.php','utf8');
 assert.match(php,/function evionActivePercentages\(\)/);
 assert.match(php,/electrifly.ru\/api\/transaction\/active\/list/);
 assert.match(php,/\$activePercentages/);
 assert.doesNotMatch(php,/'owner' => |'account' => /);
});
