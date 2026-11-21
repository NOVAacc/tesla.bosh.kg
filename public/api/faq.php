<?php
declare(strict_types=1);
require_once __DIR__.'/root-access.php';
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

function faq_order_ids($value): array {
  if (!is_array($value) || !array_is_list($value) || count($value) > 100001) return [];
  $ids = [];
  foreach ($value as $rawId) {
    if (!is_string($rawId) && !is_int($rawId)) return [];
    $id = filter_var($rawId, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if ($id === false || isset($ids[$id])) return [];
    $ids[$id] = $id;
  }
  return array_values($ids);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try {
  $admin = $method !== 'GET' || isset($_GET['admin']);
  if ($admin) {
    ev_session();
    if (!ev_root_access(ev_user())) ev_response(403, ['error'=>'forbidden']);
  }
  $db=ev_db();
  if ($method === 'GET') {
    if ($admin) {
      $items=$db->query('SELECT id, category, question, answer, sort_order, is_published, updated_at FROM ev_faq ORDER BY sort_order, id')->fetchAll();
      ev_response(200, ['items'=>$items, 'csrf'=>$_SESSION['csrf']]);
    }
    header('Cache-Control: public, max-age=60');
    $items=$db->query('SELECT id, category, question, answer FROM ev_faq WHERE is_published=1 ORDER BY sort_order, id')->fetchAll();
    ev_response(200, ['items'=>$items]);
  }
  if (!in_array($method,['POST','PUT','DELETE','PATCH'],true)) ev_response(405,['error'=>'method_not_allowed']);
  $data=ev_input();
  ev_csrf($data);
  if ($method === 'PATCH') {
    $ids = faq_order_ids($data['ids'] ?? null);
    $previousIds = faq_order_ids($data['previous_ids'] ?? null);
    if (!$ids || !$previousIds || count($ids) !== count($previousIds)
        || array_diff($ids, $previousIds)) {
      ev_response(422, ['error'=>'invalid_order']);
    }

    $db->beginTransaction();
    try {
      $rows = $db->query('SELECT id, sort_order FROM ev_faq ORDER BY sort_order, id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
      $currentIds = array_map('intval', array_column($rows, 'id'));
      if ($currentIds !== $previousIds) {
        $db->rollBack();
        ev_response(409, ['error'=>'order_changed']);
      }
      $existingOrder = array_column($rows, 'sort_order', 'id');
      $step = min(10, intdiv(100000, max(1, count($ids) - 1)));
      $update = $db->prepare('UPDATE ev_faq SET sort_order=? WHERE id=?');
      foreach ($ids as $index => $itemId) {
        $order = $index * $step;
        if ((int)$existingOrder[$itemId] !== $order) $update->execute([$order, $itemId]);
      }
      $db->commit();
    } catch (Throwable $error) {
      if ($db->inTransaction()) $db->rollBack();
      throw $error;
    }
    ev_response(200, ['ok'=>true, 'step'=>$step]);
  }
  $id=filter_var($data['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if ($method==='DELETE') {
    if (!$id) ev_response(422,['error'=>'invalid_id']);
    $q=$db->prepare('DELETE FROM ev_faq WHERE id=?');
    $q->execute([$id]);
    ev_response(200,['ok'=>true]);
  }
  $categories=['service','adapters','charging','software','battery','home','buying','other'];
  $category=$data['category']??'';
  $question=trim((string)($data['question']??''));
  $answer=trim((string)($data['answer']??''));
  $order=filter_var($data['sort_order']??100,FILTER_VALIDATE_INT);
  $published=isset($data['is_published'])&&($data['is_published']===true||$data['is_published']===1||$data['is_published']==='1')?1:0;
  if (!in_array($category,$categories,true) || $question==='' || mb_strlen($question)>350 || $answer==='' || mb_strlen($answer)>10000 || $order===false || $order<0 || $order>100000) {
    ev_response(422,['error'=>'invalid_fields']);
  }
  if ($method==='PUT') {
    if (!$id) ev_response(422,['error'=>'invalid_id']);
    $q=$db->prepare('UPDATE ev_faq SET category=?,question=?,answer=?,sort_order=?,is_published=? WHERE id=?');
    $q->execute([$category,$question,$answer,$order,$published,$id]);
  } else {
    $q=$db->prepare('INSERT INTO ev_faq(category,question,answer,sort_order,is_published) VALUES(?,?,?,?,?)');
    $q->execute([$category,$question,$answer,$order,$published]);
    $id=(int)$db->lastInsertId();
  }
  ev_response(200,['ok'=>true,'id'=>$id]);
} catch (Throwable $e) {
  error_log('EV FAQ: '.$e->getMessage());
  ev_response(503,['error'=>'faq_unavailable']);
}
