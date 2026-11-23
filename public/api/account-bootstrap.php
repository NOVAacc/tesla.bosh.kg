<?php
declare(strict_types=1);
// Secret settings are read from PHP process environment, or a private file OUTSIDE webroot.
// For this Fornex account, the default private file is /home/g143326/.tesla-ev.env (outside public_html).
// EV_PRIVATE_ENV can override its path. Never put credentials inside the deployed website.
// GitHub Actions secrets are NOT automatically available to PHP at request time.
function ev_config(): array {
  $config = [];
  $file = getenv('EV_PRIVATE_ENV') ?: '/home/g143326/.tesla-ev.env';
  if ($file && is_file($file) && is_readable($file)) {
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
      $line = trim($line);
      if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
      [$key,$value] = explode('=', $line, 2);
      $key = trim($key);
      if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) continue;
      $config[$key] = trim(trim($value), "'\"");
    }
  }
  foreach (['DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD'] as $key) {
    $env = getenv($key);
    if ($env !== false) $config[$key] = $env;
  }
  return $config;
}
function ev_db(): PDO {
  static $db = null;
  if ($db instanceof PDO) return $db;
  $c = ev_config();
  foreach (['DB_HOST','DB_DATABASE','DB_USERNAME','DB_PASSWORD'] as $key) {
    if (!isset($c[$key]) || $c[$key] === '') throw new RuntimeException('Database configuration unavailable');
  }
  $db = new PDO('mysql:host='.$c['DB_HOST'].';port='.($c['DB_PORT'] ?? '3306').';dbname='.$c['DB_DATABASE'].';charset=utf8mb4',
    $c['DB_USERNAME'], $c['DB_PASSWORD'], [
      PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES=>false
    ]);
  return $db;
}
const EV_SESSION_LIFETIME = 30 * 24 * 60 * 60;

function ev_session(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  // Use an app-specific directory so other PHP apps cannot garbage-collect these sessions.
  $path = getenv('EV_SESSION_PATH') ?: '/home/g143326/.tesla-ev-sessions';
  if ((!is_dir($path) && !@mkdir($path, 0700) && !is_dir($path)) || !is_writable($path)) {
    error_log('EV session directory is unavailable');
    ev_response(503,['error'=>'service_unavailable']);
  }
  session_name('ev_session');
  if (!session_start([
    'save_handler'=>'files',
    'save_path'=>$path,
    'gc_maxlifetime'=>EV_SESSION_LIFETIME,
    'gc_probability'=>1,
    'gc_divisor'=>100,
    'use_strict_mode'=>1,
    'use_only_cookies'=>1,
    'cookie_lifetime'=>0,
    'cookie_path'=>'/',
    'cookie_secure'=>true,
    'cookie_httponly'=>true,
    'cookie_samesite'=>'Lax',
  ])) {
    ev_response(503,['error'=>'service_unavailable']);
  }
  if (isset($_SESSION['uid']) && ($_SESSION['expires_at'] ?? 0) <= time()) {
    ev_reset_session();
  }
  if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function ev_reset_session(): void {
  $_SESSION = [];
  if (!session_regenerate_id(true)) {
    ev_response(503,['error'=>'service_unavailable']);
  }
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function ev_sign_in(int $userId): void {
  ev_reset_session();
  $expires = time() + EV_SESSION_LIFETIME;
  $cookie = session_get_cookie_params();
  unset($cookie['lifetime']);
  if (!setcookie(session_name(), session_id(), ['expires'=>$expires] + $cookie)) {
    ev_response(503,['error'=>'service_unavailable']);
  }
  $_SESSION['uid'] = $userId;
  $_SESSION['expires_at'] = $expires;
}
function ev_response(int $code, array $body): never {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  header('X-Content-Type-Options: nosniff');
  echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
  exit;
}
function ev_input(): array {
  if (strlen(file_get_contents('php://input') ?: '') > 8192) ev_response(413,['error'=>'request_too_large']);
  $value = json_decode(file_get_contents('php://input') ?: '{}', true);
  if (!is_array($value) || array_is_list($value) && $value !== []) ev_response(400,['error'=>'invalid_json']);
  return $value;
}
function ev_user(): ?array {
  if (!isset($_SESSION['uid'])) return null;
  $s = ev_db()->prepare('SELECT id,email,display_name,role,email_verified_at FROM users WHERE id=?');
  $s->execute([(int)$_SESSION['uid']]);
  return $s->fetch() ?: null;
}
function ev_require_user(): array {
  $u = ev_user(); if (!$u) ev_response(401,['error'=>'unauthorized']); return $u;
}
function ev_csrf(array $body): void {
  if (!isset($body['csrf']) || !is_string($body['csrf']) || !hash_equals($_SESSION['csrf'], $body['csrf'])) {
    ev_response(403,['error'=>'invalid_csrf']);
  }
}

/**
 * User-supplied Tesla charging connector types. Never inferred from Fleet API.
 * Stored outside webroot; separate from manually maintained user_vehicles.
 */
function ev_tesla_connector_path(int $uid): string {
  return '/home/g143326/.tesla-connector-preferences/user-'.$uid.'.json';
}
function ev_tesla_connector_read(int $uid): array {
  $path=ev_tesla_connector_path($uid);
  if (!is_file($path)) return [];
  $body=@file_get_contents($path);
  $item=is_string($body)?json_decode($body,true):null;
  $allowed=['CCS2','GB/T','NACS','Type2','CHAdeMO'];
  if (!is_array($item)||!isset($item['connectors'])||!is_array($item['connectors'])) return [];
  return array_values(array_intersect($allowed,array_filter($item['connectors'],'is_string')));
}
function ev_tesla_connector_write(int $uid,array $connectors): void {
  $dir='/home/g143326/.tesla-connector-preferences';
  if (!is_dir($dir) && !@mkdir($dir,0700) && !is_dir($dir)) throw new RuntimeException('Tesla connector preferences directory unavailable');
  $target=ev_tesla_connector_path($uid);
  $tmp=$target.'.'.bin2hex(random_bytes(6)).'.tmp';
  $data=json_encode(['connectors'=>$connectors],JSON_THROW_ON_ERROR);
  if (file_put_contents($tmp,$data,LOCK_EX)===false) throw new RuntimeException('Tesla connector preferences write failed');
  chmod($tmp,0600);
  if (!rename($tmp,$target)) { @unlink($tmp);throw new RuntimeException('Tesla connector preferences move failed'); }
}
