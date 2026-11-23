const fmt=value=>Number(value||0).toLocaleString('ru-RU');
const names={map_open:'Открытия карты',station_open:'Карточки станций',filter_apply:'Применения фильтров',favorite_add:'Добавления в избранное',favorite_remove:'Удаления из избранного',list_open:'Переходы к списку',faq_open:'Открытия FAQ',services_open:'EV-сервисы',route_open:'Маршруты'};
const container=document.getElementById('analyticsContent'),message=document.getElementById('analyticsStatus');
function node(tag,content,cls){const el=document.createElement(tag);if(cls)el.className=cls;el.textContent=content;return el}
function table(rows,headers){const tbl=node('table','','root-user-table');const head=document.createElement('thead');const tr=document.createElement('tr');headers.forEach(x=>tr.append(node('th',x)));head.append(tr);tbl.append(head);const body=document.createElement('tbody');rows.forEach(row=>{const tr=document.createElement('tr');row.forEach(v=>tr.append(node('td',String(v))));body.append(tr)});tbl.append(body);const wrap=node('div','','root-user-table-wrap');wrap.append(tbl);return wrap}
function section(title,contents){const area=node('section','','card');area.append(node('h3',title),contents);return area}
async function load(){
 const days=Number(document.getElementById('analyticsDays').value);
 message.textContent='Загружаем аналитику…';
 try{
  const response=await fetch('/api/root-analytics.php?days='+days,{cache:'no-store'});
  if(!response.ok)throw Error('HTTP '+response.status);
  const data=await response.json();
  container.replaceChildren();
  const grid=node('div','','analytics-summary');
  for(const [label,value] of [['Посетители',data.counts.visitors],['Активные',data.counts.engaged],['События',data.counts.events],['Регистрации (всё время)',data.counts.registered],['Сохранили избранное',data.counts.favorite_users],['Избранных станций',data.counts.favorite_stations],['Всего сохранений сейчас',data.counts.favorite_links]]){
    const card=node('div','','analytics-metric');card.append(node('span',label),node('strong',fmt(value)));grid.append(card);
  }
  container.append(grid);
  container.append(section('Действия пользователей',table(data.events.map(x=>[names[x.event_name]||x.event_name,fmt(x.total),fmt(x.users)]),['Действие','События','Пользователи'])));
  container.append(section('Используемые фильтры',table(data.filters.map(x=>[x.event_label||'Без категории',fmt(x.total)]),['Фильтр','Использований'])));
  container.append(section('Посещаемость по дням',table(data.daily.map(x=>[x.day,fmt(x.visitors),fmt(x.engaged)]),['День','Посетители','Активные'])));
  container.append(section('Популярные в избранном станции (за всё время)',table(data.topFavorites.map(x=>[x.station_id,fmt(x.total)]),['Станция','Сохранений'])));
  message.textContent='Данные за выбранный период. «Активный» — посетитель, совершивший полезное действие. Для анонимных посетителей уникальность приблизительная.';
 }catch(e){message.textContent='Аналитика пока недоступна: '+e.message}
}
document.getElementById('analyticsDays').addEventListener('change',load);
document.getElementById('analyticsRefresh').addEventListener('click',load);
load();
