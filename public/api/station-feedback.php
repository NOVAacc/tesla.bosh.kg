<?php
declare(strict_types=1);
require_once __DIR__.'/account-bootstrap.php';
require_once __DIR__.'/station-cache.php';
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET','POST'], true)) ev_response(405, ['error'=>'method_not_allowed']);

function valid_feedback_station($id): bool {
  return is_string($id) && strlen($id) <= 1024
    && preg_match('/\A(?:spark|charge24|weway|evion):[^\p{Cc}]+\z/u', $id) === 1;
}

try {
  if ($method === 'GET') {
    $id = $_GET['stationId'] ?? null;
    $before = filter_var($_GET['before'] ?? PHP_INT_MAX, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if (!valid_feedback_station($id) || $before === false) ev_response(422, ['error'=>'invalid_feedback']);
    $query = ev_db()->prepare("SELECT f.id, f.feedback_type, f.message, f.created_at,
      SUBSTRING_INDEX(TRIM(u.display_name), ' ', 1) AS author_name
      FROM station_feedback f INNER JOIN users u ON u.id=f.user_id
      WHERE f.station_hash=UNHEX(SHA2(?,256)) AND f.is_public=1 AND f.status<>'dismissed' AND f.id<?
      ORDER BY f.id DESC LIMIT 11");
    $query->execute([$id,$before]);
    $items = $query->fetchAll();
    $hasMore = count($items) > 10;
    if ($hasMore) array_pop($items);
    ev_response(200, ['items'=>$items,'address'=>cachedStationAddress($id),'before'=>$hasMore ? (string)end($items)['id'] : null]);
  }
  ev_session();
  $user = ev_require_user();
  $body = ev_input();
  ev_csrf($body);
  $id = $body['stationId'] ?? null;
  $type = $body['type'] ?? null;
  $message = $body['message'] ?? null;
  if (!is_string($id) || strlen($id) > 1024 || !preg_match('/\\A(?:spark|charge24|weway|evion):[^\\p{Cc}]+\\z/u', $id)
      || !is_string($type) || !in_array($type, ['broken','incorrect_info','access','other'], true)
      || !is_string($message)) {
    ev_response(422, ['error'=>'invalid_feedback']);
  }
  $message = trim($message);
  if (mb_strlen($message) < 10 || mb_strlen($message) > 1000) ev_response(422, ['error'=>'invalid_message']);
  $db = ev_db();
  $db->beginTransaction();
  try {
    // Serialize feedback submissions by this user across browsers and tabs.
    $lock = $db->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');
    $lock->execute([$user['id']]);
    if (!$lock->fetchColumn()) {
      $db->rollBack();
      ev_response(401, ['error'=>'unauthorized']);
    }
    $recent = $db->prepare('SELECT COUNT(*) FROM station_feedback WHERE user_id=? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)');
    $recent->execute([$user['id']]);
    if ((int)$recent->fetchColumn() >= 1) {
      $db->rollBack();
      ev_response(429, ['error'=>'rate_limited','message'=>'Повторное сообщение можно отправить через 5 минут.']);
    }
    $insert = $db->prepare("INSERT INTO station_feedback(user_id,station_hash,station_id,feedback_type,message,is_public,station_address) VALUES (?,UNHEX(SHA2(?,256)),?,?,?,1,?)");
    $insert->execute([$user['id'],$id,$id,$type,$message,cachedStationAddress($id)]);
    $feedbackId = (int)$db->lastInsertId();
    $db->commit();
    ev_response(201, ['ok'=>true,'id'=>$feedbackId,'status'=>'new']);
  } catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    throw $error;
  }
} catch (Throwable $error) {
  error_log('EV station feedback failure: '.$error->getMessage());
  ev_response(503, ['error'=>'service_unavailable']);
}
