<?php
declare(strict_types=1);
require_once __DIR__.'/account-bootstrap.php';
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') ev_response(405,['error'=>'method_not_allowed']);
if (strlen($_SERVER['CONTENT_LENGTH'] ?? '0') > 6000) ev_response(413,['error'=>'too_large']);
$body=json_decode(file_get_contents('php://input') ?: '{}',true);
if (!is_array($body)) ev_response(400,['error'=>'invalid_json']);
$visitor=$body['visitor'] ?? '';
$events=$body['events'] ?? [];
$allowed=['map_open','station_open','filter_apply','favorite_add','favorite_remove','list_open','faq_open','services_open','route_open'];
if (!is_string($visitor) || !preg_match('/^[a-f0-9]{32}$/D',$visitor) || !is_array($events) || count($events)>12) ev_response(422,['error'=>'invalid_payload']);
$parsed=[];
foreach ($events as $event) {
  if (!is_array($event) || !in_array($event['name'] ?? null,$allowed,true)) continue;
  $label=$event['label'] ?? '';
  if (!is_string($label) || strlen($label)>80 || preg_match('/[[:cntrl:]]/',$label)) $label='';
  $parsed[]=[$event['name'],$label];
}
if (!$parsed) ev_response(200,['ok'=>true]);
try {
  $db=ev_db();
  $db->exec("CREATE TABLE IF NOT EXISTS map_analytics_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    visitor_hash BINARY(32) NOT NULL,
    event_name VARCHAR(32) NOT NULL,
    event_label VARCHAR(80) NOT NULL DEFAULT '',
    occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_time (occurred_at),
    KEY idx_event_time (event_name,occurred_at),
    KEY idx_visitor_time (visitor_hash,occurred_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  // Daily visitor pseudonym rotation limits long-term tracking.
  $visitorHash=hash('sha256',gmdate('Y-m-d').':'.$visitor,true);
  $statement=$db->prepare('INSERT INTO map_analytics_events (visitor_hash,event_name,event_label) VALUES (?,?,?)');
  foreach($parsed as [$name,$label])$statement->execute([$visitorHash,$name,$label]);
  ev_response(200,['ok'=>true]);
} catch(Throwable $error) {
  error_log('EV analytics ingestion: '.$error->getMessage());
  ev_response(503,['error'=>'analytics_unavailable']);
}
