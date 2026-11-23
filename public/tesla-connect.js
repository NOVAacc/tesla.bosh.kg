'use strict';
(() => {
  const el=id=>document.getElementById(id);
  const box=el('teslaConnectCard'),status=el('teslaConnectStatus'),link=el('teslaConnectLink');
  if(!box||!status||!link)return;
  const feedback=el('teslaFeedback'),details=el('teslaVehicleDetails');
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
    const art=el('teslaModelArt');
    const displayModel=chosen||'3';
    art.src='/tesla-model'+displayModel.toLowerCase()+'-threequarter.svg';
    art.alt='Tesla Model '+displayModel+' — векторная иллюстрация три четверти';
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
    link.classList.remove('hidden');
    link.textContent='Переподключить';
  }
  async function load(){
    try{
      const auth=await fetch('/api/account.php?action=me',{credentials:'same-origin',cache:'no-store'});
      if(!auth.ok)return;
      const user=await auth.json();
      if(!user.user)return;
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
        link.textContent='Переподключить';
        return;
      }
      draw(car);
      if(data.cache_source==='server_stale')feedback.textContent='Показана последняя сохранённая сводка.';
    }catch{
      status.textContent='Данные Tesla временно недоступны.';
    }
  }
  void load();
})();
