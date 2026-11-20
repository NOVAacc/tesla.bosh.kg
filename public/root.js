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
