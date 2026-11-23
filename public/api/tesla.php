<?php
declare(strict_types=1);
require_once __DIR__.'/account-bootstrap.php';
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
const TESLA_CALLBACK = 'https://tesla.bosh.kg/auth/tesla/callback';
const TESLA_AUDIENCE = 'https://fleet-api.prd.na.vn.cloud.tesla.com';
function tesla_secret_config(): array {
  $c=ev_config();
  foreach (['TESLA_CLIENT_ID','TESLA_CLIENT_SECRET','TESLA_TOKEN_KEY'] as $k) {
    if (empty($c[$k])) ev_response(503,['error'=>'tesla_not_configured']);
  }
  if (!preg_match('/^[0-9a-fA-F]{64}$/D',$c['TESLA_TOKEN_KEY'])) ev_response(503,['error'=>'tesla_key_invalid']);
  return $c;
}
function tesla_path(int $user): string {
  $dir='/home/g143326/.tesla-oauth-tokens';
  if (!is_dir($dir) && !@mkdir($dir,0700) && !is_dir($dir)) throw new RuntimeException('Tesla token directory unavailable');
  if (!is_writable($dir)) throw new RuntimeException('Tesla token directory unwritable');
  return $dir.'/user-'.$user.'.json';
}
function tesla_store(int $user,array $value,array $cfg): void {
  $key=hex2bin($cfg['TESLA_TOKEN_KEY']);
  $iv=random_bytes(12); $tag='';
  $json=json_encode($value,JSON_THROW_ON_ERROR);
  $encrypted=openssl_encrypt($json,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'tesla-oauth-v1');
  if (!is_string($encrypted)) throw new RuntimeException('Tesla token encryption failed');
  $payload=json_encode(['iv'=>base64_encode($iv),'tag'=>base64_encode($tag),'data'=>base64_encode($encrypted)],JSON_THROW_ON_ERROR);
  $dest=tesla_path($user); $temp=$dest.'.'.bin2hex(random_bytes(8)).'.tmp';
  if (file_put_contents($temp,$payload,LOCK_EX)===false) throw new RuntimeException('Tesla token write failed');
  chmod($temp,0600);
  if (!rename($temp,$dest)) { @unlink($temp);throw new RuntimeException('Tesla token move failed'); }
}
function tesla_load(int $user,array $cfg): ?array {
  $path=tesla_path($user);
  if (!is_file($path)) return null;
  $s=file_get_contents($path);
  $item=json_decode($s?:'',true);
  if (!is_array($item)) throw new RuntimeException('Invalid Tesla token storage');
  $iv=base64_decode($item['iv']??'',true);
  $tag=base64_decode($item['tag']??'',true);
  $data=base64_decode($item['data']??'',true);
  if ($iv===false || $tag===false || $data===false) throw new RuntimeException('Invalid Tesla token data');
  $plain=openssl_decrypt($data,'aes-256-gcm',hex2bin($cfg['TESLA_TOKEN_KEY']),OPENSSL_RAW_DATA,$iv,$tag,'tesla-oauth-v1');
  if (!is_string($plain)) throw new RuntimeException('Tesla token decrypt failed');
  $value=json_decode($plain,true,512,JSON_THROW_ON_ERROR);
  return is_array($value)?$value:null;
}
function tesla_request(string $url,array $fields,?string $bearer=null): array {
  $ch=curl_init($url);
  $headers=['Accept: application/json'];
  if ($bearer!==null) $headers[]='Authorization: Bearer '.$bearer;
  if ($fields!==[]) $headers[]='Content-Type: application/x-www-form-urlencoded';
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>6,CURLOPT_TIMEOUT=>18,
    CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
    CURLOPT_HTTPHEADER=>$headers,CURLOPT_FOLLOWLOCATION=>false
  ]);
  if ($fields!==[]) {
    curl_setopt($ch,CURLOPT_POST,true);
    curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($fields,'','&',PHP_QUERY_RFC3986));
  }
  $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  if (!is_string($body) || strlen($body)>200000) throw new RuntimeException('Tesla API unavailable');
  $json=json_decode($body,true);
  return [$status,is_array($json)?$json:[]];
}
function tesla_redirect(string $code): never {
  header('Location: /account.html?tesla_result='.rawurlencode($code),true,303); exit;
}
try {
  if (($_SERVER['REQUEST_METHOD']??'')!=='GET') ev_response(405,['error'=>'method_not_allowed']);
  ev_session();
  $u=ev_user();
  if (!$u) {
    if (($_GET['action']??'')==='start') {header('Location: /account.html',true,303);exit;}
    ev_response(401,['error'=>'unauthorized']);
  }
  $action=$_GET['action']??'status';
  $cfg=tesla_secret_config();
  if ($action==='start') {
    $state=bin2hex(random_bytes(32));
    $_SESSION['tesla_oauth']=['state'=>$state,'uid'=>(int)$u['id'],'created'=>time()];
    $params=[
      'response_type'=>'code','client_id'=>$cfg['TESLA_CLIENT_ID'],
      'redirect_uri'=>TESLA_CALLBACK,
      'scope'=>'openid offline_access vehicle_device_data',
      'state'=>$state
    ];
    header('Location: https://auth.tesla.com/oauth2/v3/authorize?'.http_build_query($params,'','&',PHP_QUERY_RFC3986),true,302);
    exit;
  }
  if ($action==='callback' || str_starts_with($_SERVER['REQUEST_URI']??'','/auth/tesla/callback')) {
    $pending=$_SESSION['tesla_oauth']??null; unset($_SESSION['tesla_oauth']);
    if (!is_array($pending)||!is_string($_GET['state']??null)
        ||!hash_equals((string)($pending['state']??''),$_GET['state'])
        ||(int)$pending['uid']!==(int)$u['id']
        ||time()-(int)$pending['created']>600 ||time()<(int)$pending['created']) tesla_redirect('expired');
    if (isset($_GET['error'])) tesla_redirect('cancelled');
    $code=$_GET['code']??null;
    if (!is_string($code)||!$code||strlen($code)>4096) tesla_redirect('invalid_code');
    [$status,$tokens]=tesla_request('https://fleet-auth.prd.vn.cloud.tesla.com/oauth2/v3/token',[
      'grant_type'=>'authorization_code','client_id'=>$cfg['TESLA_CLIENT_ID'],
      'client_secret'=>$cfg['TESLA_CLIENT_SECRET'],'code'=>$code,
      'audience'=>TESLA_AUDIENCE,'redirect_uri'=>TESLA_CALLBACK
    ]);
    if ($status!==200||!is_string($tokens['access_token']??null)) tesla_redirect('token_failed');
    $tokens['expires_at']=time()+max(60,(int)($tokens['expires_in']??3600));
    tesla_store((int)$u['id'],$tokens,$cfg);
    tesla_redirect('connected');
  }
  if ($action==='status') {
    $tokens=tesla_load((int)$u['id'],$cfg);
    if (!$tokens) ev_response(200,['connected'=>false]);
    if (($tokens['expires_at']??0)<time()+120) {
      if (empty($tokens['refresh_token'])) ev_response(200,['connected'=>false,'reason'=>'reauthorize']);
      [$status,$next]=tesla_request('https://fleet-auth.prd.vn.cloud.tesla.com/oauth2/v3/token',[
        'grant_type'=>'refresh_token','client_id'=>$cfg['TESLA_CLIENT_ID'],
        'client_secret'=>$cfg['TESLA_CLIENT_SECRET'],
        'refresh_token'=>$tokens['refresh_token'],'audience'=>TESLA_AUDIENCE
      ]);
      if ($status!==200 || empty($next['access_token'])) ev_response(200,['connected'=>false,'reason'=>'reauthorize']);
      $tokens=array_merge($tokens,$next);
      $tokens['expires_at']=time()+max(60,(int)($next['expires_in']??3600));
      tesla_store((int)$u['id'],$tokens,$cfg);
    }
    [$status,$body]=tesla_request(TESLA_AUDIENCE.'/api/1/vehicles',[],(string)$tokens['access_token']);
    if ($status!==200) ev_response(200,['connected'=>true,'vehicles'=>[],'api_status'=>$status]);
    $vehicles=[];
    foreach (array_slice($body['response']??[],0,3) as $vehicle) {
      if (!is_array($vehicle)) continue;
      $vin=(string)($vehicle['vin']??'');
      $entry=['display_name'=>(string)($vehicle['display_name']??'Tesla'),
        'state'=>(string)($vehicle['state']??'unknown'),'vin_suffix'=>substr($vin,-4)];
      if (preg_match('/^[A-HJ-NPR-Z0-9]{17}$/D',$vin)) {
        // Tesla's cached endpoint never wakes the car. Responses may be stale.
        [$cachedStatus,$cached]=tesla_request(TESLA_AUDIENCE.'/api/1/vehicles/'.rawurlencode($vin).'/vehicle_data_cached',[],(string)$tokens['access_token']);
        if ($cachedStatus===200) {
          $details=$cached['response']??[];
          $charge=$details['charge_state']??[];
          if (is_array($charge)) {
            foreach (['battery_level','battery_range','charging_state','charge_limit_soc','charge_rate','time_to_full_charge'] as $key) {
              if (isset($charge[$key]) && (is_numeric($charge[$key])||is_string($charge[$key]))) $entry[$key]=$charge[$key];
            }
            if (isset($charge['timestamp']) && is_numeric($charge['timestamp'])) $entry['charge_timestamp']=(int)$charge['timestamp'];
          }
        } else {
          $entry['cached_status']=$cachedStatus;
        }
      }
      $vehicles[]=$entry;
    }
    ev_response(200,['connected'=>true,'vehicles'=>$vehicles]);
  }
  ev_response(404,['error'=>'unknown_action']);
} catch (Throwable $e) {
  error_log('Tesla OAuth: '.$e->getMessage());
  ev_response(503,['error'=>'tesla_unavailable']);
}
