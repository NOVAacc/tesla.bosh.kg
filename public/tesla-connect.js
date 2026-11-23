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
      const cars=(data.vehicles||[]).map(v=>v.display_name+' · '+v.state+' · ••••'+v.vin_suffix);
      status.textContent=cars.length?'Подключено: '+cars.join('; '):'Аккаунт Tesla подключён. Автомобили пока не обнаружены.';
      if(data.api_status)status.textContent+=' Запрос Tesla вернул HTTP '+data.api_status+'.';
    } catch {
      status.textContent='Не удалось проверить подключение Tesla.';
    }
  }
  void load();
})();
