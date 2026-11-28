<?php
declare(strict_types=1);
require_once __DIR__.'/root-access.php';
const EV_WRAP_MODELS=['model3','model3-2024-base','model3-2024-performance','modely','modely-2025-base','modely-2025-premium','modely-2025-performance','modely-l','models-2021','models-2025-plaid','modelx-2021','cybertruck'];
const EV_WRAP_DIR='/home/g143326/.tesla-community-wraps';
function ev_wrap_list(): array {
  $items=[];
  foreach (EV_WRAP_MODELS as $model) {
    foreach (glob(EV_WRAP_DIR.'/'.$model.'/*.png') ?: [] as $path) {
      $name=basename($path);
      if (!preg_match('/^[A-Za-z0-9_-]{1,26}\.png$/D',$name) || !is_file($path)) continue;
      $items[]=['model'=>$model,'file'=>$name,'title'=>str_replace('_',' ',substr($name,0,-4)),
        'url'=>'/api/wraps.php?model='.rawurlencode($model).'&file='.rawurlencode($name)];
    }
  }
  return $items;
}
if (defined('EV_WRAP_LIBRARY_ONLY')) return;
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
try {
  $method=$_SERVER['REQUEST_METHOD']??'GET';
  if ($method==='GET') {
    if (isset($_GET['file'])) {
      $model=$_GET['model']??null; $name=$_GET['file'];
      if (!in_array($model,EV_WRAP_MODELS,true) || !is_string($name) || !preg_match('/^[A-Za-z0-9_-]{1,26}\.png$/D',$name)) ev_response(404,['error'=>'not_found']);
      $path=EV_WRAP_DIR.'/'.$model.'/'.$name;
      if (!is_file($path)) ev_response(404,['error'=>'not_found']);
      header('Content-Type: image/png');header('Cache-Control: public, max-age=300');
      header('Content-Disposition: inline; filename="'.$name.'"');
      header('Content-Length: '.filesize($path));
      readfile($path);exit;
    }
    ev_response(200,['items'=>ev_wrap_list()]);
  }
  if ($method!=='POST') ev_response(405,['error'=>'method_not_allowed']);
  ev_session();
  if (!ev_root_access(ev_user())) ev_response(403,['error'=>'forbidden']);
  ev_csrf($_POST);
  $model=$_POST['model']??null;
  if (!in_array($model,EV_WRAP_MODELS,true)) throw new InvalidArgumentException('Выберите модель.');
  $dir=EV_WRAP_DIR.'/'.$model;
  if (!is_dir($dir) && !@mkdir($dir,0700,true) && !is_dir($dir)) throw new RuntimeException('Storage unavailable');
  $uploads=$_FILES['skins']??[];
  $names=$uploads['name']??[];
  if (!is_array($names)||!count($names)||count($names)>10) throw new InvalidArgumentException('Выберите от 1 до 10 PNG.');
  $saved=0;$failed=0;
  foreach ($names as $index=>$original) {
    $tmp=$uploads['tmp_name'][$index]??'';
    if (!is_string($original)||!is_string($tmp)||($uploads['error'][$index]??-1)!==UPLOAD_ERR_OK||!is_uploaded_file($tmp)||filesize($tmp)>1000000) {++$failed;continue;}
    $info=@getimagesize($tmp);
    if (!$info||$info[2]!==IMAGETYPE_PNG||min($info[0],$info[1])<512||max($info[0],$info[1])>1024) {++$failed;continue;}
    $stem=trim(preg_replace('/[^A-Za-z0-9_-]+/','_',pathinfo($original,PATHINFO_FILENAME)),'_-');
    $stem=substr($stem?:'Wrap',0,26);
    $name=$stem.'.png';
    if (is_file($dir.'/'.$name)) $name=substr($stem,0,17).'_'.bin2hex(random_bytes(4)).'.png';
    if (!move_uploaded_file($tmp,$dir.'/'.$name)) {++$failed;continue;}
    chmod($dir.'/'.$name,0600);++$saved;
  }
  header('Location: /root?page=wraps&saved='.$saved.'&failed='.$failed,true,303);exit;
} catch(InvalidArgumentException $e) {
  header('Location: /root?page=wraps&error=invalid',true,303);exit;
} catch(Throwable $e) {
  error_log('EV wraps: '.$e->getMessage());ev_response(503,['error'=>'wraps_unavailable']);
}
