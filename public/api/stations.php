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
        : 'query ChargingStationsList($page: Page) { ChargingStationsList(page: $page) { count rows { id seqNo name active address blacklisted forceBlocked coordinates { latitude longitude } evses { id connectors { id type status pending chargingFinished activeTransactionBrief { percentage } } } } } }';
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
                    'type' => $connector['type'] ?? null, 'status' => $status, 'pending' => $pending,
                    'chargingFinished' => !empty($connector['chargingFinished']),
                    'percentage' => $connector['activeTransactionBrief']['percentage'] ?? null,
                    'tariff' => $connector['tariff'] ?? null,
                    'powerKw' => $connector['power'] ?? null,
                    'available' => $active && !$blocked && $status === 'AVAILABLE' && !$pending,
                ];
            }
        }
        $stations[] = stationWithConnectors([
            'id' => "spark:$id", 'sourceId' => $id, 'seqNo' => $row['seqNo'] ?? null, 'operator' => 'SPARK KG',
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

function evionStations(): array
{
    $curl = curl_init('https://electrifly.ru/api/location/list');
    curl_setopt_array($curl, [
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json, text/plain, */*',
            'protocol: kg.evion.app|1.10.1|211|ios|27.3',
            'User-Agent: kg.evion.app',
            'Accept-Language: ru',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($curl);
    $httpStatus = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($body === false || $httpStatus !== 200) {
        throw new RuntimeException("EVION HTTP $httpStatus");
    }
    $rows = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($rows) || !array_is_list($rows)) {
        throw new RuntimeException('EVION: некорректный список локаций');
    }
    if (!$rows) {
        throw new RuntimeException('EVION: пустой каталог');
    }
    $stations = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $lat = $row['coordinates']['latitude'] ?? null;
        $lng = $row['coordinates']['longitude'] ?? null;
        if (!is_numeric($lat) || !is_numeric($lng) || $lat < 39 || $lat > 44 || $lng < 69 || $lng > 81) {
            continue;
        }
        $connectors = [];
        foreach ($row['evses'] ?? [] as $evse) {
            foreach ($evse['connectors'] ?? [] as $connector) {
                $status = strtoupper((string) ($connector['status'] ?? 'UNKNOWN'));
                $ocppStatus = strtoupper((string) ($connector['ocppStatus'] ?? ''));
                $emergency = !empty($evse['emergency']);
                $available = $status === 'AVAILABLE' && $ocppStatus === 'AVAILABLE' && !$emergency;
                $tariffs = array_values(array_filter(array_map(
                    static fn ($tariff) => is_array($tariff) ? ($tariff['text'] ?? null) : null,
                    $connector['tariffs'] ?? []
                )));
                $connectors[] = [
                    'id' => (string) ($connector['_id'] ?? ''),
                    'connectorId' => $connector['_id'] ?? null,
                    'evseId' => $evse['_id'] ?? null,
                    'type' => $connector['standard'] ?? null,
                    'status' => $emergency ? 'UNAVAILABLE' : ($ocppStatus === 'FINISHING' ? 'FINISHING' : ($ocppStatus === 'PREPARING' ? 'PREPARING' : $status)),
                    'ocppStatus' => $connector['ocppStatus'] ?? null,
                    'percentage' => null,
                    'powerKw' => is_numeric($connector['power'] ?? null) ? (float) $connector['power'] : null,
                    'tariff' => $tariffs ? implode(', ', $tariffs) : null,
                    'available' => $available,
                ];
            }
        }
        if (!$connectors) {
            continue;
        }
        $powers = array_column($connectors, 'powerKw');
        $stations[] = stationWithConnectors([
            'id' => 'evion:' . (string) ($row['_id'] ?? ''),
            'sourceId' => (string) ($row['_id'] ?? ''),
            'operator' => 'EVION',
            'name' => $row['name'] ?? 'EVION',
            'address' => $row['address'] ?? '',
            'lat' => (float) $lat, 'lng' => (float) $lng,
            'active' => true, 'blocked' => false,
            'powerKw' => $powers ? (max($powers) ?: null) : null,
        ], $connectors);
    }
    if (!$stations) {
        throw new RuntimeException('EVION: нет станций с корректными координатами и разъёмами');
    }
    return $stations;
}

function wewayStations(): array
{
    $curl = curl_init('https://users.weway.tech/api/v1/stations/?page=1&size=100');
    curl_setopt_array($curl, [
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 12,
    ]);
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($body === false || $status !== 200) {
        throw new RuntimeException("WeWay HTTP $status");
    }
    $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    $rows = $payload['items'] ?? null;
    if (!is_array($rows) || !is_int($payload['total'] ?? null)
        || count($rows) !== $payload['total'] || ($payload['pages'] ?? null) !== 1) {
        throw new RuntimeException('WeWay: неполный каталог');
    }
    $stations = [];
    foreach ($rows as $row) {
        $lat = $row['latitude'] ?? null;
        $lng = $row['longitude'] ?? null;
        if (!is_numeric($lat) || !is_numeric($lng) || $lat < 39 || $lat > 44 || $lng < 69 || $lng > 81) {
            continue;
        }
        $active = ($row['online'] ?? false) === true && ($row['state'] ?? null) === 1;
        $connectors = [];
        foreach ($row['connectors'] ?? [] as $group) {
            foreach ($group['items'] ?? [] as $connector) {
                $portStatus = strtoupper((string) ($connector['status'] ?? 'UNKNOWN'));
                $connectors[] = [
                    'id' => (string) ($connector['ocpp_16_id'] ?? ''),
                    'connectorId' => $connector['ocpp_16_id'] ?? null,
                    'type' => $group['connector_type'] ?? null,
                    'status' => $portStatus, 'percentage' => null,
                    'powerKw' => is_numeric($connector['power'] ?? null) ? (float) $connector['power'] : null,
                    'position' => $connector['position'] ?? null,
                    'available' => $active && $portStatus === 'AVAILABLE',
                ];
            }
        }
        $promotions = $row['promotions'] ?? [];
        $restricted = false;
        foreach ($promotions as $promotion) {
            if (preg_match('/только для сотрудников|ограничен.*доступ/ui', (string) $promotion)) {
                $restricted = true;
                break;
            }
        }
        $powers = array_column($connectors, 'powerKw');
        $stations[] = stationWithConnectors([
            'id' => 'weway:' . (string) ($row['identity'] ?? $row['id']),
            'sourceId' => (string) ($row['identity'] ?? ''),
            'operator' => 'WeWay', 'name' => $row['name'] ?? 'Зарядная станция',
            'address' => $row['address'] ?? '', 'lat' => (float) $lat, 'lng' => (float) $lng,
            'active' => $active, 'blocked' => !$active, 'restricted' => $restricted,
            'promotions' => $promotions,
            'powerKw' => $powers ? (max($powers) ?: null) : null,
        ], $connectors);
    }
    return $stations;
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
    $path = sys_get_temp_dir() . '/tesla-map-' . hash('sha256', __DIR__) . ($operator === 'spark' ? '-spark-v4.json' : "-$operator.json");
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

function sparkCardDetails(string $stationId): array
{
    $query = <<<'GRAPHQL'
query ChargingStationCard($id: String) {
  ChargingStationCard(id: $id) {
    id
    evses {
      id
      connectors {
        id evseId type status chargingFinished power
        activeTransactionBrief { percentage estimatedChargingTime }
        tariff { type amount currency }
      }
    }
  }
}
GRAPHQL;
    $headers = ['tenant: spark', 'x-application-version: 2.3.3-ios'];
    // Optional server-side credential, never expose it in frontend JavaScript.
    $token = trim((string) getenv('SPARK_API_TOKEN'));
    if ($token === '') {
        // Dedicated private credential file (outside the site's public directory).
        $privateFile = '/home/g143326/.spark-api-token';
        if (is_file($privateFile) && is_readable($privateFile)) {
            $token = trim((string) file_get_contents($privateFile));
        }
    }
    if ($token !== '') {
        $headers[] = 'Authorization: ' . $token;
    }
    $card = graphql('https://api.itcharge.ru/graphql/v1', $query, ['id' => $stationId], $headers)['ChargingStationCard'] ?? [];
    $connectors = [];
    foreach ($card['evses'] ?? [] as $device) {
        foreach ($device['connectors'] ?? [] as $connector) {
            $percentage = $connector['activeTransactionBrief']['percentage'] ?? null;
            $occupied = ($connector['status'] ?? '') === 'OCCUPIED' && empty($connector['chargingFinished']);
            $percent = $occupied && is_numeric($percentage) && (float) $percentage >= 0 && (float) $percentage <= 100
                ? (float) $percentage : null;
            $tariff = $connector['tariff'] ?? null;
            $amount = is_array($tariff) && is_numeric($tariff['amount'] ?? null) ? (float) $tariff['amount'] : null;
            $connectors[$device['id'] . ':' . $connector['id']] = [
                'percentage' => $percent,
                'tariff' => $amount === null ? null : [
                    'amount' => $amount,
                    'currency' => $tariff['currency'] ?? 'KGS',
                    'type' => $tariff['type'] ?? null,
                ],
                'powerKw' => is_numeric($connector['power'] ?? null) ? (float) $connector['power'] : null,
            ];
        }
    }
    return $connectors;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (isset($_GET['sparkCard'])) {
    $stationId = (string) $_GET['sparkCard'];
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $stationId)) {
        http_response_code(400);
        echo '{"error":"Invalid station ID"}';
        exit;
    }
    // Brief cache protects the upstream API while keeping charging percentages fresh.
    $cachePath = sys_get_temp_dir() . '/tesla-spark-card-' . hash('sha256', $stationId) . '.json';
    $cached = is_file($cachePath) ? json_decode((string) @file_get_contents($cachePath), true) : null;
    if (is_array($cached) && isset($cached['checkedAt']) && time() - $cached['checkedAt'] < 45) {
        echo json_encode(['connectors' => (object) ($cached['connectors'] ?? [])], JSON_THROW_ON_ERROR);
        exit;
    }
    try {
        $connectors = sparkCardDetails($stationId);
        @file_put_contents($cachePath, json_encode(['checkedAt' => time(), 'connectors' => $connectors], JSON_THROW_ON_ERROR), LOCK_EX);
        echo json_encode(['connectors' => (object) $connectors], JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        http_response_code(502);
        echo json_encode(['error' => 'SPARK card unavailable', 'reason' => trim((string) getenv('SPARK_API_TOKEN')) === '' && !is_file('/home/g143326/.spark-api-token') ? 'credential_not_configured' : 'upstream_unavailable'], JSON_THROW_ON_ERROR);
    }
    exit;
}

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
$weway = loadCached('weway', static fn (): array => ['stations' => wewayStations()]);
$evion = loadCached('evion', static fn (): array => ['stations' => evionStations()]);
$spark['count'] = count($spark['stations']);
$charge['count'] = count($charge['stations']);
$weway['count'] = count($weway['stations']);
$evion['count'] = count($evion['stations']);
$stations = array_merge($spark['stations'], $charge['stations'], $weway['stations'], $evion['stations']);
$sites = groupSites($spark['stations']);
unset($spark['stations'], $spark['checkedAt'], $charge['stations'], $charge['checkedAt'], $weway['stations'], $weway['checkedAt'], $evion['stations'], $evion['checkedAt']);
echo json_encode([
    'stations' => $stations, 'sites' => $sites, 'meta' => ['spark' => $spark, 'charge24' => $charge, 'weway' => $weway, 'evion' => $evion],
], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
