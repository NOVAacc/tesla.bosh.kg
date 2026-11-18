<?php
declare(strict_types=1);

function graphql(string $url, string $query, array $variables, array $headers = []): array
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['query' => $query, 'variables' => $variables], JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 8,
    ]);
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if ($body === false || $status !== 200) {
        throw new RuntimeException("Ошибка подключения (HTTP $status)");
    }
    $result = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if (!empty($result['errors']) || !is_array($result['data'] ?? null)) {
        throw new RuntimeException('Ошибка ответа GraphQL');
    }
    return $result['data'];
}

function sparkRows(bool $ccs2): array
{
    $query = $ccs2
        ? 'query ChargingStationsList($page: Page, $connectors: ConnectorType) { ChargingStationsList(page: $page, connectors: $connectors) { count rows { id } } }'
        : 'query ChargingStationsList($page: Page) { ChargingStationsList(page: $page) { count rows { id name active address blacklisted forceBlocked coordinates { latitude longitude } evses { id connectors { id status pending chargingFinished } } } } }';
    $rows = [];
    $total = null;
    $deadline = microtime(true) + 22;
    for ($page = 1; $page <= 100 && microtime(true) < $deadline; $page++) {
        $variables = ['page' => ['size' => 100, 'number' => $page]];
        if ($ccs2) {
            $variables['connectors'] = ['type' => ['in' => ['C_CCS2']]];
        }
        $data = graphql('https://api.itcharge.ru/graphql/v1', $query, $variables, [
            'tenant: spark', 'x-application-version: 2.3.3-ios',
        ])['ChargingStationsList'] ?? [];
        if (!is_int($data['count'] ?? null) || !is_array($data['rows'] ?? null)
            || ($total !== null && $total !== $data['count'])) {
            break;
        }
        $total = $data['count'];
        foreach ($data['rows'] as $row) {
            $rows[(string) $row['id']] = $row;
        }
        if (count($rows) === $total) {
            return $rows;
        }
        if (!$data['rows'] || count($rows) > $total) {
            break;
        }
    }
    throw new RuntimeException('Получен неполный список SPARK KG');
}

function sparkStations(array $rows, array $ccs2 = []): array
{
    $stations = [];
    foreach ($rows as $row) {
        $lat = $row['coordinates']['latitude'] ?? null;
        $lng = $row['coordinates']['longitude'] ?? null;
        if (!is_numeric($lat) || !is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            continue;
        }
        $id = $row['id'];
        $active = !empty($row['active']);
        $blocked = !empty($row['forceBlocked']) || !empty($row['blacklisted']);
        $connectors = [];
        foreach ($row['evses'] ?? [] as $device) {
            foreach ($device['connectors'] ?? [] as $connector) {
                $status = $connector['status'] ?: 'UNKNOWN';
                $pending = !empty($connector['pending']);
                $connectors[] = [
                    'id' => "$id:{$device['id']}:{$connector['id']}",
                    'evseId' => $device['id'], 'connectorId' => $connector['id'],
                    'type' => null, 'status' => $status, 'pending' => $pending,
                    'chargingFinished' => !empty($connector['chargingFinished']),
                    'available' => $active && !$blocked && $status === 'AVAILABLE' && !$pending,
                ];
            }
        }
        $stations[] = stationWithConnectors([
            'id' => "spark:$id", 'sourceId' => $id, 'operator' => 'SPARK KG',
            'name' => $row['name'], 'address' => $row['address'] ?? '',
            'lat' => (float) $lat, 'lng' => (float) $lng, 'active' => $active, 'blocked' => $blocked,
            'supportedConnectorTypes' => isset($ccs2[(string) $id]) ? ['CCS2'] : ($row['supportedConnectorTypes'] ?? []),
            'powerKw' => null,
        ], $connectors);
    }
    return $stations;
}

