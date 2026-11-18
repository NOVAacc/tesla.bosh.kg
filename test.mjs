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
 const raw={rows:[{id:'mixed',name:'Mixed',active:true,coordinates:{latitude:42.8,longitude:74.6},evses:[{id:1,connectors:[{id:1,type:'GBT_DC'},{id:2,type:'C_CCS2'},{id:3}]}]}]};
 const [station]=normalizeAll(raw);
 assert.deepEqual(station.connectors.map(c=>c.type),['GBT_DC','C_CCS2',null]);
});
