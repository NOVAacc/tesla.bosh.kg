const numeric = value => (typeof value === 'number' || (typeof value === 'string' && value.trim()))
  && Number.isFinite(Number(value));

export function normalizeTazatok({items, total}) {
  if (!Array.isArray(items) || !items.length || items.length !== total) {
    throw new Error('Получен неполный список TazaTok');
  }
  const stations = [];
  for (const row of items) {
    const sourceId = String(row.id ?? '');
    const {lat, lng} = row;
    if (!sourceId || !numeric(lat) || !numeric(lng) || lat < 39 || lat > 44 || lng < 69 || lng > 81) {
      continue;
    }
    const active = row.online === true;
    const name = row.name ?? 'TazaTok';
    const current = name.match(/\b(AC|DC)\s*\d/i)?.[1].toUpperCase();
    const price = row.price_per_kwh;
    const tariff = numeric(price) && price >= 0 ? {type: 'PER_E', currency: 'KGS', amount: Number(price)} : null;
    const connectors = [];
    for (const port of row.connectors ?? []) {
      const connectorId = port.connector_id;
      if (connectorId == null) continue;
      const reported = String(port.status ?? '').toUpperCase();
      let status = 'UNKNOWN';
      if (['BUSY', 'CHARGING', 'OCCUPIED'].includes(reported)) status = 'OCCUPIED';
      if (['AVAILABLE', 'FAULTED', 'UNAVAILABLE', 'FINISHING', 'PREPARING', 'RESERVED'].includes(reported)) status = reported;
      if (!active) status = 'UNAVAILABLE';
      const soc = port.soc_current;
      connectors.push({
        id: `${sourceId}:${connectorId}`, connectorId,
        type: port.connector_type === 'GB/T' && current ? `GBT_${current}` : port.connector_type ?? null,
        status, available: status === 'AVAILABLE', tariff,
        percentage: status === 'OCCUPIED' && numeric(soc) && soc >= 0 && soc <= 100 ? Number(soc) : null,
      });
    }
    const power = row.max_power_kw;
    stations.push({
      id: `tazatok:${sourceId}`, sourceId, seqNo: row.station_number ?? null, operator: 'TazaTok', name,
      address: row.address || row.city || '', lat: Number(lat), lng: Number(lng), active, blocked: false,
      powerKw: numeric(power) && power > 0 ? Number(power) : null,
      connectors, totalConnectors: connectors.length,
      availableConnectors: connectors.filter(port => port.available).length,
    });
  }
  if (!stations.length) throw new Error('TazaTok: нет станций с корректными координатами');
  return stations;
}

export async function loadTazatok() {
  const response = await fetch('https://api.ecoauto.kg/api/v1/stations', {
    headers: {Accept: 'application/json'},
    signal: AbortSignal.timeout(6000),
  });
  if (!response.ok) throw new Error(`TazaTok HTTP ${response.status}`);
  return normalizeTazatok(await response.json());
}