function chargeStations(): array
{
    $query = <<<'GRAPHQL'
query PullPoint($checkpoint: PointInputCheckpoint!, $limit: Int!) {
  pullPoint(checkpoint: $checkpoint, limit: $limit) {
    documents { id name status isOpen lat lng
      locationGroup { id name address data { latitude longitude } }
      data { latitude longitude address tariffPower tariffPrice }
      connectors { connectorId status data { name percentage } }
    }
    checkpoint { id updatedAt }
  }
}
GRAPHQL;
    $cursor = ['id' => '0', 'updatedAt' => 0];
    $seen = [];
    $stations = [];
    $deadline = microtime(true) + 32;
    for ($page = 0; $page < 200 && microtime(true) < $deadline; $page++) {
        $data = graphql('https://charge24.app/graphql', $query, ['checkpoint' => $cursor, 'limit' => 25])['pullPoint'] ?? [];
        $rows = $data['documents'] ?? null;
        if (!is_array($rows)) {
            break;
        }
        foreach ($rows as $row) {
            $lat = $row['lat'] ?? $row['data']['latitude'] ?? $row['locationGroup']['data']['latitude'] ?? null;
            $lng = $row['lng'] ?? $row['data']['longitude'] ?? $row['locationGroup']['data']['longitude'] ?? null;
            if (!is_numeric($lat) || !is_numeric($lng) || $lat < 39 || $lat > 44 || $lng < 69 || $lng > 81) {
                continue;
            }
            $connectors = [];
            foreach ($row['connectors'] ?? [] as $connector) {
                $status = $connector['status'] ?? 'UNKNOWN';
                $connectors[] = [
                    'id' => (string) ($connector['connectorId'] ?? ''),
                    'type' => $connector['data']['name'] ?? null, 'status' => $status,
                    'percentage' => $connector['data']['percentage'] ?? null,
                    'available' => in_array(strtolower($status), ['available', 'free'], true),
                ];
            }
            $stations[$row['id']] = stationWithConnectors([
                'id' => 'charge24:' . $row['id'], 'operator' => 'Charge24',
                'name' => $row['name'] ?: 'Зарядная станция',
                'address' => $row['data']['address'] ?? $row['locationGroup']['address'] ?? '',
                'lat' => (float) $lat, 'lng' => (float) $lng, 'active' => ($row['isOpen'] ?? true) !== false,
                'blocked' => false, 'powerKw' => $row['data']['tariffPower'] ?? null,
                'price' => $row['data']['tariffPrice'] ?? null,
            ], $connectors);
        }
        $next = $data['checkpoint'] ?? null;
        if (count($rows) < 25 || !$next) {
            if ($stations) {
                return array_values($stations);
            }
            break;
        }
        if (!isset($next['id']) || !is_numeric($next['updatedAt'] ?? null)) {
            break;
        }
        $cursor = ['id' => (string) $next['id'], 'updatedAt' => (float) $next['updatedAt']];
        $key = json_encode($cursor);
        if (isset($seen[$key])) {
            break;
        }
        $seen[$key] = true;
    }
    throw new RuntimeException('Получен неполный список Charge24');
}

function stationWithConnectors(array $station, array $connectors): array
{
    return $station + [
        'connectors' => $connectors,
        'availableConnectors' => count(array_filter($connectors, static fn ($c) => $c['available'])),
        'totalConnectors' => count($connectors),
    ];
}

function groupSites(array $stations): array
{
    $groups = [];
    foreach ($stations as $station) {
        $address = preg_replace('/\s+/u', ' ', trim($station['address']));
        $address = function_exists('mb_strtolower') ? mb_strtolower($address, 'UTF-8') : strtolower($address);
        $key = $address !== '' ? "spark:site:$address" : $station['id'];
        $groups[$key] ??= [
            'id' => $key, 'operator' => 'SPARK KG',
            'name' => preg_replace('/\s+№\d+\b.*$/u', '', $station['name']),
            'address' => $station['address'], 'lat' => $station['lat'], 'lng' => $station['lng'],
            'devices' => [], 'supportedConnectorTypes' => [], 'availableConnectors' => 0, 'totalConnectors' => 0,
        ];
        $groups[$key]['devices'][] = $station;
        $groups[$key]['supportedConnectorTypes'] = array_values(array_unique(array_merge(
            $groups[$key]['supportedConnectorTypes'], $station['supportedConnectorTypes']
        )));
        $groups[$key]['availableConnectors'] += $station['availableConnectors'];
        $groups[$key]['totalConnectors'] += $station['totalConnectors'];
    }
    return array_values($groups);
}

function loadCached(string $operator, callable $loader, array $fallback = []): array
{
    $path = sys_get_temp_dir() . '/tesla-map-' . hash('sha256', __DIR__) . "-$operator.json";
    $cached = is_file($path) ? json_decode((string) @file_get_contents($path), true) : null;
    if ($cached && time() - $cached['checkedAt'] < ($cached['error'] ? 30 : 300)) {
        return $cached;
    }
    try {
        $result = $loader() + ['source' => 'live', 'updatedAt' => gmdate(DATE_ATOM), 'error' => null];
    } catch (Throwable $error) {
        $result = $cached ?? ['stations' => $fallback, 'source' => $fallback ? 'snapshot' : 'none', 'updatedAt' => null];
        $result['error'] = $error->getMessage();
    }
    $result['checkedAt'] = time();
    $temp = @tempnam(sys_get_temp_dir(), 'tesla-map-');
    if ($temp !== false) {
        if (@file_put_contents($temp, json_encode($result, JSON_INVALID_UTF8_SUBSTITUTE)) && @rename($temp, $path)) {
            return $result;
        }
        @unlink($temp);
    }
    return $result;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$snapshot = json_decode((string) file_get_contents(__DIR__ . '/spark-snapshot.json'), true);
$fallback = sparkStations($snapshot['rows'] ?? []);
$spark = loadCached('spark', static function (): array {
    $rows = sparkRows(false);
    try {
        return ['stations' => sparkStations($rows, sparkRows(true)), 'connectorTypesError' => null];
    } catch (Throwable $error) {
        return ['stations' => sparkStations($rows), 'connectorTypesError' => $error->getMessage()];
    }
}, $fallback);
$charge = loadCached('charge24', static fn (): array => ['stations' => chargeStations()]);
$spark['count'] = count($spark['stations']);
$charge['count'] = count($charge['stations']);
$stations = array_merge($spark['stations'], $charge['stations']);
$sites = groupSites($spark['stations']);
unset($spark['stations'], $spark['checkedAt'], $charge['stations'], $charge['checkedAt']);
echo json_encode([
    'stations' => $stations, 'sites' => $sites, 'meta' => ['spark' => $spark, 'charge24' => $charge],
], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
