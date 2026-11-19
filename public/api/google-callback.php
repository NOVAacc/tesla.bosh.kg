<?php
declare(strict_types=1);
require_once __DIR__.'/account-bootstrap.php';
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
function oauth_fail(string $reason): never {
  header('Location: /account.html?auth_error='.rawurlencode($reason),true,303);
  exit;
}
function oauth_post_json(string $url,array $params): array {
  if (!function_exists('curl_init')) oauth_fail('server_error');
  $ch=curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>http_build_query($params,'','&',PHP_QUERY_RFC3986),
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CONNECTTIMEOUT=>5,
    CURLOPT_TIMEOUT=>12,
    CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded','Accept: application/json'],
    CURLOPT_SSL_VERIFYPEER=>true,
    CURLOPT_SSL_VERIFYHOST=>2
  ]);
  $body=curl_exec($ch); $status=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  curl_close($ch);
  if (!is_string($body)||$status!==200||strlen($body)>20000) oauth_fail('google_unavailable');
  $data=json_decode($body,true);
  if (!is_array($data)) oauth_fail('google_unavailable');
  return $data;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') oauth_fail('invalid_response');
ev_session();
$pending=$_SESSION['google_oauth']??null;
unset($_SESSION['google_oauth']); // Single-use OAuth state; replay cannot sign in.
if (!is_array($pending)||time()-(int)($pending['created']??0)>600||time()<(int)($pending['created']??0)
    ||!isset($_GET['state'])||!is_string($_GET['state'])||!hash_equals((string)$pending['state'],$_GET['state'])) oauth_fail('expired');
if (isset($_GET['error'])) oauth_fail('cancelled');
$code=$_GET['code']??null;
if (!is_string($code)||$code===''||strlen($code)>4096) oauth_fail('invalid_response');
$c=ev_config();
if (empty($c['GOOGLE_CLIENT_ID'])||empty($c['GOOGLE_CLIENT_SECRET'])) oauth_fail('not_configured');
$token=oauth_post_json('https://oauth2.googleapis.com/token',[
  'client_id'=>$c['GOOGLE_CLIENT_ID'],
  'client_secret'=>$c['GOOGLE_CLIENT_SECRET'],
  'code'=>$code,
  'grant_type'=>'authorization_code',
  'redirect_uri'=>'https://tesla.bosh.kg/api/google-callback.php'
]);
$idToken=$token['id_token']??null;
if (!is_string($idToken)||strlen($idToken)>10000) oauth_fail('invalid_identity');
// Google tokeninfo verifies token signature and claims; never trust browser-supplied profile information.
// For higher throughput replace tokeninfo with locally verified Google JWKs (cached) or official SDK.
$ch=curl_init('https://oauth2.googleapis.com/tokeninfo?id_token='.rawurlencode($idToken));
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
$body=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
if(!is_string($body)||$status!==200||strlen($body)>20000) oauth_fail('invalid_identity');
$p=json_decode($body,true);
if (!is_array($p)
 || !in_array($p['iss']??'',['accounts.google.com','https://accounts.google.com'],true)
 || !hash_equals((string)$c['GOOGLE_CLIENT_ID'],(string)($p['aud']??''))
 || !hash_equals((string)$pending['nonce'],(string)($p['nonce']??''))
 || (int)($p['exp']??0)<=time()
 || !in_array($p['email_verified']??null,['true',true],true)
 || !isset($p['sub'])||!is_string($p['sub'])||strlen($p['sub'])>255
 || !filter_var($p['email']??null,FILTER_VALIDATE_EMAIL)
 || strlen($p['email'])>254) oauth_fail('invalid_identity');
$email=strtolower((string)$p['email']);
$sub=(string)$p['sub'];
$name=trim((string)($p['name']??''));
if (mb_strlen($name)>120) $name=mb_substr($name,0,120);
try {
  $db=ev_db();
  $db->beginTransaction();
  $s=$db->prepare('SELECT u.id FROM user_google_identities g JOIN users u ON u.id=g.user_id WHERE g.google_sub=? FOR UPDATE');
  $s->execute([$sub]);$existingId=$s->fetchColumn();
  if ($existingId) {
    $userId=(int)$existingId;
  } else {
    // Do not auto-link Google to an existing password account based on email alone.
    $s=$db->prepare('SELECT id FROM users WHERE email=? LIMIT 1 FOR UPDATE');
    $s->execute([$email]);
    if ($s->fetchColumn()) { $db->rollBack();oauth_fail('existing_email'); }
    $passHash=password_hash(bin2hex(random_bytes(48)),PASSWORD_DEFAULT);
    $s=$db->prepare('INSERT INTO users(email,password_hash,display_name,role,email_verified_at) VALUES(?,?,?,\'user\',NOW())');
    $s->execute([$email,$passHash,$name]);$userId=(int)$db->lastInsertId();
    $s=$db->prepare('INSERT INTO user_google_identities(user_id,google_sub) VALUES(?,?)');
    $s->execute([$userId,$sub]);
  }
  $db->commit();
  session_regenerate_id(true);
  $_SESSION['uid']=$userId;
  $_SESSION['csrf']=bin2hex(random_bytes(32));
  header('Location: /account.html',true,303);exit;
} catch (Throwable $e) {
  if(isset($db)&&$db->inTransaction())$db->rollBack();
  error_log('Google OAuth user transaction failed: '.$e->getMessage());
  oauth_fail('server_error');
}
