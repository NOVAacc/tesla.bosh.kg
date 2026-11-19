import http from 'node:http';
import fs from 'node:fs/promises';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {loadFromApi,loadCcs2Ids,assignStationTypes,normalizeAll,groupSites} from './spark.mjs';
import {loadCharge24,normalizeCharge24} from './charge24.mjs';
import {loadWeWay,normalizeWeWay} from './weway.mjs';
import {loadEvion,loadEvionActive,normalizeEvion,attachEvionPercentages} from './evion.mjs';
const root=path.dirname(fileURLToPath(import.meta.url));
process.chdir(root);
const PORT=Number(process.env.PORT||3080);
const CACHE_MS=5*60*1000;
const cache={spark:{value:null,at:0,error:null,source:'snapshot'},charge24:{value:null,at:0,error:null,source:'none'},weway:{value:null,at:0,error:null,source:'none'},evion:{value:null,at:0,error:null,source:'none'}};
let requests={};
async function cached(name, loader, fallback) {
 const c=cache[name];
 if(c.value && Date.now()-c.at<CACHE_MS)return c;
 if(requests[name])return requests[name];
 requests[name]=(async()=>{
  try {const value=await loader();c.value=value;c.at=Date.now();c.error=null;c.source='live';}
  catch(e){c.error='Данные временно недоступны';if(!c.value && fallback){try{c.value=await fallback();c.at=0;c.source='snapshot'}catch{}}}
  return c;
 })(); try{return await requests[name]}finally{delete requests[name]}
}
async function loadSpark(){return await cached('spark',async()=>{let raw=await loadFromApi();let ccs2Warning=null;try{const ids=await loadCcs2Ids();raw=assignStationTypes(raw,ids)}catch(e){ccs2Warning='Данные временно недоступны'}cache.spark.connectorTypesError=ccs2Warning;await fs.writeFile(path.join(root,'data/spark-snapshot.json'),JSON.stringify(raw));return normalizeAll(raw)},async()=>normalizeAll(JSON.parse(await fs.readFile(path.join(root,'data/spark-snapshot.json'),'utf8'))));}
async function loadWeWayStations(){return await cached('weway',async()=>normalizeWeWay(await loadWeWay()));}
async function loadEvionStations(){return await cached('evion',async()=>{const rows=await loadEvion();const normalized=normalizeEvion(rows);let sessions=[];try{sessions=await loadEvionActive()}catch{}return attachEvionPercentages(normalized,sessions)});}
async function loadCharge(){return await cached('charge24',async()=>{const raw=await loadCharge24();const normalized=normalizeCharge24(raw);if(!normalized.length)throw new Error('Charge24: не получены координаты');await fs.writeFile(path.join(root,'data/charge24-snapshot.json'),JSON.stringify(raw));return normalized},async()=>normalizeCharge24(JSON.parse(await fs.readFile(path.join(root,'data/charge24-snapshot.json'),'utf8'))));}
const send=(res,code,obj)=>{res.writeHead(code,{'content-type':'application/json; charset=utf-8','cache-control':'no-store'});res.end(JSON.stringify(obj))};
http.createServer(async(req,res)=>{
 try{
  const url=new URL(req.url,'http://localhost');
  if(url.pathname==='/api/stations'){
    const [s,c,w,e]=await Promise.all([loadSpark(),loadCharge(),loadWeWayStations(),loadEvionStations()]);
    return send(res,200,{stations:[...(url.searchParams.get('view')==='map'?[]:(s.value||[])),...(c.value||[]),...(w.value||[]),...(e.value||[])],sites:groupSites(s.value||[]),meta:{spark:{count:s.value?.length||0,source:s.source,error:s.error,connectorTypesError:s.connectorTypesError||null,updatedAt:s.at?new Date(s.at).toISOString():null},charge24:{count:c.value?.length||0,source:c.source,error:c.error,updatedAt:c.at?new Date(c.at).toISOString():null},weway:{count:w.value?.length||0,source:w.source,error:w.error,updatedAt:w.at?new Date(w.at).toISOString():null},evion:{count:e.value?.length||0,source:e.source,error:e.error,updatedAt:e.at?new Date(e.at).toISOString():null}}});
  }
  if(url.pathname==='/api/health') return send(res,200,Object.fromEntries(
    Object.entries(cache).map(([name,{at,error,source}])=>[name,{at,error,source}])
  ));
  const p=url.pathname==='/'?'index.html':url.pathname.slice(1);
  if(!['index.html','map-config.js'].includes(p)) return send(res,404,{error:'not found'});
  const content=await fs.readFile(path.join(root,'public',p));
  const type=p.endsWith('.html')?'text/html; charset=utf-8':p.endsWith('.js')?'application/javascript':'text/plain';
  res.writeHead(200,{'content-type':type});res.end(content);
 }catch(e){ if(e.code==='ENOENT')send(res,404,{error:'not found'});else send(res,500,{error:'internal server error'})}
}).listen(PORT,()=>console.log('Общая карта: http://localhost:'+PORT));
