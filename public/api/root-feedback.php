<?php
declare(strict_types=1);
require_once __DIR__.'/root-access.php';
require_once __DIR__.'/station-cache.php';
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('X-Robots-Tag: noindex, nofollow');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') ev_response(405, ['error'=>'method_not_allowed']);
ev_session();
try {
  if (!ev_root_access(ev_user())) ev_response(403, ['error'=>'forbidden']);
  session_write_close();
  $page = filter_var($_GET['page'] ?? '1', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>1000000]]);
  if ($page === false) ev_response(422, ['error'=>'invalid_page']);
  $perPage = 30;
  $db = ev_db();
  // Query the table directly: SHOW TABLES may be restricted by hosting permissions.
  try {
    $total = (int)$db->query('SELECT COUNT(*) FROM station_feedback')->fetchColumn();
  } catch (PDOException $error) {
    if ($error->getCode() === '42S02' || (int)($error->errorInfo[1] ?? 0) === 1146) {
      ev_response(503, ['error'=>'feedback_storage_unavailable']);
    }
    throw $error;
  }
  $offset = ($page - 1) * $perPage;
  $q = $db->prepare('SELECT f.id,f.station_id,f.feedback_type,f.message,f.status,f.created_at,f.is_public,f.station_address,
      u.email, u.display_name
      FROM station_feedback f
      INNER JOIN users u ON u.id=f.user_id
      ORDER BY f.created_at DESC, f.id DESC LIMIT :take OFFSET :skip');
  $q->bindValue(':take', $perPage, PDO::PARAM_INT);
  $q->bindValue(':skip', $offset, PDO::PARAM_INT);
  $q->execute();
  $items = $q->fetchAll();
  foreach ($items as &$item) $item['station_address'] ??= cachedStationAddress($item['station_id']);
  unset($item);
  ev_response(200, ['items'=>$items, 'total'=>$total, 'page'=>$page, 'perPage'=>$perPage]);
} catch (Throwable $error) {
  error_log('EV root feedback error: '.$error->getMessage());
  ev_response(503, ['error'=>'service_unavailable']);
}
