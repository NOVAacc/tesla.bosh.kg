<?php
declare(strict_types=1);
require_once __DIR__.'/root-access.php';
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') ev_response(405,['error'=>'method_not_allowed']);
ev_session();
try {
  if (!ev_root_access(ev_user())) ev_response(403,['error'=>'forbidden']);
  session_write_close();
  $days=filter_var($_GET['days'] ?? 30,FILTER_VALIDATE_INT);
  if (!in_array($days,[1,7,30,90],true)) ev_response(422,['error'=>'invalid_period']);
  $db=ev_db();
  $today=(new DateTimeImmutable('today'))->modify('-'.($days-1).' days')->format('Y-m-d 00:00:00');
  $counts=['visitors'=>0,'engaged'=>0,'events'=>0,'registered'=>0,'favorite_users'=>0,'favorite_stations'=>0,'favorite_links'=>0];
  $counts['registered']=(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
  $counts['favorite_users']=(int)$db->query('SELECT COUNT(DISTINCT user_id) FROM user_station_favorites')->fetchColumn();
  $counts['favorite_stations']=(int)$db->query('SELECT COUNT(DISTINCT station_hash) FROM user_station_favorites')->fetchColumn();
  $counts['favorite_links']=(int)$db->query('SELECT COUNT(*) FROM user_station_favorites')->fetchColumn();
  $topFavorites=$db->query('SELECT station_id,COUNT(*) AS total FROM user_station_favorites GROUP BY station_hash,station_id ORDER BY total DESC LIMIT 10')->fetchAll();
  try{
    $statement=$db->prepare('SELECT COUNT(DISTINCT visitor_hash) visitors,COUNT(*) events, COUNT(DISTINCT CASE WHEN event_name <> \'map_open\' THEN visitor_hash END) engaged FROM map_analytics_events WHERE occurred_at >= ?');
    $statement->execute([$today]);
    $summary=$statement->fetch();
    $counts['visitors']=(int)$summary['visitors'];
    $counts['events']=(int)$summary['events'];
    $counts['engaged']=(int)$summary['engaged'];
    $statement=$db->prepare('SELECT event_name,COUNT(*) AS total,COUNT(DISTINCT visitor_hash) AS users FROM map_analytics_events WHERE occurred_at>=? GROUP BY event_name ORDER BY total DESC');
    $statement->execute([$today]);$events=$statement->fetchAll();
    $statement=$db->prepare("SELECT event_label,COUNT(*) AS total FROM map_analytics_events WHERE event_name='filter_apply' AND occurred_at>=? GROUP BY event_label ORDER BY total DESC LIMIT 12");
    $statement->execute([$today]);$filters=$statement->fetchAll();
    $statement=$db->prepare("SELECT DATE(occurred_at) AS day,COUNT(DISTINCT visitor_hash) AS visitors,COUNT(DISTINCT CASE WHEN event_name <> 'map_open' THEN visitor_hash END) AS engaged FROM map_analytics_events WHERE occurred_at>=? GROUP BY DATE(occurred_at) ORDER BY day");
    $statement->execute([$today]);$daily=$statement->fetchAll();
  }catch(PDOException $e){
    if($e->getCode()==='42S02'||(int)($e->errorInfo[1]??0)===1146){
      $events=[];$filters=[];$daily=[];
    } else throw $e;
  }
  ev_response(200,['days'=>$days,'counts'=>$counts,'events'=>$events,'filters'=>$filters,'daily'=>$daily,'topFavorites'=>$topFavorites]);
}catch(Throwable $e){
  error_log('EV analytics report: '.$e->getMessage());
  ev_response(503,['error'=>'analytics_unavailable']);
}
