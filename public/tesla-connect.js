'use strict';
(() => {
  const get=id=>document.getElementById(id);
  const box=get('teslaConnectCard'),status=get('teslaConnectStatus'),link=get('teslaConnectLink');
  if(!box||!status||!link)return;
  const feedback=get('teslaFeedback'),details=get('teslaVehicleDetails'),rows=get('teslaInfoRows');
  const result=new URLSearchParams(location.search).get('tesla_result');
  const resultMessages={
    connected:'Tesla-аккаунт подключён.',
    cancelled:'Подключение отменено.',
    expired:'Сессия подключения истекла. Повторите попытку.',
    invalid_code:'Tesla вернула некорректный код.',
    token_failed:'Tesla отклонила авторизацию.'
  };
  if(result){
    feedback.textContent=resultMessages[result]||'Не удалось подключить Tesla.';
    history.replaceState(null,'',location.pathname);
  }
  const hasNumber=v=>v!==null&&v!==undefined&&v!==''&&Number.isFinite(Number(v));
  const clamp=(n,min,max)=>Math.min(max,Math.max(min,n));
  const stateLabel=state=>({
    online:'В сети',asleep:'Спит',offline:'Не в сети',waking:'Пробуждается'
  })[state]||'Статус неизвестен';
  const chargingLabel=state=>({
    Charging:'Заряжается',Complete:'Заряжена',Disconnected:'Не подключена',
    Stopped:'Зарядка остановлена',NoPower:'Нет питания',Starting:'Начало зарядки'
  })[state]||'Данные зарядки недоступны';
  function updateVehicle(car){
    if(!car)return;
    const name=String(car.display_name||'Tesla').trim();
    const carName=/model\s*3/i.test(name)||/tesla\s*3/i.test(name)?'Tesla Model 3':name;
    get('teslaVehicleName').textContent=carName;
    const state=car.state||'unknown';
    get('teslaVehicleState').textContent=stateLabel(state);
    get('teslaOnlineDot').classList.toggle('is-online',state==='online');
    get('teslaVin').textContent=car.vin_suffix?'· ••••'+String(car.vin_suffix).slice(-4):'';
    get('teslaStateRow').textContent=stateLabel(state);
    const level=hasNumber(car.battery_level)?clamp(Number(car.battery_level),0,100):null;
    const km=hasNumber(car.battery_range)?Math.round(Number(car.battery_range)*1.609344):null;
    const limit=hasNumber(car.charge_limit_soc)?clamp(Math.round(Number(car.charge_limit_soc)),0,100):null;
    const charging=chargingLabel(car.charging_state);
    get('teslaBattery').textContent=level===null?'—':Math.round(level)+'%';
    get('teslaGauge').style.setProperty('--tesla-progress',(level??0)+'%');
    get('teslaRange').textContent=km===null?'— км':'≈ '+km+' км';
    get('teslaRangeRow').textContent=km===null?'—':'≈ '+km+' км';
    get('teslaChargeLimit').textContent=limit===null?'Лимит —':'Лимит '+limit+'%';
    get('teslaLimitRow').textContent=limit===null?'—':limit+'%';
    get('teslaCharging').textContent=charging;
    get('teslaChargingRow').textContent=charging;
    if(hasNumber(car.charge_timestamp)){
      const ms=Number(car.charge_timestamp);
      const date=new Date(ms<1e12?ms*1000:ms);
      get('teslaFreshness').textContent=Number.isNaN(date.getTime())
        ?'Данные Tesla могут быть устаревшими'
        :'Данные Tesla: '+date.toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'});
    } else {
      get('teslaFreshness').textContent=level===null?'Показатели батареи пока недоступны':'Последние данные Tesla';
    }
    status.classList.add('hidden');
    details.classList.remove('hidden');
    rows.classList.remove('hidden');
    link.classList.remove('hidden');
    link.textContent='Переподключить';
    return level!==null;
  }
  async function fetchLive(csrf,car){
    try{
      const response=await fetch('/api/tesla.php?action=live',{
        method:'POST',credentials:'same-origin',cache:'no-store',
        headers:{'Accept':'application/json','Content-Type':'application/json'},
        body:JSON.stringify({csrf})
      });
      if(!response.ok)return;
      const data=await response.json();
      if(!data.ok)return;
      updateVehicle({...car,...data,charge_timestamp:Date.now()});
      get('teslaFreshness').textContent='Обновлено только что';
      feedback.textContent='';
    }catch{
      // Existing cached data stays visible. No repeated automatic wake requests.
    }
  }
  async function load(){
    try{
      const response=await fetch('/api/account.php?action=me',{credentials:'same-origin',cache:'no-store'});
      if(!response.ok)return;
      const account=await response.json();
      if(!account.user)return;
      const r=await fetch('/api/tesla.php?action=status',{credentials:'same-origin',cache:'no-store'});
      const data=await r.json();
      if(!r.ok){
        status.textContent=data.error==='tesla_not_configured'
          ?'Подключение Tesla пока не настроено.'
          :'Tesla API временно недоступен.';
        return;
      }
      if(!data.connected){
        status.textContent='Подключите аккаунт Tesla, чтобы видеть данные автомобиля.';
        link.classList.remove('hidden');
        return;
      }
      const car=Array.isArray(data.vehicles)?data.vehicles[0]:null;
      if(!car){
        status.textContent='Аккаунт подключён, но Tesla не вернула список автомобилей.';
        link.classList.remove('hidden');
        link.textContent='Переподключить';
        return;
      }
      const hasBattery=updateVehicle(car);
      if(!hasBattery && car.state==='online' && account.csrf){
        feedback.textContent='Загружаем актуальные показатели автомобиля…';
        await fetchLive(account.csrf,car);
        if(feedback.textContent==='Загружаем актуальные показатели автомобиля…')
          feedback.textContent='Показатели батареи временно недоступны. Попробуйте открыть кабинет позднее.';
      }
    }catch{
      status.textContent='Не удалось получить данные Tesla.';
    }
  }
  void load();
})();
