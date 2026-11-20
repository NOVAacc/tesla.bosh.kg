const operators = [
  ['spark', 'SPARK KG'],
  ['charge24', 'Charge24'],
  ['weway', 'WeWay'],
  ['evion', 'EVION'],
];
const workingPorts = new Set(['AVAILABLE', 'FREE', 'OCCUPIED', 'CHARGING', 'PREPARING', 'FINISHING', 'RESERVED', 'SUSPENDEDEV', 'SUSPENDEDEVSE']);
const failedPorts = new Set(['FAULTED', 'UNAVAILABLE', 'OFFLINE', 'DISABLED', 'OUT_OF_SERVICE', 'INOPERATIVE']);

function stationStatus(station) {
  if (station.active === false || station.blocked) return 'inactive';
  const ports = (station.connectors ?? []).map(port => ({
    ...port, status: String(port.status ?? '').toUpperCase(),
  }));
  if (ports.some(port => !port.pending && (port.available === true || workingPorts.has(port.status)))) return 'working';
  if (ports.length && ports.every(port => failedPorts.has(port.status))) return 'inactive';
  return 'unknown';
}

function tariffLabel(tariff) {
  if (typeof tariff === 'string') return tariff.trim();
  if (!tariff || tariff.amount == null || tariff.amount === '' || !Number.isFinite(Number(tariff.amount))) return '';
  const currency = tariff.currency === 'KGS' ? 'сом' : tariff.currency ?? '';
  const unit = ({PER_E: '/кВт·ч', PER_T: '/мин'})[tariff.type] ?? '';
  return [Number(tariff.amount).toLocaleString('ru-RU'), currency + unit].filter(Boolean).join(' ');
}

function summarize(stations) {
  const counts = {working: 0, inactive: 0, unknown: 0};
  const tariffs = new Map();
  let priced = 0;
  for (const station of stations) {
    counts[stationStatus(station)]++;
    const prices = new Set((station.connectors ?? []).map(port => tariffLabel(port.tariff)).filter(Boolean));
    if (station.operator === 'Charge24' && station.price != null && String(station.price).trim()) {
      prices.add(String(station.price).trim() + ' сом');
    }
    if (prices.size) priced++;
    for (const price of prices) tariffs.set(price, (tariffs.get(price) ?? 0) + 1);
  }
  return {counts, tariffs, priced};
}

function textElement(tag, text, className) {
  const element = document.createElement(tag);
  element.textContent = text;
  if (className) element.className = className;
  return element;
}

function networkCard(name, stations, meta) {
  const card = textElement('article', '', 'card');
  card.append(textElement('h3', name));
  if (meta?.source === 'none' || (!meta && !stations.length)) {
    card.append(textElement('p', 'Данные сети временно недоступны.', 'muted'));
    return card;
  }
  if (meta?.source === 'snapshot' || meta?.error) {
    card.append(textElement('p', 'Последние сохранённые данные', 'muted'));
  }
  const {counts, tariffs, priced} = summarize(stations);
  const stats = document.createElement('dl');
  for (const [label, amount] of [['Всего зарядок', stations.length], ['Рабочие', counts.working], ['Не работают', counts.inactive], ['Статус неизвестен', counts.unknown]]) {
    const item = document.createElement('div');
    item.append(textElement('dt', label), textElement('dd', amount.toLocaleString('ru-RU')));
    stats.append(item);
  }
  card.append(stats);
  if (!priced) {
    card.append(textElement('p', 'Тарифы в данных сети отсутствуют.', 'muted'));
    return card;
  }
  const details = document.createElement('details');
  const list = document.createElement('ul');
  for (const [price, count] of [...tariffs].sort((a,b) => b[1] - a[1] || a[0].localeCompare(b[0], 'ru', {numeric:true}))) {
    list.append(textElement('li', price + ' · зарядок: ' + count));
  }
  details.append(textElement('summary', 'Тарифы · данные по ' + priced + ' из ' + stations.length), list);
  card.append(details);
  return card;
}

