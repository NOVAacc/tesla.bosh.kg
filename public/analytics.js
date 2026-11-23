/* Lightweight, opt-out respecting, first-party usage metrics. No IP, coordinates or search queries. */
(()=>{
  if(navigator.globalPrivacyControl===true || navigator.doNotTrack==='1')return;
  let visitor;
  try{
    visitor=localStorage.getItem('ev-analytics-visitor');
    if(!/^[a-f0-9]{32}$/.test(visitor||'')){
      visitor=Array.from(crypto.getRandomValues(new Uint8Array(16)),v=>v.toString(16).padStart(2,'0')).join('');
      localStorage.setItem('ev-analytics-visitor',visitor);
    }
  }catch{return}
  let queue=[],scheduled=false;
  function flush(){
    scheduled=false;
    if(!queue.length)return;
    const events=queue.splice(0,12);
    const payload=JSON.stringify({visitor,events});
    if(navigator.sendBeacon){
      const ok=navigator.sendBeacon('/api/analytics.php',new Blob([payload],{type:'application/json'}));
      if(ok)return;
    }
    fetch('/api/analytics.php',{method:'POST',headers:{'Content-Type':'application/json'},body:payload,keepalive:true,credentials:'omit'}).catch(()=>{});
  }
  function track(name,label=''){
    if(!/^(map_open|station_open|filter_apply|favorite_add|favorite_remove|list_open|faq_open|services_open|route_open)$/.test(name))return;
    queue.push({name,label:String(label).slice(0,80)});
    if(queue.length>=12)flush();
    else if(!scheduled){scheduled=true;setTimeout(flush,1800)}
  }
  window.evTrack=track;
  document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='hidden')flush()});
  window.addEventListener('pagehide',flush);
  document.addEventListener('click',event=>{
    const button=event.target.closest('button');
    if(!button)return;
    if(['spark','charge24','weway','evion','tazatok','reset','applyFilters'].includes(button.id))track('filter_apply',button.id);
    else if(button.closest('#connectorPickerOptions,#connectorCards'))track('filter_apply','connector');
  },true);
  document.addEventListener('change',event=>{
    const el=event.target;
    if(['favoritesOnly','availability','current','power','station','portstatus','extras'].includes(el.id))track('filter_apply',el.id);
  },true);
  track('map_open');
})();
