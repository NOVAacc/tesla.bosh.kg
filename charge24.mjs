export const ENDPOINT = 'https://charge24.app/graphql';
export const QUERY = `query PullPoint($checkpoint: PointInputCheckpoint!, $limit: Int!) {
 pullPoint(checkpoint: $checkpoint, limit: $limit) {
   documents { id name status isOpen lat lng
     locationGroup { id name address data { latitude longitude } }
     data { latitude longitude address tariffPower tariffPrice }
     connectors { connectorId status data { name percentage } }
   }
   checkpoint { id updatedAt }
 }
}`;
export async function loadCharge24({fetcher=fetch,timeoutMs=14000}={}) {
  // API requires a non-null PointInputCheckpoint with both mandatory fields.
  // Epoch cursor requests records created after the beginning of the timeline.
  let checkpoint={id:'0',updatedAt:0}; const rows=[]; const seenCheckpoints=new Set();
  for (let i=0;i<200;i++) {
    const controller=new AbortController(); const t=setTimeout(()=>controller.abort(),timeoutMs);
    let r;
    try {
      r=await fetcher(ENDPOINT,{method:'POST',headers:{'content-type':'application/json','accept':'application/json'},body:JSON.stringify({operationName:'PullPoint',query:QUERY,variables:{checkpoint,limit:25}}),signal:controller.signal});
    } finally {clearTimeout(t)}
    if(!r.ok) throw new Error('Charge24 HTTP '+r.status);
    const v=await r.json(); if(v.errors?.length) throw new Error('Charge24 GraphQL: '+JSON.stringify(v.errors).slice(0,350));
    const p=v.data?.pullPoint; if(!p || !Array.isArray(p.documents)) throw new Error('Charge24: неверный ответ');
    rows.push(...p.documents);
    if(!p.checkpoint || p.documents.length===0 || p.documents.length<25) break;
    const k=JSON.stringify(p.checkpoint); if(seenCheckpoints.has(k)) throw new Error('Charge24: повторный checkpoint');
    seenCheckpoints.add(k); checkpoint={id:String(p.checkpoint.id),updatedAt:Number(p.checkpoint.updatedAt)};
    if(!Number.isFinite(checkpoint.updatedAt)) throw new Error('Charge24: checkpoint.updatedAt не число');
  }
  return rows;
}
export function normalizeCharge24(raw) {
 const rows=Array.isArray(raw)?raw:raw?.data?.pullPoint?.documents??raw?.documents??raw?.rows??raw?.stations??[];
 return rows.map(x=>{
   const lat=Number(x.lat??x.data?.latitude??x.locationGroup?.data?.latitude??x.coordinates?.latitude);
   const lng=Number(x.lng??x.data?.longitude??x.locationGroup?.data?.longitude??x.coordinates?.longitude);
   if (!Number.isFinite(lat)||!Number.isFinite(lng)||lat<39||lat>44||lng<69||lng>81) return null;
   const connectors=(x.connectors||[]).map(c=>({id:String(c.connectorId??c.id??''),type:c.data?.name??c.type??null,status:c.status??'UNKNOWN',percentage:c.data?.percentage??null,available:/^(available|free)$/i.test(c.status??'')}));
   return {id:'charge24:'+x.id,operator:'Charge24',name:x.name||x.data?.name||'Зарядная станция',address:x.data?.address??x.locationGroup?.address??x.address??'',lat,lng,active:x.isOpen!==false,blocked:false,powerKw:x.data?.tariffPower??null,price:x.data?.tariffPrice??null,connectors,totalConnectors:connectors.length,availableConnectors:connectors.filter(c=>c.available).length};
 }).filter(Boolean);
}
