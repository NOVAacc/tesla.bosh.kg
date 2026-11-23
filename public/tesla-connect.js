'use strict';
(() => {
  const box=document.getElementById('teslaConnectCard');
  const status=document.getElementById('teslaConnectStatus');
  const link=document.getElementById('teslaConnectLink');
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
      const cars=(data.vehicles||[]).map(v=>{
        const details=[v.display_name,'••••'+v.vin_suffix,v.state==='online'?'В сети':v.state==='asleep'?'Спит':v.state];
        if(Number.isFinite(Number(v.battery_level)) && v.battery_level!==undefined)details.push('Батарея '+Number(v.battery_level)+'%');
        if(Number.isFinite(Number(v.battery_range)) && v.battery_range!==undefined)details.push('Запас хода ≈'+Math.round(Number(v.battery_range)*1.609344)+' км');
        if(v.charging_state)details.push('Зарядка: '+v.charging_state);
        if(v.charge_timestamp) {
          const timestamp=new Date(Number(v.charge_timestamp));
          if(!Number.isNaN(timestamp.getTime()))details.push('Данные на '+timestamp.toLocaleString('ru-RU'));
        }
        if(v.cached_status===404 || v.cached_status===412)details.push('Кэш Tesla пока недоступен');
        return details.join(' · ');
      });
      status.textContent=cars.length?'Подключено: '+cars.join('; '):'Аккаунт Tesla подключён. Автомобили пока не обнаружены.';
      if(data.api_status)status.textContent+=' Запрос Tesla вернул HTTP '+data.api_status+'.';
    } catch {
      status.textContent='Не удалось проверить подключение Tesla.';
    }
  }
  void load();
})();
