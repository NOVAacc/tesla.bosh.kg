<?php
declare(strict_types=1);
require_once __DIR__.'/root-access.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method,['GET','POST'],true)) ev_response(405,['error'=>'method_not_allowed']);

try {
  ev_session();
  if (!ev_root_access(ev_user())) ev_response(403,['error'=>'forbidden']);
  $db = ev_db();
  if ($method === 'GET') {
    $before = filter_var($_GET['before'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: null;
    $query = $db->prepare("SELECT b.id,b.name,b.address,b.description,b.phone,b.whatsapp,b.latitude,b.longitude,c.name_ru AS category
      FROM businesses b JOIN business_categories c ON c.id=b.category_id
      WHERE b.status='pending'".($before ? ' AND b.id < ?' : '')." ORDER BY b.id DESC LIMIT 31");
    $query->execute($before ? [$before] : []);
    $items = $query->fetchAll();
    $more = count($items)>30;
    if ($more) array_pop($items);
    ev_response(200,['items'=>$items,'next'=>$more ? end($items)['id'] : null,'csrf'=>$_SESSION['csrf']]);
  }
  $data = ev_input();
  ev_csrf($data);
  $id = $data['id'] ?? null;
  $decision = $data['decision'] ?? '';
  $note = is_string($data['note'] ?? null) ? trim($data['note']) : '';
  if (!is_int($id) || $id<1 || !in_array($decision,['approved','rejected'],true)
    || ($decision==='rejected' && ($note==='' || mb_strlen($note)>500))) {
    ev_response(422,['error'=>'invalid_review']);
  }
  $db->beginTransaction();
  $query = $db->prepare("UPDATE businesses SET status=? WHERE id=? AND status='pending'"
    .($decision==='approved' ? " AND latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180 AND address IS NOT NULL" : ''));
  $query->execute([$decision,$id]);
  if (!$query->rowCount()) {
    $db->rollBack();
    ev_response(409,['error'=>'business_changed']);
  }
  if ($decision==='rejected') {
    $query = $db->prepare('INSERT INTO business_review_notes(business_id,note) VALUES (?,?) ON DUPLICATE KEY UPDATE note=VALUES(note)');
    $query->execute([$id,$note]);
  }
  $db->commit();
  ev_response(200,['status'=>$decision]);
} catch (Throwable $error) {
  if (isset($db) && $db->inTransaction()) $db->rollBack();
  error_log('EV business review: '.$error->getMessage());
  ev_response(503,['error'=>'service_unavailable']);
}
