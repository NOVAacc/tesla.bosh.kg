<?php
declare(strict_types=1);
require_once __DIR__.'/root-access.php';
const EV_WRAP_MODELS=['model3','model3-2024-base','model3-2024-performance','modely','modely-2025-base','modely-2025-premium','modely-2025-performance','modely-l','models-2021','models-2025-plaid','modelx-2021','cybertruck'];
const EV_WRAP_DIR='/home/g143326/.tesla-community-wraps';
const EV_WRAP_LIMIT=10;
// Alpha masks sampled from Tesla's official custom-wrap templates at 64 columns.
const EV_WRAP_MASKS='{"cybertruck":{"cols":64,"rows":48,"mask":"AAf////gAAAAB/////AAAIAP////8AAAwA/////4AP/gH/////wB/+A//////gP/////////////////////////////////H/////////8D8B///////wB+AAAB///+DB/AAAH//4B8P/gAH//Af3x/v///4AB/fH+H//AAAH98/4DAAAAAf3z/AAAAAAB/fP8AAAAAAH98/wAAAAAAf33/AAAAAAB/ff8AAAAAAH99/wAAAAAAf33/AAAAAAB/ff8AAAAAAH99/wAAAAAAf33/AAAAAAB/ff8AAAAAAH98/wAAAAAAf3z/AAAAAAB/fP8AAAAAAH98/4DAAAAAf3x/h//wAAB/fH+/v//wAH98P/gAH//AfwAfwAAB//+AAP4AAAP///8D8B///////x//////////////////////////////////////////4B/////+A//gH/////wB/8AP////+AD/gA/////wAAAAB/////AAAAAH////4AAA"},"modelx-2021":{"cols":64,"rows":64,"mask":"AAAAH/gAAAAAAAfwD+AAAAAAD///4AAAAAAN///gAAAAAA///+AAAAAAD/7/8AAAAAAcD/B4AAAAADgf+BgAAAAAAD/8AAAAAABAf/4AAAAAAED//wIAAAAAwP//AgAAAAHA//8DAAAAAcD//wMAAAABwP//AwAAAAHA//8HgAAAA8H//4eAAAADz8AD88AAAA/fAAD7+AAAH4EAAIH4AAAfwQABA/gAAB/AgAED+AAAH8CAAQP4AAAfwIABA/gAAB/AgAID+AAAH8AAAgP4AAAfwEACA/gAAB/AQAID+AAAH8BAAgf4AAAf4EACB/gAAB/gQAAF+AAAH4BAAAH4AAAf4EAAB/gAAB/gQAAH+AAAH+BAAAf4AAAf4EACB/gAAB/gQAIH+AAAH+BAAgf4AAAP4EACB/AAAAPgQAIHwAAAAfBAAg+AAAAA8EACDwAAAADwgAEPAAAAAdCAAQuAAAAB8IABD4AAAAHwgAEPwAAAA/iAAw/gAAAD+MACH8AAAAP4QAIfwAAAA/hAAh7AAAADuGAGDsAAAAOwf/4MwAAAA4B//gDAAAADgGAMAMAAAAAAf/4AAAAAAAAf+AAAAAAAAAfgAAAAAAAAH/gAAAAAAAA//AAAAAAAAD/8AAAAAAAf4Af4AAAAAB////gAAAAAD///8AAAAAAPgAHwAAA="},"models-2025-plaid":{"cols":64,"rows":64,"mask":"AAABgAGAAAAAAB/AB/gAAAAAGAAAEAAAAAAY//8wAAAAAB////AAAAAAH/5/+AAAAAEf4Af4gAAAAzgAADzAAAADGB/wEMAAAAcAH/gA4AAADwA//ADwAAAPAH/+APAAAA8A//8A8AAADgD//wBwAAAeAP//AHgAB/4A//8Af+AH/gT//yB/4Af+BP//IH/gBvoE//8gX2AH/h3//3h/4Af+O///3H/gB/578A/ef+AH/gOAAwB/4Af+AQAAgH/gB/4BAACAf+AH/gEAAIB/4Af+AQAAgH/gB/4AAAEAf+AH/gCAAQB/4Af+AIABAH/gB/wAgAEAf+AH/gCAAQB/4Af+AIABAH/gB/4AgAEAf6AH/gCAAQB/oAf+AYABAH/gBv4BgAGAf+AGfwEAAIB/cAA/AQAAgPyAAd8BAACA+4AB7wEAAID3gAB6gQAAgX4AAB+BAACB+AAAD8GAAYPwAAAPwYABg/AAAA/hgAGH8AAAD+DAAwfwAAAP4MADB/AAAA/g4AcH8AAAD+D//wfwAAAO4H/+B2AAAAbgf/4GYAAABwA//ATgAAADAAPAAMAAAAMAH/gAwAAAAAA//AAAAAAAAH/+AAAAAAA8IAQMAAAAAD8AAHwAAAAAP////AAAAAA////8AAAAAH////wAAAAADgAAfAAAAAAAAAAAAAA="},"models-2021":{"cols":64,"rows":64,"mask":"AAAAAAAAAAAAAAB///AAAAAAH///+AAAAAAf///4AAAAAB////gAAAAAP///+AAAAAE////8gAAAA3///57AAAAD4B/wBMAAAAcAH/gA4AAADwA//ADwAAAPAH/+APAAAA8A//8A8AAADgD//wBwAAAeAP//AHgAB/4A//8Af+AH/gT//yB/4Af+BP//IH/gBvoE//8gX2AH/h3//3h/4Af+O///3H/gB/578A/ef+AH/gOAAwB/4Af+AQAAgH/gB/4BAACAf+AH/gEAAIB/4Af+AQAAgH/gB/4AAAEAf+AH/gCAAQB/4Af+AIABAH/gB/wAgAEAf+AH/gCAAQB/4Af+AIABAH/gB/4AgAEAf6AH/gCAAQB/oAf+AYABAH/gBv4BgAGAf+AGfwEAAIB/cAE/AQAAgPyAAd8BAACA+4AB7wEAAID3gAB6AQAAgX4AAB+BAACB+AAAD8GAAYPwAAAPwYABg/AAAA/hgAGH8AAAD+DAAwfwAAAP4MADB/AAAA/g4AcH8AAAD+D//wfwAAAO4H/+B2AAAAbgf/4GYAAABwA//ATgAAADAAPAAMAAAAMAH/gAwAAAAAA//AAAAAAAAH/+AAAAAAA4IAQMAAAAAD/4D/wAAAAAP////AAAAAA////8AAAAAD+//fwAAAAAPwAA/AAAAAAYAAAAAAA="},"modely-l":{"cols":64,"rows":64,"mask":"AAAAAAAAAAAAAg+AAOBgAAAGH8AD/DAAAA4f///8OAAAHj////w4AAAcf////jwAAD3/////vAAAPPgAAH++AAB8AAAAAB8AAHwAD/gAHgAAfYA//AGfAAB5wH/+AZ8AAPjAf/4BnwAB+MB//gMfgAP4YH//Ax/AP/hg//8GH/gAM7D//wbuAD/HsP//Dev8P/PQ//8Nz/w/8Bj//wgH/D/wGP//mAf+P/AJ//+YB/4/8An4H5gH/j/wDYABmAf+P/AMAAAQB/4/8AwAADAH/j/wDAAAMAf+P/AEAAAwB/4/8AYAADAH/j/wBgAAMAf+P/AGAAAwB/4D8AYAADAHwD8QBgAAIAD8f+AGAAAgB/5/8AYAACAP/n/wBgAAYA/+f/AGAABgD/5/8AYAAHAP/n/4BgAAcA/+f/gGAABwD/5/+AYAADAP/n/4AAAAAB/+f/gMAAAwH/4/+AwAADAf/gf4DAAAMB/wA/gMAAAwD8AABAwAADAQAAD8DAAAOB+AAPwcAAA4PwAAfhwAABg/AAB+HAAAGH8AAH8cAAAYfgAAf7wf+Bz/AAB/zP//Ef8AAH/B8A/B/wAA/8AAAAH/AAB/hwAAcd8AAH2Pf//43wAAOB9///geAAAB/3//f4AAAAD/f/9/gAAAAP///38AAAAA/AAAPwAAAAB4AAAOAAA="},"modely-2025-performance":{"cols":64,"rows":64,"mask":"AAA/AAD8AAAAAD+AAfwAAAAAP////AAAAAA////8AAAAAH////4AAAAA/////wAAAAP/////wAAAA9g//BOAAAAAMH/8DAAAAABwf/4OAAAAAPB//g4AAAAA8H/+DwAAAADg//4PAAAAAeD//weAAAAD4P//B8AAAAPg//8HwAAAA+D//wfAAAAHwP//B8AAAAfB//8D4AAAD8H8fwPwAAH/wYADA/4AAAeAAAAD8AAD/oIAAMF/gAP/egAA3f+AA/96AAD8/4AD/xMAAID/wAP/AwAAgP/AA/8BAACA/8AD/wEAAID/wAP+AQAAgP/AB/4BAAGA/8AH/gEAAYD/wAf+AQABAP/AB/4BAAEAf8AH/gEAAQB/4Ad+AQABAH4AB/YBAAGAb+AH/gEAAYB/4Af+AQAAgH/gD/4BAACAf+AP/gEAAIB/4A/+AQAAgH/gD/4DAACAf+AP/gMAAIB/4AP+AgAAwH/AAP4CAADAfwAA/gIAAEB+AAB+AgAAQH4AAD4GAABAfAAAdAYAAEB8AAB+BgAAQHwAAH4EAAAgfAAAfwQAACD+AAD/DAAAIP4AAP+MAeAx/wAC/wv//7H/wB7/G///kP/4H/YZ//+Yb/g/5BgAABgv/D+AEH/+EAn8H8AAf/4AA/gPwAB//gAD8A/AAH/+AAPwDAAAAAAAACA="},"modely-2025-premium":{"cols":64,"rows":64,"mask":"AAADgAEAAAAAAB/AA/gAAAAAH///+AAAAAA////4AAAAAD////wAAAAA/////wAAAAD+AAD/AAAAAAg//BAAAAAAMD/8DAAAAABwf/4OAAAAAHB//g4AAAAA8H/+DwAAAADg//4PAAAAAeD//weAAAAD4P//B8AAAAPg//8HwAAAA+D//wfAAAADwP//A8AAAAfB//8D4AAAD8H8fwPwAAH/wYADA/wAAAfAAAAD8AAB/oIAAMF/gAP/egAA3P+AA/97AAD+/4AD/xMAAID/wAP/AQAAgP/AA/8BAACA/8AD/wEAAID/wAP+AQAAgP/AA/4BAACA/8AH/gEAAYD/wAf+AQABgH/AB/4BAAEAf+AH/gEAAQB/4AZ+AQABgH4AB/YBAAGAb+AH/gEAAIB/4Af+AQAAgH/gD/4BAACAf+AP/gEAAIB/4A/+AQAAgH/gD/4DAACAf+AP/gMAAIB/4AP+AgAAwH/AAP4CAADAfwAAfgIAAEB+AAB+AgAAQH4AAD4CAABAfAAAdAYAAEB0AAB+BgAAYHwAAH4EAAAgfAAAfwQAACD+AAD/DAAAIP4AAP+MAYAx/wAA/wv//7D/AAb/G///kP/AH/YZ//+Yb/gf9BgAABgv+A/AEH/+EAXwD+AAf/4AB/AH4AB//gAH4APAAH/+AAfAAAAAAAAAAAA="},"modely-2025-base":{"cols":64,"rows":64,"mask":"AAAIAAAAAAAAAB8HY/AAAAAAD///8AAAAAAf///wAAAAAD////wAAAAAeD/4HgAAAAAIB+AAAAAAABg//AwAAAAAOD/8DAAAAAAwP/wOAAAAAHB//A4AAAAAcH/+DwAAAABwf/4HAAAAAPB//geAAAAA8P//B4AAAADw//8HgAAAAfD//weAAAAB8P//B4AAAAPxwA8HwAAAB/AAAAfgAAA94AABB/gAAD+BgAGEAAAAP+GAAYP+AAA/7oABc/4AAD/ugAFz/gAAP+CAAQP+AAA/4IABA/4AAD/AQAID/gAAP8BAAgP+AAA/wEACA/4AAD/gQAID/gAAP+BAAgP+AAB/4EACA/4AAH/AQAID/gAAAMBAAgPgAAB/wEACAP4AAH/gQAID/gAAf+BAAgP+AAB/4EACA/4AAH/gQAID/gAAf+BAAgP+AAB/4EACA/4AAH/gQAIH/gAAD+BAAgf4AAAD4EADB+AAAAPgQAEHwAAAAuDAAQfAAAADgAAAAMAAAAP0AAAnwAAAA/YAAG/AAAAD8gAAT8AAAAf7AADf4AAAB/mAAJ/gAAA38YABj+gAAH/wwAOP/gAAfyD//4b+AAB+AP//gJ4AAD8Af/4AfgAAHwABwAD8AAAMAH/+APAAAAAAf/4AEAAAAAA//gAAAAAAAD/8AAAAAAAAH/gAAAA="},"modely":{"cols":64,"rows":64,"mask":"AAIAH/gAAAAAAwf//+DAAAAGP////MAAAAY////8YAAATj////xgAABuP////HYAAGx////+dgAA/H////4/AAB88CAEDz4AAHwMB+AwPgAAfAwf+DA+AAB8DD/8MD4AAPwMf/4wPwAA/Ax//jA/AAH8DP/+MD+AB/wM//8wP+A//Az//zA//CAIDP//MBAEP/gN//+wH/w/+An//7Af/D/4Cf//sB/8P/gJ//+QH/w/+An//5Af/D/4C+AHkB/8P/gKAAAQH/w/+cgAABOf/D/76AAAM9/8P/voAAA33/w/+AgAADAf/D/4CAAAMB/8P/gMAAAwH/w/+AwAADAf/D/4DAAAMB/8PzgMAAAwHHw/8AwAADAP/D/4DAAAMB/8P/gMAAAwH/w/+AwAADAf/D/4DAAAMB/8P/gMAAAwH/4/+BgAABgf/j/4GAAAGB/8P/gMAAAwH/wP/AwAAHAf8AP8BgAAYD/AAfwHAADgP4AA/AMAAcA/gAB8A8ADwD4AAOQD///AJwAA/AP//8A/AAD+A///wH8AAP4Af/4AfwAA/wA//AD/gAH/AP//AP+AA/8A//8A/8AD/wD//wD/wAH+D///4H/AAfwPf//wP4AB+B+f+fgfgAAA/8AD/wAAAAB////+AAAAAH8AAP4AAAAAfgAAfAAAAAAwAAAEAAA="},"model3-2024-performance":{"cols":64,"rows":64,"mask":"AAAAAAAAAAAAAD8AAPwAAAADP////EAAAAY////8YAAADD////w4AAAcP////DgAADx////+PAAAPHAgBg4cAAB44AAABx4AAHgAAAAAHwAA+AAf+AAfAAD4AD/8AB8AAPgAP/wADwAA+AA//AAPgAHwAH/+AA+AB/AAf/4AD+A/8AB//wAP/D/wAP//AA/8IBAA//8AAAAAAID//wEAADv8gf//gT/cO/yB//+BP9w7/IH//4E/3Dv8gfAPgT/cO/yBgAGBP9w7/IAAAAE/3Dv8jgAAcT/8O/yfAAD5P/w7/I8AAPE//Dv8gAAAAT/8O/6AAAABP/w7/oAAAAE//Dv+gAAAAT/8O/6AAAABP/w/3oAAAAE7/D/+gAAAAT/8P/6AAAABP/4//oAAAAE//n/+gQAAgT/uf/6BgAGBP+5//oGAAYF/7nv+geAHgX/ue/6B//+Bf855/oH//4F/nnz+gf//wX8+/n6A//8Bfj8HPoE//IF84AGegf//AXmAAMaAD+ABYwAAcMD//wEHAAB+wH/+AT4AAH7Af/4BfgAAfsAAAAF+AAB/B4AB4P8AAP8HgAHg/wAA/w///+D/gAD//////v8AAPb/////bwAA9n////5vAADgf4AA/gcAAAB+AAB+AAAAAHwAAD4AAAAAMAAADAAAAAAAAAAAAAA="},"model3-2024-base":{"cols":64,"rows":64,"mask":"AAAAAAAAAAAAAAf//+AAAAADP////EAAAAZ////+YAAADH////44AAAcf////jgAADx////+HAAAPH////8cAAB4/GACA54AAHmAAAAAHwAA+AAP+AAfAAD4AB/8AA8AAPgAP/wADwAA+AA//AAPgAHwAH/+AA+AA/AAf/4AD+A/8AB//wAP/D/wAP//AA/8ABAA//8AAAAAAID//wEAADv8gP//gT/cO/yB//+BP9w7/IH//4E/3Dv8gfAPgT/cO/yBgAGBP9w7/IAAAAE//Dv8jgAAcT/8O/yfAAD5P/w7/I8AAPE//Dv8gAAAAT/8O/6AAAABP/w7/oAAAAE//Dv+gAAAAT/8O/6AAAABP/w7joAAAAE5/D/+gAAAAT/8P/6AAAABP/4//oAAAAE/7j/+gQAAgT/uf/6BgAGBP+5//oGAAYE/7nv+geAHgT/ue/6B//+Bf855/oH//4F/nnz+gf//wX8efn6A//8Bfj8HPoE//IF8YAGOgP//AXmAAMaAB+ABYwAAcMD//wEHAAB+wH/+AT4AAH7Af/4BfgAAfsAAAAF+AAB/B4AB4P8AAP8HgAHg/wAA/wf//+D/gAD/f////v8AAPb/////bwAA9n////5nAADgfwAA/gcAAAB+AAB+AAAAADwAADwAAAAAMAAADAAAAAAAAAAAAAA="},"model3":{"cols":64,"rows":64,"mask":"AAAAAAAAAAAAAAAH8AAAAAAAD///8AAAAAAP///wAAAAAA////AAAAAAH///+AAAAAAf///4AAAAAD////4AAAAAv///+QAAAACAf/4BgAAAAQAAAACAAAATQA/wAsgAABNgH/gGyAAAPyA//AR8AAA+ID/8BHwAAB4wf/4EfAAAHhB//gh8AAA+EH/+CDwAADwQ//8IPAAAfAj//xA+AAH8CP//ED+AB/wI//8QP+AH/An//5Af4AAABeAHoAPgN/gFAAGgH+w3+AQAACAf7Df4BAAAIB/sN/nkAAAnn+w3+eIAAEef7Df4AgAAQB/sN/gCAABAH/w3+AIAAEAf/Df4AgAAQB/8N/gCAABAH/w3+AIAAEAf/Df4AgAAwB/8N/gDAADAH/wx+AMAAIAfDj/gAwAAgAf+f/gBAACAH/5/+AEAAIAf/n/4AQAAgB/2f/gAAAAAH/Z/+AEAAYAf9n/4AYABgB/2d/gBgAMAH+Zz+AD8fwAf7nn4AP//AB/efPgB//+AHz4GeAH//4AeYAM8AH/+ABzAAZwAH/gAGYAA4AA//AAXgAD4AH/+AB8AAPwAf/4AHwAA/Ae//OA/gAH+B4AB4H+AA/4H///gP8AB/H////4/wAH8f////j/AAdg////8G4ABwD+AAfwLgAAAHgAAeAAAAAAQAAAIAAA="}}';
function ev_wrap_index(): array {
  $path=EV_WRAP_DIR.'/index.json';
  if (!is_file($path)) return [];
  $value=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
  if (!is_array($value)) throw new RuntimeException('Invalid wrap index');
  return $value;
}
function ev_wrap_write(array $index): void {
  $path=EV_WRAP_DIR.'/index.json';
  $temp=$path.'.'.bin2hex(random_bytes(6)).'.tmp';
  if (file_put_contents($temp,json_encode($index,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),LOCK_EX)===false) throw new RuntimeException('Index write failed');
  chmod($temp,0600);
  if (!rename($temp,$path)) { @unlink($temp);throw new RuntimeException('Index rename failed'); }
}
function ev_wrap_lock() {
  if (!is_dir(EV_WRAP_DIR) && !@mkdir(EV_WRAP_DIR,0700,true) && !is_dir(EV_WRAP_DIR)) throw new RuntimeException('Storage unavailable');
  $lock=fopen(EV_WRAP_DIR.'/index.lock','c');
  if (!$lock || !flock($lock,LOCK_EX)) throw new RuntimeException('Storage lock unavailable');
  return $lock;
}
function ev_wrap_valid_name($name): bool {
  return is_string($name) && preg_match('/^[A-Za-z0-9_-]{1,26}\.png$/D',$name)===1;
}
function ev_wrap_item(string $model,string $name,array $meta,bool $admin,?int $uid): array {
  $owner=(int)($meta['owner']??0);
  $item=['model'=>$model,'file'=>$name,'title'=>$meta['title']??str_replace('_',' ',substr($name,0,-4)),
    'url'=>'/api/wraps.php?model='.rawurlencode($model).'&file='.rawurlencode($name),
    'status'=>$meta['status']??'approved','author'=>$meta['author']??'BOSH',
    'mine'=>$uid!==null&&$owner===$uid,'date'=>$meta['date']??null];
  if ($admin) $item['email']=$meta['email']??'';
  return $item;
}
function ev_wrap_list(bool $admin=false,?int $uid=null): array {
  $index=ev_wrap_index();$items=[];
  foreach (EV_WRAP_MODELS as $model) {
    foreach (glob(EV_WRAP_DIR.'/'.$model.'/*.png') ?: [] as $path) {
      $name=basename($path);$key=$model.'/'.$name;
      if (!ev_wrap_valid_name($name)||!is_file($path)) continue;
      // New managed files without metadata are never public.
      if (str_starts_with($name,'u_')&&!isset($index[$key])) continue;
      $meta=$index[$key]??[];
      if (($meta['status']??'approved')!=='approved'&&!$admin&&($uid===null||(int)($meta['owner']??0)!==$uid)) continue;
      $items[]=ev_wrap_item($model,$name,$meta,$admin,$uid);
    }
  }
  usort($items,static fn($a,$b)=>strcmp($b['date']??'',$a['date']??''));
  return $items;
}
function ev_wrap_validate(string $tmp,string $model): void {
  $size=filesize($tmp);
  if ($size===false||$size>1000000) throw new InvalidArgumentException('Файл должен быть не больше 1 МБ.');
  $info=@getimagesize($tmp);
  if (!$info||$info[2]!==IMAGETYPE_PNG||min($info[0],$info[1])<512||max($info[0],$info[1])>1024) throw new InvalidArgumentException('Нужен PNG с размерами сторон от 512 до 1024 px.');
  $masks=json_decode(EV_WRAP_MASKS,true,512,JSON_THROW_ON_ERROR);$ref=$masks[$model];
  if (abs($info[1]/$info[0]-$ref['rows']/$ref['cols'])>0.005) throw new InvalidArgumentException('Пропорции PNG не соответствуют лекалу модели.');
  if (!function_exists('imagecreatefrompng')) throw new RuntimeException('GD PNG support required');
  $im=@imagecreatefrompng($tmp);
  if (!$im) throw new InvalidArgumentException('Не удалось прочитать PNG.');
  $mask=base64_decode($ref['mask'],true);$intersection=0;$union=0;$n=0;
  for ($y=0;$y<$ref['rows'];++$y) for ($x=0;$x<$ref['cols'];++$x) {
    $px=(int)(($x+0.5)*$info[0]/$ref['cols']);$py=(int)(($y+0.5)*$info[1]/$ref['rows']);
    $pixel=imagecolorat($im,$px,$py);
    $alpha=imageistruecolor($im)?(($pixel>>24)&127):imagecolorsforindex($im,$pixel)['alpha'];
    $painted=$alpha<64;
    $expected=(ord($mask[intdiv($n,8)]) & (128>>($n%8)))!==0;++$n;
    if ($painted&&$expected) ++$intersection;
    if ($painted||$expected) ++$union;
  }
  imagedestroy($im);
  if (!$union||$intersection/$union<0.94) throw new InvalidArgumentException('Контур не совпадает с лекалом выбранного кузова. Сохраните прозрачный фон и исходную развёртку.');
}
if (defined('EV_WRAP_LIBRARY_ONLY')) return;
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
try {
  $method=$_SERVER['REQUEST_METHOD']??'GET';
  if ($method==='GET') {
    if (isset($_GET['file'])) {
      $model=$_GET['model']??null;$name=$_GET['file'];
      if (!in_array($model,EV_WRAP_MODELS,true)||!ev_wrap_valid_name($name)) ev_response(404,['error'=>'not_found']);
      $meta=ev_wrap_index()[$model.'/'.$name]??null;
      if (!$meta&&str_starts_with($name,'u_')) ev_response(404,['error'=>'not_found']);
      if (($meta['status']??'approved')!=='approved') {
        ev_session();$user=ev_user();
        if (!$user||(!ev_root_access($user)&&(int)$user['id']!==(int)$meta['owner'])) ev_response(404,['error'=>'not_found']);
      }
      $path=EV_WRAP_DIR.'/'.$model.'/'.$name;
      if (!is_file($path)) ev_response(404,['error'=>'not_found']);
      header('Content-Type: image/png');header('Cache-Control: private, no-store');
      header('Content-Disposition: inline; filename="'.$name.'"');
      header('Content-Length: '.filesize($path));readfile($path);exit;
    }
    ev_session();$user=ev_user();$uid=$user?(int)$user['id']:null;
    $items=ev_wrap_list(false,$uid);
    ev_response(200,['items'=>$items,'limit'=>EV_WRAP_LIMIT,'count'=>count(array_filter($items,static fn($i)=>$i['mine']))]);
  }
  if ($method!=='POST') ev_response(405,['error'=>'method_not_allowed']);
  ev_session();$user=ev_require_user();$admin=ev_root_access($user);$uid=(int)$user['id'];
  ev_csrf($_POST);
  $redirect=$admin&&($_POST['from']??'')==='admin';
  $model=$_POST['model']??null;$action=$_POST['action']??'upload';
  if (!in_array($model,EV_WRAP_MODELS,true)) throw new InvalidArgumentException('Выберите модель.');
  $lock=ev_wrap_lock();
  try {
    $index=ev_wrap_index();
    if ($action==='delete'||$action==='approve') {
      $name=$_POST['file']??null;
      if (!ev_wrap_valid_name($name)) throw new InvalidArgumentException('Некорректный скин.');
      $key=$model.'/'.$name;$meta=$index[$key]??null;$path=EV_WRAP_DIR.'/'.$key;
      if (!is_file($path)||(!$admin&&(!$meta||(int)$meta['owner']!==$uid))||($action==='approve'&&!$admin)) {
        ev_response(403,['error'=>'forbidden']);
      }
      if ($action==='approve') {
        if (!$meta) throw new InvalidArgumentException('Скин уже опубликован.');
        $index[$key]['status']='approved';ev_wrap_write($index);
      } else {
        if (!unlink($path)) throw new RuntimeException('Delete failed');
        unset($index[$key]);ev_wrap_write($index);
      }
      $saved=1;$failed=0;
    } elseif ($action==='upload') {
      $dir=EV_WRAP_DIR.'/'.$model;
      if (!is_dir($dir)&&!@mkdir($dir,0700,true)&&!is_dir($dir)) throw new RuntimeException('Storage unavailable');
      $uploads=$_FILES['skins']??[];$names=$uploads['name']??[];
      if (!is_array($names)||!count($names)||count($names)>10||(!$admin&&count($names)!==1)) throw new InvalidArgumentException('Выберите PNG.');
      $owned=count(array_filter($index,static fn($i)=>(int)($i['owner']??0)===$uid));
      $saved=0;$failed=0;$lastError='';
      foreach ($names as $i=>$original) {
        try {
          if (!$admin&&$owned>=EV_WRAP_LIMIT) throw new InvalidArgumentException('Лимит: 10 скинов на аккаунт.');
          $tmp=$uploads['tmp_name'][$i]??'';
          if (!is_string($original)||!is_string($tmp)||($uploads['error'][$i]??-1)!==UPLOAD_ERR_OK||!is_uploaded_file($tmp)) throw new InvalidArgumentException('Файл не загружен.');
          if (!preg_match('/^[A-Za-z0-9 _-]{1,30}\.png$/iD',$original)) throw new InvalidArgumentException('Имя PNG: до 30 символов латиницы, цифр, пробелов, дефисов и подчёркиваний.');
          ev_wrap_validate($tmp,$model);
          $hash=hash_file('sha256',$tmp);
          foreach ($index as $existing) if (($existing['hash']??'')===$hash) throw new InvalidArgumentException('Такой скин уже добавлен.');
          $title=trim((string)($_POST['title']??''));
          if ($title==='') $title=str_replace('_',' ',pathinfo($original,PATHINFO_FILENAME));
          if (mb_strlen($title)>80) throw new InvalidArgumentException('Название — не более 80 символов.');
          $name='u_'.bin2hex(random_bytes(12)).'.png';$key=$model.'/'.$name;
          if (!move_uploaded_file($tmp,$dir.'/'.$name)) throw new RuntimeException('Image write failed');
          chmod($dir.'/'.$name,0600);
          $index[$key]=['owner'=>$uid,'email'=>$user['email'],'author'=>$admin?'BOSH':(trim((string)$user['display_name'])?:'Участник'),
            'title'=>$title,'hash'=>$hash,'status'=>$admin?'approved':'pending','date'=>gmdate('c')];
          try {ev_wrap_write($index);} catch(Throwable $e){@unlink($dir.'/'.$name);unset($index[$key]);throw $e;}
          ++$saved;++$owned;
        } catch(InvalidArgumentException $e){++$failed;$lastError=$e->getMessage();}
      }
      if (!$saved&&$lastError!=='') throw new InvalidArgumentException($lastError);
    } else throw new InvalidArgumentException('Неизвестное действие.');
  } finally {flock($lock,LOCK_UN);fclose($lock);}
  if ($redirect){header('Location: /root?page=wraps&saved='.$saved.'&failed='.$failed,true,303);exit;}
  ev_response(200,['ok'=>true,'saved'=>$saved,'failed'=>$failed]);
} catch(InvalidArgumentException $e) {
  if (!empty($redirect)){header('Location: /root?page=wraps&error='.rawurlencode($e->getMessage()),true,303);exit;}
  ev_response(422,['error'=>'invalid_wrap','message'=>$e->getMessage()]);
} catch(Throwable $e) {
  error_log('EV wraps: '.$e->getMessage());ev_response(503,['error'=>'wraps_unavailable','message'=>'Сервис скинов временно недоступен.']);
}
