'use strict';
(() => {
  const el=id=>document.getElementById(id);
  const box=el('teslaConnectCard'),status=el('teslaConnectStatus'),link=el('teslaConnectLink');
  if(!box||!status||!link)return;
  const feedback=el('teslaFeedback'),details=el('teslaVehicleDetails');
  const disconnect=el('teslaDisconnect');
  let csrf='';
  const result=new URLSearchParams(location.search).get('tesla_result');
  if(result){
    feedback.textContent=({
      connected:'Tesla-аккаунт подключён.',cancelled:'Подключение отменено.',
      expired:'Сессия подключения истекла.',invalid_code:'Неверный ответ Tesla.',
      token_failed:'Tesla отклонила авторизацию.'
    })[result]||'Не удалось подключить Tesla.';
    history.replaceState(null,'',location.pathname);
  }
  const number=v=>v!==null&&v!==undefined&&v!==''&&Number.isFinite(Number(v))?Number(v):null;
  const validModel=model=>['3','Y','S','X'].includes(String(model).toUpperCase())?String(model).toUpperCase():null;
  const stateLabel=state=>({online:'В сети',asleep:'Спит',offline:'Не в сети',waking:'Пробуждается'})[state]||'Статус неизвестен';
  function draw(car){
    if(!car)return;
    const model=validModel(car.model);
    const name=String(car.display_name||'Tesla').trim();
    const nameMatch=name.match(/(?:model\s*|tesla\s*)([3ysx])\b/i);
    const chosen=model||(nameMatch?validModel(nameMatch[1]):null);
    el('teslaVehicleName').textContent=chosen?'Tesla Model '+chosen:name;
    el('teslaVehicleState').textContent=stateLabel(car.state);
    el('teslaOnlineDot').classList.toggle('is-online',car.state==='online');
    el('teslaVin').textContent=car.vin_suffix?'· ••••'+String(car.vin_suffix).slice(-4):'';
    const battery=number(car.battery_level);
    const rangeMiles=number(car.battery_range);
    const inside=number(car.inside_temp),outside=number(car.outside_temp),miles=number(car.odometer);
    el('teslaBattery').textContent=battery===null?'—':Math.round(Math.min(100,Math.max(0,battery)))+'%';
    el('teslaGauge').style.setProperty('--tesla-progress',(battery===null?0:Math.min(100,Math.max(0,battery)))+'%');
    el('teslaRange').textContent=rangeMiles===null?'— км':'≈ '+Math.round(rangeMiles*1.609344)+' км';
    el('teslaInsideTemp').textContent=inside===null?'В салоне —':'В салоне '+Math.round(inside)+' °C';
    el('teslaOutsideTemp').textContent=outside===null?'На улице —':'На улице '+Math.round(outside)+' °C';
    el('teslaOdometer').textContent=miles===null?'Пробег —':'Пробег '+Math.round(miles*1.609344).toLocaleString('ru-RU')+' км';
    const t=number(car.data_timestamp);
    if(t!==null) {
      const date=new Date(t<1e12?t*1000:t);
      el('teslaFreshness').textContent=Number.isNaN(date.getTime())
        ?'Последние доступные данные Tesla'
        :'Данные Tesla на '+date.toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'});
    } else {
      el('teslaFreshness').textContent='Сохранённые данные Tesla · время обновления неизвестно';
    }
    status.classList.add('hidden');
    details.classList.remove('hidden');
    el('teslaPrivacyNotice').classList.add('hidden');
    link.classList.add('hidden');
    disconnect.classList.remove('hidden');
  }
  const connectorForm=el('teslaConnectorsForm');
  const connectorInputs=[...connectorForm.querySelectorAll('input[name="teslaConnectors"]')];
  const connectorMsg=el('teslaConnectorsMsg'),connectorSave=el('teslaConnectorsSave');
  async function loadConnectors(){
    try{
      const response=await fetch('/api/tesla.php?action=connectors',{credentials:'same-origin',cache:'no-store'});
      if(!response.ok)throw new Error('load');
      const data=await response.json();
      const chosen=new Set(data.connectors||[]);
      for(const input of connectorInputs)input.checked=chosen.has(input.value);
      connectorMsg.textContent='';
    }catch{
      connectorMsg.textContent='Не удалось загрузить разъёмы.';
    }
  }
  connectorForm.addEventListener('submit',async event=>{
    event.preventDefault();
    if(connectorSave.disabled||!csrf)return;
    connectorSave.disabled=true;
    connectorMsg.textContent='Сохраняем…';
    try{
      const response=await fetch('/api/tesla.php?action=connectors',{
        method:'POST',credentials:'same-origin',cache:'no-store',
        headers:{'Accept':'application/json','Content-Type':'application/json'},
        body:JSON.stringify({csrf,connectors:connectorInputs.filter(input=>input.checked).map(input=>input.value)})
      });
      if(!response.ok)throw new Error('save');
      connectorMsg.textContent='Сохранено. Разъёмы учтутся в автофильтре карты.';
    }catch{
      connectorMsg.textContent='Ошибка сохранения. Попробуйте ещё раз.';
    }finally{connectorSave.disabled=false;}
  });
  async function load(){
    try{
      const auth=await fetch('/api/account.php?action=me',{credentials:'same-origin',cache:'no-store'});
      if(!auth.ok)return;
      const user=await auth.json();
      if(!user.user)return;
      csrf=user.csrf||'';
      const response=await fetch('/api/tesla.php?action=status',{credentials:'same-origin',cache:'no-store'});
      const data=await response.json();
      if(!response.ok){
        status.textContent=data.error==='tesla_not_configured'?'Подключение Tesla не настроено.':'Не удалось прочитать данные Tesla.';
        return;
      }
      if(!data.connected){
        status.textContent='Подключите свой аккаунт Tesla.';
        link.classList.remove('hidden');
        return;
      }
      const car=Array.isArray(data.vehicles)?data.vehicles[0]:null;
      if(!car){
        status.textContent='Tesla подключена, но список автомобилей временно недоступен.';
        link.classList.remove('hidden');
        link.textContent='Подключить Tesla';
        return;
      }
      draw(car);
      await loadConnectors();
      if(data.cache_source==='server_stale')feedback.textContent='Показана последняя сохранённая сводка.';
    }catch{
      status.textContent='Данные Tesla временно недоступны.';
    }
  }
  disconnect.addEventListener('click',async()=>{
    if(!csrf || disconnect.disabled)return;
    const confirmed=window.confirm('Отвязать Tesla от Bosh.kg? Сохранённый токен и данные автомобиля на нашем сервере будут удалены. Для полного отзыва разрешения потребуется также удалить Bosh.kg в аккаунте Tesla.');
    if(!confirmed)return;
    disconnect.disabled=true;
    feedback.textContent='Удаляем подключение…';
    try{
      const response=await fetch('/api/tesla.php?action=disconnect',{
        method:'POST',credentials:'same-origin',cache:'no-store',
        headers:{'Accept':'application/json','Content-Type':'application/json'},
        body:JSON.stringify({csrf})
      });
      const data=await response.json();
      if(!response.ok || !data.disconnected)throw new Error('disconnect_failed');
      details.classList.add('hidden');
      connectorForm.reset();
      connectorMsg.textContent='';
      status.classList.remove('hidden');
      status.textContent='Tesla не подключена.';
      el('teslaVehicleName').textContent='Tesla';
      el('teslaVehicleState').textContent='Не подключена';
      el('teslaVin').textContent='';
      el('teslaOnlineDot').classList.remove('is-online');
      el('teslaPrivacyNotice').classList.remove('hidden');
      link.classList.remove('hidden');
      link.textContent='Подключить Tesla';
      disconnect.classList.add('hidden');
      el('teslaDisconnectHint').classList.remove('hidden');
      feedback.textContent='Автомобиль отвязан от сайта. Для полного отзыва доступа откройте настройки Tesla.';
    }catch{
      feedback.textContent='Не удалось отвязать Tesla. Повторите попытку.';
    }finally{
      disconnect.disabled=false;
    }
  });
  void load();
})();
