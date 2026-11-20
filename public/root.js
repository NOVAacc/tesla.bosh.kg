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

function networkCard(name, snapshot) {
  const card = textElement('article', '', 'card');
  card.append(textElement('h3', name));
  if (!snapshot || snapshot.unavailable) {
    card.append(textElement('p', 'Данные сети временно недоступны.', 'muted'));
    return card;
  }
  if (snapshot.stale) card.append(textElement('p', 'Последние сохранённые данные', 'muted'));
  const stats = document.createElement('dl');
  for (const [label, amount] of [
    ['Всего зарядок', snapshot.total],
    ['Рабочие', snapshot.counts.working],
    ['Не работают', snapshot.counts.inactive],
    ['Статус неизвестен', snapshot.counts.unknown]
  ]) {
    const item = document.createElement('div');
    item.append(textElement('dt', label), textElement('dd', Number(amount).toLocaleString('ru-RU')));
    stats.append(item);
  }
  card.append(stats);
  if (!snapshot.priced) {
    card.append(textElement('p', 'Тарифы в данных сети отсутствуют.', 'muted'));
    return card;
  }
  const details = document.createElement('details');
  const list = document.createElement('ul');
  for (const [price, count] of snapshot.tariffs) list.append(textElement('li', price+' · зарядок: '+count));
  details.append(textElement('summary', 'Тарифы · данные по '+snapshot.priced+' из '+snapshot.total), list);
  card.append(details);
  return card;
}

const status = document.getElementById('networkStatus');
const refresh = document.getElementById('refreshNetworks');
const cards = document.getElementById('networkCards');
const networkCacheKey = 'ev-root-networks-v1';

function renderNetworkSnapshot(snapshot) {
  cards.replaceChildren(...operators.map(([key,name]) => networkCard(name, snapshot.networks[key])));
  const date = new Date(snapshot.savedAt);
  status.textContent = 'Последнее ручное обновление: ' + date.toLocaleString('ru-RU');
}
function loadCachedNetworks() {
  try {
    const cached = JSON.parse(localStorage.getItem(networkCacheKey) || 'null');
    if (!cached || !Number.isFinite(cached.savedAt) || !cached.networks ||
        !operators.every(([key]) => cached.networks[key])) throw Error('No cache');
    renderNetworkSnapshot(cached);
  } catch {
    status.textContent = 'Статистика ещё не загружена в этом браузере. Нажмите «Обновить».';
  }
}
async function loadNetworks() {
  refresh.disabled = true;
  status.textContent = 'Обновление статистики по запросу…';
  try {
    const response = await fetch('/api/stations', {cache: 'no-store'});
    if (!response.ok) throw Error('Stations unavailable');
    const {stations,meta} = await response.json();
    if (!Array.isArray(stations)) throw Error('Invalid stations response');
    const networks = {};
    for (const [key,name] of operators) {
      const entries = stations.filter(station => station.operator === name);
      const summary = summarize(entries);
      networks[key] = {
        total: entries.length,
        counts: summary.counts,
        tariffs: [...summary.tariffs].sort((a,b)=>b[1]-a[1]||a[0].localeCompare(b[0],'ru',{numeric:true})),
        priced:summary.priced,
        stale: Boolean(meta?.[key]?.source === 'snapshot' || meta?.[key]?.error),
        unavailable: Boolean(meta?.[key]?.source === 'none' || (!meta?.[key] && !entries.length))
      };
    }
    const snapshot = {savedAt:Date.now(),networks};
    try { localStorage.setItem(networkCacheKey, JSON.stringify(snapshot)); } catch {}
    renderNetworkSnapshot(snapshot);
  } catch {
    status.textContent = 'Не удалось обновить статистику. Сохранённые данные остаются без изменений.';
  } finally {
    refresh.disabled = false;
  }
}
if (refresh) {
  refresh.addEventListener('click', loadNetworks);
  loadCachedNetworks();
}

const feedbackTypes = {broken:'Не работает',incorrect_info:'Неверные данные',access:'Проблема доступа',other:'Другое'};
const feedbackStatuses = {new:'Новое',reviewing:'В работе',resolved:'Решено',dismissed:'Отклонено'};
let feedbackPageIndex = 1;
let feedbackRequest = 0;
function feedbackCard(item) {
  const card = textElement('article','','card feedback-card');
  const top = textElement('div','','feedback-top');
  top.append(textElement('strong', 'Обращение №'+item.id), textElement('span',feedbackStatuses[item.status] || item.status,'feedback-state'),
    textElement('span',Number(item.is_public) && item.status!=='dismissed'?'На карте':'Только в админке','feedback-state feedback-visibility'));
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
    if(!response.ok){
      if(response.status===403)throw Error('Доступ запрещён. Войдите под аккаунтом владельца.');
      if(response.status===503){
        const failure=await response.json().catch(()=>null);
        if(failure?.error==='feedback_storage_unavailable')throw Error('Хранилище обращений ещё не подключено.');
        throw Error('Ошибка чтения базы данных (503).');
      }
      throw Error('Сервер вернул HTTP '+response.status+'.');
    }
    const data=await response.json();
    if(request!==feedbackRequest)return;
    feedbackPageIndex=data.page;
    cards.replaceChildren(...data.items.map(feedbackCard));
    status.textContent=data.total===0?'В базе нет сохранённых обращений.':'Всего сообщений: '+data.total.toLocaleString('ru-RU');
    document.getElementById('feedbackPage').textContent=data.total?'Страница '+data.page+' из '+Math.ceil(data.total/data.perPage):'';
    prev.disabled=page<=1;
    next.disabled=page*data.perPage>=data.total;
  } catch (error) {
    if(request!==feedbackRequest)return;
    status.textContent='Не удалось загрузить сообщения. '+(error?.message || 'Ошибка сети.');
    cards.replaceChildren();
    document.getElementById('feedbackPage').textContent='';
  } finally {
    if(request===feedbackRequest)refresh.disabled=false;
  }
}
if (document.getElementById('refreshFeedback')) {
document.getElementById('refreshFeedback').addEventListener('click',()=>loadFeedback(feedbackPageIndex));
document.getElementById('feedbackPrev').addEventListener('click',()=>loadFeedback(Math.max(1,feedbackPageIndex-1)));
document.getElementById('feedbackNext').addEventListener('click',()=>loadFeedback(feedbackPageIndex+1));
loadFeedback();
}
