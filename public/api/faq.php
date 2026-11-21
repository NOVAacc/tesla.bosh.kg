<?php
declare(strict_types=1);
require_once __DIR__.'/root-access.php';
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

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
  if (!in_array($method,['POST','PUT','DELETE'],true)) ev_response(405,['error'=>'method_not_allowed']);
  $data=ev_input();
  ev_csrf($data);
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
