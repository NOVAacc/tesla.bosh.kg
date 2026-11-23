'use strict';
(() => {
  const box=document.getElementById('teslaConnectCard');
  const status=document.getElementById('teslaConnectStatus');
  const link=document.getElementById('teslaConnectLink');
  const refresh=document.getElementById('teslaLiveRefresh');
  const liveResult=document.getElementById('teslaLiveResult');
  let currentCsrf='';
  if (!box || !status || !link) return;
  const result=new URLSearchParams(location.search).get('tesla_result');
  if(result) {
    const messages={connected:'Tesla-аккаунт подключён.',cancelled:'Подключение отменено.',expired:'Сессия подключения истекла. Повторите попытку.',invalid_code:'Tesla вернула некорректный код.',token_failed:'Tesla отклонила авторизацию.'};
    status.textContent=messages[result]||'Не удалось подключить Tesla.';
    history.replaceState(null,'',location.pathname);
  }
  async function load() {
    try {
      const response=await fetch('/api/account.php?action=me',{credentials:'same-origin',cache:'no-store'});
      if(!response.ok) return;
      const account=await response.json();
      if(!account.user) return;
      currentCsrf=account.csrf||'';
      const r=await fetch('/api/tesla.php?action=status',{credentials:'same-origin',cache:'no-store'});
      const data=await r.json();
      if(!r.ok) {
        status.textContent=data.error==='tesla_not_configured'?'Подключение Tesla будет доступно после настройки ключей API на сервере.':'Tesla API временно недоступен.';
        link.classList.add('hidden');return;
      }
      if(!data.connected) {
        if(!result)status.textContent='Подключите свой аккаунт Tesla, чтобы видеть автомобили.';
        return;
      }
      link.textContent='Подключить повторно';
      refresh.classList.remove('hidden');
      const cars=(data.vehicles||[]).map(v=>{
        const details=[v.display_name,'••••'+v.vin_suffix,v.state==='online'?'В сети':v.state==='asleep'?'Спит':v.state];
        if(Number.isFinite(Number(v.battery_level)) && v.battery_level!==undefined)details.push('Батарея '+Number(v.battery_level)+'%');
        if(Number.isFinite(Number(v.battery_range)) && v.battery_range!==undefined)details.push('Запас хода ≈'+Math.round(Number(v.battery_range)*1.609344)+' км');
        if(v.charging_state)details.push('Зарядка: '+v.charging_state);
        if(v.charge_timestamp) {
          const timestamp=new Date(Number(v.charge_timestamp));
          if(!Number.isNaN(timestamp.getTime()))details.push('Данные на '+timestamp.toLocaleString('ru-RU'));
        }
        if(v.cached_status===404)details.push('Tesla: кэш пока отсутствует (404)');
        else if(v.cached_status===412)details.push('Tesla: кэш не поддерживается (412)');
        else if(v.cached_status==='no_battery')details.push('Tesla: в кэше нет данных батареи');
        else if(v.cached_status)details.push('Tesla: ошибка кэша HTTP '+v.cached_status);
        return details.join(' · ');
      });
      status.textContent=cars.length?'Подключено: '+cars.join('; '):'Аккаунт Tesla подключён. Автомобили пока не обнаружены.';
      if(data.api_status)status.textContent+=' Список автомобилей: Tesla HTTP '+data.api_status+'.';
    } catch {
      status.textContent='Не удалось проверить подключение Tesla.';
    }
  }
  refresh.addEventListener('click',async()=>{
    refresh.disabled=true;
    liveResult.classList.remove('hidden');
    liveResult.textContent='Запрашиваем данные автомобиля…';
    try {
      const response=await fetch('/api/tesla.php?action=live',{
        method:'POST',credentials:'same-origin',cache:'no-store',
        headers:{'Accept':'application/json','Content-Type':'application/json'},
        body:JSON.stringify({csrf:currentCsrf})
      });
      const data=await response.json();
      if(response.status===429) {
        liveResult.textContent='Повторный запрос доступен через '+(data.retry_after||120)+' сек.';
      } else if(!response.ok) {
        liveResult.textContent='Ошибка сервера: '+(data.error||response.status);
      } else if(!data.ok) {
        liveResult.textContent='Tesla не вернула актуальные данные: '+(data.tesla_status?'HTTP '+data.tesla_status:data.reason||'неизвестная причина');
      } else {
        const values=[];
        if(data.battery_level!=null)values.push('Батарея '+data.battery_level+'%');
        if(data.battery_range!=null)values.push('Запас хода ≈'+Math.round(Number(data.battery_range)*1.609344)+' км');
        if(data.charging_state)values.push('Зарядка: '+data.charging_state);
        if(data.charge_limit_soc!=null)values.push('Лимит '+data.charge_limit_soc+'%');
        liveResult.textContent=values.length?values.join(' · '):'Ответ Tesla получен, но данные батареи отсутствуют.';
      }
    } catch {
      liveResult.textContent='Не удалось выполнить запрос. Проверьте соединение.';
    } finally {refresh.disabled=false;}
  });
  void load();
})();
