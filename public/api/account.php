<?php
declare(strict_types=1);
require_once __DIR__.'/account-bootstrap.php';
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET','POST'], true)) ev_response(405,['error'=>'method_not_allowed']);
const EV_VEHICLE_LIMIT = 3;
const EV_CONNECTORS = ['CCS2','GB/T','NACS','Type2','CHAdeMO'];
ev_session();
$action = $_GET['action'] ?? 'me';
try {
  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    if ($action === 'me') ev_response(200,['user'=>ev_user(),'csrf'=>$_SESSION['csrf']]);
    if ($action === 'map_preferences') {
      $user = ev_user();
      if (!$user) ev_response(200,['signedIn'=>false]);
      $db = ev_db();
      $query = $db->prepare('SELECT use_vehicle_connectors, filters_json FROM user_map_preferences WHERE user_id=?');
      $query->execute([$user['id']]);
      $row = $query->fetch();
      $automatic = (bool)($row['use_vehicle_connectors'] ?? false);
      $connectors = [];
      if ($automatic) {
        $query = $db->prepare('SELECT connector FROM user_vehicles WHERE user_id=?');
        $query->execute([$user['id']]);
        foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $value) {
          $connectors = array_merge($connectors, explode(',', (string)$value));
        }
        $connectors = array_values(array_intersect(EV_CONNECTORS, $connectors));
      }
      ev_response(200,[
        'signedIn'=>true, 'csrf'=>$_SESSION['csrf'], 'automatic'=>$automatic, 'connectors'=>$connectors,
        'filters'=>json_decode($row['filters_json'] ?? 'null', true)
      ]);
    }
    if ($action === 'vehicles') {
      $u=ev_require_user(); $s=ev_db()->prepare('SELECT id,make,model,model_year,connector,is_primary FROM user_vehicles WHERE user_id=? ORDER BY is_primary DESC,id DESC');
      $s->execute([$u['id']]);
      $vehicles=$s->fetchAll();
      foreach ($vehicles as &$vehicle) {
        $vehicle['connectors']=$vehicle['connector'] ? explode(',', $vehicle['connector']) : [];
        $vehicle['connector']=$vehicle['connectors'][0] ?? null;
      }
      unset($vehicle);
      ev_response(200,['vehicles'=>$vehicles,'limit'=>EV_VEHICLE_LIMIT]);
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
  if ($action === 'map_preferences') {
    $user = ev_require_user();
    if (isset($data['automatic']) && is_bool($data['automatic']) && !array_key_exists('filters',$data)) {
      $query = ev_db()->prepare('INSERT INTO user_map_preferences (user_id,use_vehicle_connectors) VALUES (?,?)
        ON DUPLICATE KEY UPDATE use_vehicle_connectors=VALUES(use_vehicle_connectors)');
      $query->execute([$user['id'], (int)$data['automatic']]);
    } elseif (!array_key_exists('automatic',$data) && isset($data['filters']) && is_array($data['filters']) && !array_is_list($data['filters'])) {
      $filters = $data['filters'];
      $clean = [];
      foreach (['spark','charge24','weway','evion'] as $key) {
        if (is_bool($filters[$key] ?? null)) $clean[$key] = $filters[$key];
      }
      foreach (['availability','current','power','station','portstatus','extras'] as $key) {
        if (is_string($filters[$key] ?? null) && strlen($filters[$key]) <= 24) $clean[$key] = $filters[$key];
      }
      $connectors = $filters['connectors'] ?? [];
      if (!is_array($connectors) || !array_is_list($connectors) || count($connectors) > 64) ev_response(422,['error'=>'invalid_filters']);
      $clean['connectors'] = array_values(array_unique(array_filter($connectors, static fn($value) => is_string($value) && $value !== '' && strlen($value) <= 64)));
      $query = ev_db()->prepare('INSERT INTO user_map_preferences (user_id,filters_json) VALUES (?,?)
        ON DUPLICATE KEY UPDATE filters_json=VALUES(filters_json)');
      $query->execute([$user['id'],json_encode($clean, JSON_THROW_ON_ERROR)]);
    } else {
      ev_response(422,['error'=>'invalid_preferences']);
    }
    ev_response(200,['ok'=>true]);
  }
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
    ev_sign_in((int)$u['id']);
    ev_response(200,['user'=>ev_user(),'csrf'=>$_SESSION['csrf']]);
  }
  if ($action === 'logout') {
    ev_reset_session();
    ev_response(200,['ok'=>true,'csrf'=>$_SESSION['csrf']]);
  }
  if ($action === 'vehicle_add' || $action === 'vehicle_update') {
    $u=ev_require_user();
    $editing=$action === 'vehicle_update';
    $id=$data['id']??null;
    $makeId=$data['make_id']??null; $modelId=$data['model_id']??null;
    $keepModel=$editing && $makeId===null && $modelId===null;
    $year=$data['year']??null;
    $legacyConnector=$data['connector']??null;
    $connectors=array_key_exists('connectors',$data) ? $data['connectors'] : ($legacyConnector===null ? [] : [$legacyConnector]);
    if (($editing && (!is_int($id) || $id<1))
        || (!$keepModel && (!is_int($makeId) || $makeId<1 || !is_int($modelId) || $modelId<1))
        || ($year!==null && (!is_int($year) || $year<1990 || $year>2100))
        || !is_array($connectors) || !array_is_list($connectors) || count($connectors)>count(EV_CONNECTORS)) {
      ev_response(422,['error'=>'invalid_vehicle','message'=>'Проверьте марку, модель, год выпуска и разъёмы.']);
    }
    foreach ($connectors as $connector) {
      if (!in_array($connector,EV_CONNECTORS,true)) {
        ev_response(422,['error'=>'invalid_vehicle','message'=>'Выберите разъёмы из списка.']);
      }
    }
    $connector=$connectors ? implode(',',array_unique($connectors)) : null;
    $db=ev_db();
    $db->beginTransaction();
    try {
      // Serialize writes from different sessions of the same user.
      $owner=$db->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');
      $owner->execute([$u['id']]);
      if (!$owner->fetchColumn()) {
        $db->rollBack();
        ev_response(401,['error'=>'unauthorized']);
      }
      if ($editing) {
        $existing=$db->prepare('SELECT make,model FROM user_vehicles WHERE id=? AND user_id=?');
        $existing->execute([$id,$u['id']]);
        $vehicle=$existing->fetch();
        if (!$vehicle) {
          $db->rollBack();
          ev_response(404,['error'=>'vehicle_not_found','message'=>'Автомобиль не найден.']);
        }
      } else {
        $count=$db->prepare('SELECT COUNT(*) FROM user_vehicles WHERE user_id=?');
        $count->execute([$u['id']]);
        if ((int)$count->fetchColumn()>=EV_VEHICLE_LIMIT) {
          $db->rollBack();
          ev_response(409,['error'=>'vehicle_limit','message'=>'Можно сохранить до '.EV_VEHICLE_LIMIT.' автомобилей.']);
        }
      }
      if (!$keepModel) {
        $catalog=$db->prepare('SELECT b.name AS make,m.name AS model
          FROM vehicle_models m JOIN vehicle_makes b ON b.id=m.make_id
          WHERE m.id=? AND b.id=? AND m.is_active=1 AND b.is_active=1');
        $catalog->execute([$modelId,$makeId]);
        $vehicle=$catalog->fetch();
        if (!$vehicle) {
          $db->rollBack();
          ev_response(422,['error'=>'invalid_vehicle_model','message'=>'Выбранная модель недоступна. Выберите модель из списка.']);
        }
      }
      if ($editing) {
        $s=$db->prepare('UPDATE user_vehicles SET make=?,model=?,model_year=?,connector=? WHERE id=? AND user_id=?');
        $s->execute([$vehicle['make'],$vehicle['model'],$year,$connector,$id,$u['id']]);
      } else {
        $s=$db->prepare('INSERT INTO user_vehicles(user_id,make,model,model_year,connector) VALUES(?,?,?,?,?)');
        $s->execute([$u['id'],$vehicle['make'],$vehicle['model'],$year,$connector]);
        $id=(int)$db->lastInsertId();
      }
      $db->commit();
    } catch (Throwable $e) {
      if ($db->inTransaction()) $db->rollBack();
      throw $e;
    }
    ev_response($editing?200:201,['id'=>$id]);
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
