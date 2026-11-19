<?php
declare(strict_types=1);
// Secret settings are read from PHP process environment, or a private file OUTSIDE webroot.
// To configure a private env file use EV_PRIVATE_ENV=/absolute/path/to/.env in server environment.
// GitHub Actions secrets are NOT automatically available to PHP at request time.
function ev_config(): array {
  $config = [];
  $file = getenv('EV_PRIVATE_ENV');
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
function ev_session(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    // TLS may terminate at a trusted reverse proxy; deployed site is HTTPS.
  }
  ini_set('session.use_strict_mode','1');
  ini_set('session.use_only_cookies','1');
  session_name('ev_session');
  session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
  session_start();
  if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
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
