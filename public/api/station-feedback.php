<?php
declare(strict_types=1);
require_once __DIR__.'/account-bootstrap.php';
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') ev_response(405, ['error'=>'method_not_allowed']);
ev_session();
try {
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
    $insert = $db->prepare("INSERT INTO station_feedback(user_id,station_hash,station_id,feedback_type,message) VALUES (?,UNHEX(SHA2(?,256)),?,?,?)");
    $insert->execute([$user['id'],$id,$id,$type,$message]);
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
