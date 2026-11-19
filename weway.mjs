export const ENDPOINT = 'https://users.weway.tech/api/v1/stations/?page=1&size=100';
export async function loadWeWay({fetcher=fetch,timeoutMs=12000}={}) {
 const controller=new AbortController();
 const timer=setTimeout(()=>controller.abort(),timeoutMs);
 try {
  const response=await fetcher(ENDPOINT,{headers:{accept:'application/json'},signal:controller.signal});
  if(!response.ok)throw new Error('WeWay HTTP '+response.status);
  const data=await response.json();
  if(!Array.isArray(data.items)||!Number.isInteger(data.total)||data.items.length!==data.total||data.pages!==1)
   throw new Error('WeWay: неполный каталог станций');
  return data.items;
 }finally{clearTimeout(timer)}
}
export function normalizeWeWay(raw) {
 const rows=Array.isArray(raw)?raw:raw?.items??[];
 return rows.map(s=>{
  const lat=Number(s.latitude),lng=Number(s.longitude);
  if(!Number.isFinite(lat)||!Number.isFinite(lng)||lat<39||lat>44||lng<69||lng>81)return null;
  const active=s.online===true&&s.state===1;
  const connectors=(s.connectors||[]).flatMap(group=>(group.items||[]).map(c=>{
   const status=String(c.status??'UNKNOWN').toUpperCase();
   return {id:String(c.ocpp_16_id),connectorId:c.ocpp_16_id,
    type:group.connector_type??null,status,percentage:null,
    powerKw:Number.isFinite(Number(c.power))?Number(c.power):null,
    position:c.position??null,available:active&&status==='AVAILABLE'};
  }));
  const restricted=(s.promotions||[]).some(p=>/только для сотрудников|ограничен.*доступ/i.test(p));
  return {id:'weway:'+String(s.identity),sourceId:String(s.identity),
   operator:'WeWay',name:s.name||'Зарядная станция',address:s.address||'',
   lat,lng,active,blocked:!active,restricted,promotions:s.promotions||[],
   connectors,powerKw:connectors.length?Math.max(...connectors.map(c=>c.powerKw||0))||null:null,
   availableConnectors:connectors.filter(c=>c.available).length,totalConnectors:connectors.length};
 }).filter(Boolean);
}
