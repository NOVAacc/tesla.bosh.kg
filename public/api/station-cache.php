<?php
declare(strict_types=1);

function cachePath(string $key): string
{
    return sys_get_temp_dir() . '/tesla-map-' . hash('sha256', __DIR__) . "-$key.json";
}

function readCache(string $path): ?array
{
    $data = json_decode((string) @file_get_contents($path), true);
    return is_array($data) && isset($data['checkedAt']) ? $data : null;
}

function sparkSiteId(array $station): string
{
    $address = preg_replace('/\s+/u', ' ', trim($station['address'] ?? '')) ?? '';
    $address = function_exists('mb_strtolower') ? mb_strtolower($address, 'UTF-8') : strtolower($address);
    return $address !== '' ? "spark:site:$address" : $station['id'];
}

function cachedStationAddress(string $id): ?string
{
    static $addresses = [];
    $network = strstr($id, ':', true);
    $key = ['spark'=>'spark-v4', 'charge24'=>'charge24', 'weway'=>'weway', 'evion'=>'evion', 'tazatok'=>'tazatok'][$network] ?? null;
    if ($key === null) return null;
    if (!isset($addresses[$key])) {
        $addresses[$key] = [];
        $cache = readCache(cachePath($key));
        foreach ($cache['stations'] ?? [] as $station) {
            $address = trim($station['address'] ?? '');
            if ($address === '') continue;
            $addresses[$key][$station['id']] = $address;
            if ($network === 'spark') $addresses[$key][sparkSiteId($station)] ??= $address;
        }
    }
    return $addresses[$key][$id] ?? (str_starts_with($id, 'spark:site:') ? substr($id, 11) : null);
}
