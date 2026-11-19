<?php
declare(strict_types=1);
require_once __DIR__.'/account-bootstrap.php';
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET','POST'], true)) ev_response(405,['error'=>'method_not_allowed']);
const EV_VEHICLE_LIMIT = 3;
ev_session();
$action = $_GET['action'] ?? 'me';
try {
  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    if ($action === 'me') ev_response(200,['user'=>ev_user(),'csrf'=>$_SESSION['csrf']]);
    if ($action === 'vehicles') {
      $u=ev_require_user(); $s=ev_db()->prepare('SELECT id,make,model,model_year,connector,is_primary FROM user_vehicles WHERE user_id=? ORDER BY is_primary DESC,id DESC');
      $s->execute([$u['id']]); ev_response(200,['vehicles'=>$s->fetchAll(),'limit'=>EV_VEHICLE_LIMIT]);
    }
    if ($action === 'vehicle_catalog') {
      $s=ev_db()->query('SELECT b.id AS make_id,b.name AS make,m.id AS model_id,m.name AS model
        FROM vehicle_makes b JOIN vehicle_models m ON m.make_id=b.id
        WHERE b.is_active=1 AND m.is_active=1 ORDER BY b.name,m.name');
      $makes=[];
      foreach ($s as $row) {
        $id=(int)$row['make_id'];
        $makes[$id] ??= ['id'=>$id,'name'=>$row['make'],'models'=>[]];
        $makes[$id]['models'][]=['id'=>(int)$row['model_id'],'name'=>$row['model']];
      }
      ev_response(200,['makes'=>array_values($makes)]);
    }
    if ($action === 'categories') ev_response(200,['categories'=>ev_db()->query('SELECT id,slug,name_ru,name_ky FROM business_categories ORDER BY id')->fetchAll()]);
    if ($action === 'businesses') {
      $s=ev_db()->query("SELECT b.id,b.name,b.description,b.phone,b.whatsapp,b.address,b.latitude,b.longitude,c.slug AS category FROM businesses b JOIN business_categories c ON c.id=b.category_id WHERE b.status='approved' ORDER BY b.id DESC LIMIT 500");
      ev_response(200,['businesses'=>$s->fetchAll()]);
    }
    ev_response(404,['error'=>'unknown_action']);
  }
  $data=ev_input(); ev_csrf($data);
  if ($action === 'register') {
    $email=strtolower(trim((string)($data['email']??''))); $name=trim((string)($data['name']??'')); $password=$data['password']??null;
    if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>254 || mb_strlen($name)>120 || !is_string($password) || strlen($password)<12 || strlen($password)>1024) ev_response(422,['error'=>'invalid_registration']);
    // Email verification is required before enabling public account creation.
    ev_response(503,['error'=>'registration_not_enabled','message'=>'Регистрация станет доступна после подключения проверки email']);
  }
  if ($action === 'login') {
    $email=strtolower(trim((string)($data['email']??''))); $password=$data['password']??'';
    if (!filter_var($email,FILTER_VALIDATE_EMAIL) || !is_string($password)) ev_response(422,['error'=>'invalid_credentials']);
    $db=ev_db(); $emailHash=hash('sha256',$email); $ipHash=hash('sha256',$_SERVER['REMOTE_ADDR']??'unknown');
    $attempts=$db->prepare("SELECT COUNT(*) FROM login_attempts WHERE (email_hash=? OR ip_hash=?) AND attempted_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)");
    $attempts->execute([$emailHash,$ipHash]);
    if ((int)$attempts->fetchColumn()>=8) ev_response(429,['error'=>'try_later']);
    $s=$db->prepare('SELECT id,password_hash,email_verified_at FROM users WHERE email=?');
    $s->execute([$email]); $u=$s->fetch();
    if (!$u || !password_verify($password,$u['password_hash'])) {
      $log=$db->prepare('INSERT INTO login_attempts(email_hash,ip_hash) VALUES (?,?)'); $log->execute([$emailHash,$ipHash]);
      ev_response(401,['error'=>'invalid_credentials']);
    }
    if (!$u['email_verified_at']) ev_response(403,['error'=>'email_not_verified']);
    session_regenerate_id(true); $_SESSION['uid']=(int)$u['id']; $_SESSION['csrf']=bin2hex(random_bytes(32));
    ev_response(200,['user'=>ev_user(),'csrf'=>$_SESSION['csrf']]);
  }
  if ($action === 'logout') {
    $_SESSION=[]; session_regenerate_id(true); $_SESSION['csrf']=bin2hex(random_bytes(32));
    ev_response(200,['ok'=>true,'csrf'=>$_SESSION['csrf']]);
  }
  if ($action === 'vehicle_add') {
    $u=ev_require_user();
    $makeId=$data['make_id']??null; $modelId=$data['model_id']??null;
    $year=$data['year']??null; $connector=$data['connector']??null;
    if (!is_int($makeId) || $makeId<1 || !is_int($modelId) || $modelId<1
        || ($year!==null && (!is_int($year) || $year<1990 || $year>2100))
        || ($connector!==null && !in_array($connector,['CCS2','GB/T','NACS','Type2','CHAdeMO'],true))) {
      ev_response(422,['error'=>'invalid_vehicle','message'=>'Проверьте марку, модель, год выпуска и разъём.']);
    }
    $db=ev_db();
    $db->beginTransaction();
    try {
      // Serialize additions from different sessions of the same user.
      $owner=$db->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');
      $owner->execute([$u['id']]);
      if (!$owner->fetchColumn()) {
        $db->rollBack();
        ev_response(401,['error'=>'unauthorized']);
      }
      $count=$db->prepare('SELECT COUNT(*) FROM user_vehicles WHERE user_id=?');
      $count->execute([$u['id']]);
      if ((int)$count->fetchColumn()>=EV_VEHICLE_LIMIT) {
        $db->rollBack();
        ev_response(409,['error'=>'vehicle_limit','message'=>'Можно сохранить до '.EV_VEHICLE_LIMIT.' автомобилей.']);
      }
      $catalog=$db->prepare('SELECT b.name AS make,m.name AS model
        FROM vehicle_models m JOIN vehicle_makes b ON b.id=m.make_id
        WHERE m.id=? AND b.id=? AND m.is_active=1 AND b.is_active=1');
      $catalog->execute([$modelId,$makeId]);
      $vehicle=$catalog->fetch();
      if (!$vehicle) {
        $db->rollBack();
        ev_response(422,['error'=>'invalid_vehicle_model','message'=>'Выбранная модель недоступна. Выберите модель из списка.']);
      }
      $s=$db->prepare('INSERT INTO user_vehicles(user_id,make,model,model_year,connector) VALUES(?,?,?,?,?)');
      $s->execute([$u['id'],$vehicle['make'],$vehicle['model'],$year,$connector]);
      $id=(int)$db->lastInsertId();
      $db->commit();
    } catch (Throwable $e) {
      if ($db->inTransaction()) $db->rollBack();
      throw $e;
    }
    ev_response(201,['id'=>$id]);
  }
  if ($action === 'business_apply') {
    $u=ev_require_user(); $name=trim((string)($data['name']??'')); $cat=$data['category_id']??null;
    if ($name==='' || mb_strlen($name)>150 || !is_int($cat) || $cat<1) ev_response(422,['error'=>'invalid_business']);
    $s=ev_db()->prepare("INSERT INTO businesses(owner_user_id,category_id,name,status) VALUES(?,?,?,'pending')");
    $s->execute([$u['id'],$cat,$name]); ev_response(201,['id'=>(int)ev_db()->lastInsertId(),'status'=>'pending']);
  }
  ev_response(404,['error'=>'unknown_action']);
} catch (PDOException $e) {
  error_log('EV account database error: '.$e->getCode());
  ev_response(503,['error'=>'service_unavailable']);
} catch (Throwable $e) {
  error_log('EV account error: '.$e->getMessage());
  ev_response(503,['error'=>'service_unavailable']);
}
