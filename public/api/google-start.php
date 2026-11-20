<?php
declare(strict_types=1);
require_once __DIR__.'/account-bootstrap.php';
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') ev_response(405,['error'=>'method_not_allowed']);
ev_session();
$returnToRoot=($_GET['return']??null)==='root';
$c=ev_config();
if (empty($c['GOOGLE_CLIENT_ID']) || empty($c['GOOGLE_CLIENT_SECRET'])) {
  header('Location: '.($returnToRoot?'/root':'/account.html').'?auth_error=not_configured',true,303); exit;
}
$state=bin2hex(random_bytes(32));
$nonce=bin2hex(random_bytes(32));
$_SESSION['google_oauth']=['state'=>$state,'nonce'=>$nonce,'created'=>time(),'returnToRoot'=>$returnToRoot];
$params=[
 'client_id'=>$c['GOOGLE_CLIENT_ID'],
 'redirect_uri'=>'https://tesla.bosh.kg/api/google-callback.php',
 'response_type'=>'code',
 'scope'=>'openid email profile',
 'state'=>$state,
 'nonce'=>$nonce,
 'prompt'=>'select_account'
];
header('Location: https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query($params,'','&',PHP_QUERY_RFC3986),true,302); exit;