const status = document.getElementById('networkStatus');
const refresh = document.getElementById('refreshNetworks');
const cards = document.getElementById('networkCards');

async function loadNetworks() {
  refresh.disabled = true;
  status.textContent = 'Загрузка статистики…';
  try {
    const response = await fetch('/api/stations', {cache: 'no-store'});
    if (!response.ok) throw Error('Stations unavailable');
    const {stations, meta} = await response.json();
    cards.replaceChildren(...operators.map(([key,name]) =>
      networkCard(name, stations.filter(station => station.operator === name), meta?.[key])));
    status.textContent = '';
  } catch {
    status.textContent = 'Не удалось обновить статистику зарядных сетей.';
  } finally {
    refresh.disabled = false;
  }
}

refresh.addEventListener('click', loadNetworks);
loadNetworks();

const feedbackTypes = {broken:'Не работает',incorrect_info:'Неверные данные',access:'Проблема доступа',other:'Другое'};
const feedbackStatuses = {new:'Новое',reviewing:'В работе',resolved:'Решено',dismissed:'Отклонено'};
let feedbackPageIndex = 1;
let feedbackRequest = 0;
function feedbackCard(item) {
  const card = textElement('article','','card feedback-card');
  const top = textElement('div','','feedback-top');
  top.append(textElement('strong', 'Обращение №'+item.id), textElement('span',feedbackStatuses[item.status] || item.status,'feedback-state'));
  card.append(top);
  const station = textElement('p', 'Станция: '+item.station_id,'feedback-station');
  const info = textElement('p', (feedbackTypes[item.feedback_type] || item.feedback_type) + ' · ' + (item.created_at || ''),'muted');
  const person = textElement('p', 'Отправитель: '+(item.display_name || item.email || 'Пользователь')+(item.display_name && item.email?' · '+item.email:''),'muted');
  const message = textElement('p',item.message,'feedback-message');
  card.append(station,info,person,message);
  return card;
}
async function loadFeedback(page=1) {
  const request=++feedbackRequest;
  const status=document.getElementById('feedbackStatus');
  const cards=document.getElementById('feedbackCards');
  const prev=document.getElementById('feedbackPrev');
  const next=document.getElementById('feedbackNext');
  const refresh=document.getElementById('refreshFeedback');
  prev.disabled=next.disabled=refresh.disabled=true;
  status.textContent='Загрузка сообщений…';
  try {
    const response=await fetch('/api/root-feedback.php?page='+page,{cache:'no-store'});
    if(!response.ok)throw Error('Feedback unavailable');
    const data=await response.json();
    if(request!==feedbackRequest)return;
    feedbackPageIndex=data.page;
    cards.replaceChildren(...data.items.map(feedbackCard));
    status.textContent=data.total===0?'Сообщений пока нет.':'Всего сообщений: '+data.total.toLocaleString('ru-RU');
    document.getElementById('feedbackPage').textContent=data.total?'Страница '+data.page+' из '+Math.ceil(data.total/data.perPage):'';
    prev.disabled=page<=1;
    next.disabled=page*data.perPage>=data.total;
  } catch {
    if(request!==feedbackRequest)return;
    status.textContent='Не удалось загрузить сообщения. Проверьте подключение к базе данных.';
    cards.replaceChildren();
    document.getElementById('feedbackPage').textContent='';
  } finally {
    if(request===feedbackRequest)refresh.disabled=false;
  }
}
document.getElementById('refreshFeedback').addEventListener('click',()=>loadFeedback(feedbackPageIndex));
document.getElementById('feedbackPrev').addEventListener('click',()=>loadFeedback(Math.max(1,feedbackPageIndex-1)));
document.getElementById('feedbackNext').addEventListener('click',()=>loadFeedback(feedbackPageIndex+1));
loadFeedback();
