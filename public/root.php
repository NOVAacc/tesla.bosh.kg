<?php
declare(strict_types=1);
require_once __DIR__.'/api/root-access.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; connect-src 'self'; img-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
ev_session();
$user = ev_user();
$allowed = ev_root_access($user);
if (!$allowed) http_response_code($user ? 403 : 401);
$page = match ($_GET['page'] ?? null) {
  'comments' => 'comments',
  'businesses' => 'businesses',
  'faq' => 'faq',
  'donation' => 'donation',
  'users' => 'users',
  'analytics' => 'analytics',
  'tesla' => 'tesla',
  'wraps' => 'wraps',
  default => 'dashboard',
};
$donationError = '';
$donation = null;
if ($allowed && $page === 'donation') {
  require_once __DIR__.'/api/donation-storage.php';
  try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
      if (empty($_POST) && !empty($_SERVER['CONTENT_LENGTH'])) {
        throw new InvalidArgumentException('Файл слишком большой. Максимальный размер — 2 МБ.');
      }
      ev_csrf($_POST);
      donation_save($_POST, $_FILES['qr'] ?? []);
      header('Location: /root?page=donation&saved=1', true, 303);
      exit;
    }
  } catch (InvalidArgumentException $error) {
    $donationError = $error->getMessage();
  } catch (Throwable $error) {
    error_log('EV donation settings: '.$error->getMessage());
    $donationError = 'Не удалось сохранить настройки.';
  }
  try {
    $donation = donation_read();
  } catch (Throwable $error) {
    error_log('EV donation settings: '.$error->getMessage());
    $donationError = 'Не удалось загрузить настройки.';
  }
}

$businessError = '';
$businessRows = [];
$businessCategories = [];
$businessEdit = null;
$businessCount = 0;
$businessMapCount = 0;
$businessPage = 1;
$businessPages = 1;
$businessLoadError = false;
if ($allowed && $page === 'businesses') {
  try {
    $db = ev_db();
    $businessCategories = $db->query('SELECT id,slug,name_ru FROM business_categories ORDER BY name_ru')->fetchAll(PDO::FETCH_ASSOC);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
      ev_csrf($_POST);
      $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
      $category = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
      $fields = [];
      foreach (['name'=>150,'address'=>255,'description'=>1500,'phone'=>40,'whatsapp'=>40] as $field=>$limit) {
        $value = $_POST[$field] ?? '';
        if (!is_string($value) || mb_strlen($value)>$limit) throw new InvalidArgumentException('Проверьте длину полей.');
        $fields[$field] = trim($value);
      }
      $lat = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
      $lng = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
      if (!$id || !$category || $fields['name']==='' || $fields['address']==='' || $lat===false || $lng===false ||
          !is_finite($lat) || !is_finite($lng) || abs($lat)>90 || abs($lng)>180 ||
          !in_array($category, array_map(static fn($c)=>(int)$c['id'], $businessCategories), true)) {
        throw new InvalidArgumentException('Проверьте название, категорию, адрес и координаты.');
      }
      foreach (['phone','whatsapp'] as $field) {
        if ($fields[$field]==='') continue;
        $digits = preg_replace('/[^0-9]/','',$fields[$field]);
        if (!preg_match('/^[+0-9 ()-]+$/D',$fields[$field]) || strlen($digits)<7 || strlen($digits)>15) {
          throw new InvalidArgumentException('Проверьте формат телефона и WhatsApp.');
        }
      }
      $db->beginTransaction();
      $existing = $db->prepare("SELECT id FROM businesses WHERE id=? AND status='approved' FOR UPDATE");
      $existing->execute([$id]);
      if (!$existing->fetchColumn()) {
        $db->rollBack();
        throw new InvalidArgumentException('Компания не найдена или больше не опубликована.');
      }
      $update = $db->prepare("UPDATE businesses SET category_id=?,name=?,address=?,description=?,phone=?,whatsapp=?,latitude=?,longitude=? WHERE id=? AND status='approved'");
      $update->execute([$category,$fields['name'],$fields['address'],$fields['description'] ?: null,$fields['phone'] ?: null,$fields['whatsapp'] ?: null,$lat,$lng,$id]);
      $db->commit();
      header('Location: /root?page=businesses&edit='.$id.'&saved=1', true, 303);
      exit;
    }
  } catch (InvalidArgumentException $error) {
    $businessError = $error->getMessage();
  } catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('EV admin business update: '.$error->getMessage());
    $businessError = 'Не удалось сохранить или загрузить данные компании.';
  }
  try {
    $db = ev_db();
    $businessCount = (int)$db->query("SELECT COUNT(*) FROM businesses WHERE status='approved'")->fetchColumn();
    $businessMapCount = (int)$db->query("SELECT COUNT(*) FROM businesses b JOIN business_categories c ON c.id=b.category_id WHERE b.status='approved' AND c.slug='ev-service' AND b.latitude BETWEEN -90 AND 90 AND b.longitude BETWEEN -180 AND 180")->fetchColumn();
    $businessPages = max(1, (int)ceil($businessCount/25));
    $requested = filter_var($_GET['p'] ?? 1, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $businessPage = min($requested ?: 1,$businessPages);
    $baseSql = "SELECT b.id,b.name,b.category_id,b.address,b.description,b.phone,b.whatsapp,b.latitude,b.longitude,c.name_ru AS category,
      (c.slug='ev-service' AND b.latitude BETWEEN -90 AND 90 AND b.longitude BETWEEN -180 AND 180) AS on_map
      FROM businesses b JOIN business_categories c ON c.id=b.category_id WHERE b.status='approved'";
    $list = $db->prepare($baseSql.' ORDER BY on_map DESC,b.id DESC LIMIT 25 OFFSET :offset');
    $list->bindValue(':offset',($businessPage-1)*25,PDO::PARAM_INT);
    $list->execute();
    $businessRows = $list->fetchAll(PDO::FETCH_ASSOC);
    $editId = filter_var($_GET['edit'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if ($editId) {
      $edit = $db->prepare($baseSql.' AND b.id=?');
      $edit->execute([$editId]);
      $businessEdit = $edit->fetch(PDO::FETCH_ASSOC) ?: null;
      if (!$businessEdit && $businessError==='') $businessError='Компания не найдена.';
      if ($businessEdit && ($_SERVER['REQUEST_METHOD'] ?? 'GET')==='POST' && $businessError!=='') {
        foreach (['name','category_id','address','description','phone','whatsapp','latitude','longitude'] as $field) {
          if (is_string($_POST[$field] ?? null)) $businessEdit[$field]=$_POST[$field];
        }
      }
    }
  } catch (Throwable $error) {
    error_log('EV admin business directory: '.$error->getMessage());
    $businessLoadError = true;
  }
}
function root_business_html($value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$wrapItems=[];
if ($allowed && $page==='wraps') {
  define('EV_WRAP_LIBRARY_ONLY',true);
  require_once __DIR__.'/api/wraps.php';
  $wrapItems=ev_wrap_list(true,(int)$user['id']);
}
$csrf = $_SESSION['csrf'] ?? '';
session_write_close();
$userCount = null;
$userRows = [];
$userListError = false;
$userPage = 1;
$userTotalPages = 1;
const EV_ROOT_USERS_PAGE_SIZE = 25;
if ($allowed && in_array($page, ['dashboard', 'users'], true)) {
  try {
    $db = ev_db();
    $userCount = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($page === 'users') {
      $requested = filter_var($_GET['p'] ?? 1, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
      $userTotalPages = max(1, (int) ceil($userCount / EV_ROOT_USERS_PAGE_SIZE));
      $userPage = min($requested === false ? 1 : $requested, $userTotalPages);
      $offset = ($userPage - 1) * EV_ROOT_USERS_PAGE_SIZE;
      $list = $db->prepare('SELECT u.id, u.email, EXISTS(SELECT 1 FROM user_google_identities g WHERE g.user_id=u.id) AS google_linked
        FROM users u ORDER BY u.id DESC LIMIT :limit OFFSET :offset');
      $list->bindValue(':limit', EV_ROOT_USERS_PAGE_SIZE, PDO::PARAM_INT);
      $list->bindValue(':offset', $offset, PDO::PARAM_INT);
      $list->execute();
      $userRows = $list->fetchAll(PDO::FETCH_ASSOC);
    }
  } catch (Throwable $error) {
    error_log('EV admin user list or count unavailable: '.$error->getMessage());
    if ($page === 'users') $userListError = true;
  }
}

// Read only local connection metadata and cached vehicle lists; never call Tesla API.
$teslaAccounts = null;
$teslaVehicleCount = null;
$teslaUnknown = 0;
$teslaRows = [];
$teslaError = false;
if ($allowed && in_array($page, ['dashboard', 'tesla'], true)) {
  try {
    $tokenDir = '/home/g143326/.tesla-oauth-tokens';
    $cacheDir = '/home/g143326/.tesla-vehicle-cache';
    if (is_dir($tokenDir) && !is_readable($tokenDir)) throw new RuntimeException('Tesla connection directory unreadable');
    $files = is_dir($tokenDir) ? glob($tokenDir.'/user-*.json') : [];
    if ($files === false) throw new RuntimeException('Tesla connection directory unavailable');
    $ids = [];
    foreach ($files as $file) {
      if (is_file($file) && preg_match('/^user-([1-9][0-9]*)\\.json$/D', basename($file), $match)) {
        $ids[(int)$match[1]] = true;
      }
    }
    $teslaAccounts = 0;
    $teslaVehicleCount = 0;
    $lookup = ev_db()->prepare('SELECT email FROM users WHERE id = :id');
    foreach (array_keys($ids) as $id) {
      $lookup->execute(['id' => $id]);
      $email = $lookup->fetchColumn();
      if ($email === false) continue;
      ++$teslaAccounts;
      $cacheFile = $cacheDir.'/user-'.$id.'.json';
      $cache = is_file($cacheFile) ? json_decode((string) @file_get_contents($cacheFile), true) : null;
      $vehicles = is_array($cache['vehicles'] ?? null) ? array_filter($cache['vehicles'], 'is_array') : null;
      if ($vehicles === null || !$vehicles) ++$teslaUnknown;
      if ($vehicles !== null) $teslaVehicleCount += count($vehicles);
      if ($page === 'tesla') {
        $teslaRows[] = ['email' => $email, 'vehicles' => $vehicles ?? [], 'checked_at' => (int)($cache['checked_at'] ?? 0)];
      }
    }
  } catch (Throwable $error) {
    error_log('EV admin Tesla summary unavailable: '.$error->getMessage());
    $teslaError = true;
    $teslaAccounts = $teslaVehicleCount = null;
    $teslaRows = [];
  }
}

?><!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Панель управления · EV Карта</title>
  <link rel="stylesheet" href="/root.css?v=<?= hash_file('sha256', __DIR__.'/root.css') ?>">
  <?php if ($allowed && $page === 'faq'): ?>
  <link rel="stylesheet" href="/root-faq.css?v=<?= hash_file('sha256', __DIR__.'/root-faq.css') ?>">
  <?php endif; ?>
</head>
<body>
<main>
  <nav><a href="/">Карта зарядок</a><a href="/account.html">Кабинет</a></nav>
  <h1>Панель управления</h1>
  <?php if ($allowed): ?>
    <nav class="root-menu" aria-label="Разделы админки">
      <a href="/root"<?= $page === 'dashboard' ? ' aria-current="page"' : '' ?>>Дашборд</a>
      <a href="/root?page=users"<?= $page === 'users' ? ' aria-current="page"' : '' ?>>Пользователи</a>
      <a href="/root?page=tesla"<?= $page === 'tesla' ? ' aria-current="page"' : '' ?>>Tesla</a>
      <a href="/root?page=wraps"<?= $page === 'wraps' ? ' aria-current="page"' : '' ?>>Скины</a>
      <a href="/root?page=analytics"<?= $page === 'analytics' ? ' aria-current="page"' : '' ?>>Аналитика</a>
      <a href="/root?page=businesses"<?= $page === 'businesses' ? ' aria-current="page"' : '' ?>>Компании</a>
      <a href="/root?page=comments"<?= $page === 'comments' ? ' aria-current="page"' : '' ?>>Комментарии</a>
      <a href="/root?page=faq"<?= $page === 'faq' ? ' aria-current="page"' : '' ?>>FAQ</a>
      <a href="/root?page=donation"<?= $page === 'donation' ? ' aria-current="page"' : '' ?>>Поддержка проекта</a>
    </nav>
    <?php if ($page === 'faq'): ?>
    <div class="section-heading"><h2>Вопросы и ответы</h2><a href="/faq">Открыть FAQ</a></div><p class="note">Изменения сохраняются в базе данных и сразу становятся доступны посетителям. Скрытые вопросы видны только здесь.</p>
    <p id="faqStatus" role="status"></p>
    <section class="faq-editor card">
    <h2 id="editorTitle">Новый вопрос</h2><form id="faqForm">
    <input type="hidden" name="id" id="faqId">
    <label>Категория<select id="faqCategory" required></select></label>
    <label>Вопрос<input id="faqQuestion" maxlength="350" required></label>
    <label>Ответ<textarea id="faqAnswer" rows="7" maxlength="10000" required></textarea></label>
    <div class="faq-form-row"><label>Порядок<input type="number" id="faqOrder" min="0" max="100000" value="100" required></label><label class="check"><input type="checkbox" id="faqPublished" checked> Опубликован</label></div>
    <div class="faq-buttons"><button type="submit" id="faqSave">Сохранить вопрос</button><button type="button" id="faqCancel" class="secondary">Отмена</button></div>
    </form></section>
    <section class="faq-admin-list"><div class="section-heading"><h2>Все вопросы</h2><input type="search" id="faqFilter" placeholder="Найти вопрос…"></div><p class="note" id="faqReorderHint">Перетащите вопрос за заголовок или используйте стрелки. Порядок сохраняется автоматически.</p><div id="faqCards"></div></section>
    <script src="/root-faq.js?v=<?= hash_file('sha256', __DIR__.'/root-faq.js') ?>" type="module"></script>
    <?php elseif ($page === 'wraps'): ?>
    <section class="card">
      <h2>Пользовательские скины</h2>
      <p class="note">Загрузите до 10 PNG за раз. Они появятся в кабинете выбранной модели без значка Tesla. Размер каждого — до 1 МБ.</p>
      <?php if (isset($_GET['saved'])): ?><p role="status">Добавлено: <?= (int)$_GET['saved'] ?> · Не принято: <?= (int)($_GET['failed']??0) ?></p><?php endif; ?>
      <?php if (isset($_GET['error'])): ?><p role="alert"><?= htmlspecialchars(is_string($_GET['error'])?$_GET['error']:'Не удалось принять файлы.',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></p><?php endif; ?>
      <form class="donation-form" method="post" action="/api/wraps.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="from" value="admin">
        <label>Модель <select name="model" required>
        <?php foreach (EV_WRAP_MODELS as $model): ?>
          <option value="<?= htmlspecialchars($model,ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars($model==='model3'?'Model 3 · 2017–2023':$model,ENT_QUOTES,'UTF-8') ?></option>
        <?php endforeach; ?>
        </select></label>
        <label>PNG <input type="file" name="skins[]" accept="image/png,.png" multiple required></label>
        <button type="submit">Добавить скины</button>
      </form>
      <h3>Загружено: <?= count($wrapItems) ?></h3>
      <?php foreach ($wrapItems as $item): ?>
        <article class="card business-directory-item">
          <img width="100" height="100" loading="lazy" src="<?= htmlspecialchars($item['url'],ENT_QUOTES,'UTF-8') ?>" alt="">
          <h3><?= htmlspecialchars($item['title'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></h3>
          <p><?= htmlspecialchars($item['model'].' · '.$item['author'].' · '.$item['email'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></p>
          <p class="note"><?= $item['status']==='pending'?'На модерации':'Опубликован' ?> · <?= htmlspecialchars((string)$item['date'],ENT_QUOTES,'UTF-8') ?></p>
          <form method="post" action="/api/wraps.php">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>">
            <input type="hidden" name="from" value="admin">
            <input type="hidden" name="model" value="<?= htmlspecialchars($item['model'],ENT_QUOTES,'UTF-8') ?>">
            <input type="hidden" name="file" value="<?= htmlspecialchars($item['file'],ENT_QUOTES,'UTF-8') ?>">
            <?php if ($item['status']==='pending'): ?><button type="submit" name="action" value="approve">Одобрить</button><?php endif; ?>
            <button type="submit" name="action" value="delete">Удалить</button>
          </form>
        </article>
      <?php endforeach; ?>
    </section>
    <?php elseif ($page === 'tesla'): ?>
    <section class="card users" aria-labelledby="teslaSummary">
      <h2 id="teslaSummary">Подключённые Tesla</h2>
      <p class="total"><?= $teslaVehicleCount === null ? 'Недоступно' : number_format($teslaVehicleCount, 0, ',', ' ') ?></p>
      <p class="note">Аккаунтов с локальной привязкой: <?= $teslaAccounts === null ? 'Недоступно' : number_format($teslaAccounts, 0, ',', ' ') ?> · Без сведений об автомобилях: <?= $teslaAccounts === null ? '—' : $teslaUnknown ?></p>
      <p class="note">Количество записей автомобилей в кэше (до 3 на аккаунт), без запросов к Tesla API. Отозванные разрешения могут оставаться в локальной привязке.</p>
      <?php if ($page === 'dashboard'): ?><a href="/root?page=tesla">Список подключений</a><?php endif; ?>
    </section>

    <section class="card root-user-list">
      <div class="section-heading"><h2>Автомобили и аккаунты</h2><a href="/root?page=tesla">Обновить</a></div>
      <?php if ($teslaError): ?>
        <p role="alert">Не удалось загрузить подключения.</p>
      <?php elseif (!$teslaRows): ?>
        <p class="note">Подключений пока нет.</p>
      <?php else: ?>
      <div class="root-user-table-wrap"><table class="root-user-table">
        <thead><tr><th scope="col">Email</th><th scope="col">Модель</th><th scope="col">VIN (последние 4)</th><th scope="col">Обновление (UTC)</th></tr></thead>
        <tbody>
        <?php foreach ($teslaRows as $row): ?>
          <?php foreach ($row['vehicles'] ?: [[]] as $car): ?>
          <tr>
            <td><?= htmlspecialchars((string)$row['email'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= htmlspecialchars(in_array($car['model'] ?? '', ['3','Y','S','X'], true) ? 'Model '.$car['model'] : ($car ? 'Tesla' : 'Нет данных'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($car['vin_suffix'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= $row['checked_at'] > 0 ? gmdate('d.m.Y H:i', $row['checked_at']) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </section>
    <?php elseif ($page === 'analytics'): ?>
    <section aria-labelledby="analyticsTitle">
      <div class="section-heading"><h2 id="analyticsTitle">Аналитика аудитории</h2><div><label for="analyticsDays">Период</label> <select id="analyticsDays"><option value="1">Сегодня</option><option value="7">7 дней</option><option value="30" selected>30 дней</option><option value="90">90 дней</option></select> <button id="analyticsRefresh" type="button">Обновить</button></div></div>
      <p id="analyticsStatus" class="note" role="status"></p><div id="analyticsContent"></div>
    </section>
    <script src="/root-analytics.js?v=<?= hash_file('sha256', __DIR__.'/root-analytics.js') ?>" type="module"></script>
    <?php elseif ($page === 'businesses'): ?>

    <section class="card users" aria-labelledby="publishedBusinessTitle">
      <div class="section-heading"><h2 id="publishedBusinessTitle">Опубликованные компании</h2><a href="/root?page=businesses">Обновить</a></div>
      <?php if ($businessLoadError): ?>
        <p role="alert">Не удалось загрузить каталог.</p>
      <?php else: ?>
        <p class="total"><?= $businessMapCount ?></p>
        <p class="note">На карте · Всего одобрено: <?= $businessCount ?></p>
      <?php endif; ?>
      <?php if ($businessError!==''): ?><p role="alert"><?= root_business_html($businessError) ?></p><?php elseif (isset($_GET['saved'])): ?><p role="status">Данные компании сохранены.</p><?php endif; ?>
      <?php if ($businessEdit): ?>
        <h3>Редактирование компании</h3>
        <form class="donation-form" method="post" action="/root?page=businesses&amp;edit=<?= (int)$businessEdit['id'] ?>&amp;p=<?= $businessPage ?>">
          <input type="hidden" name="csrf" value="<?= root_business_html($csrf) ?>">
          <input type="hidden" name="id" value="<?= (int)$businessEdit['id'] ?>">
          <label>Название<input name="name" required maxlength="150" value="<?= root_business_html($businessEdit['name']) ?>"></label>
          <label>Категория <select name="category_id" required>
          <?php foreach ($businessCategories as $category): ?>
            <option value="<?= (int)$category['id'] ?>"<?= (int)$businessEdit['category_id']===(int)$category['id']?' selected':'' ?>><?= root_business_html($category['name_ru']) ?></option>
          <?php endforeach; ?>
          </select></label>
          <p class="note">На текущем слое карты показываются одобренные компании категории EV-сервис с корректными координатами.</p>
          <label>Адрес<input name="address" required maxlength="255" value="<?= root_business_html($businessEdit['address']) ?>"></label>
          <label>Описание<textarea name="description" rows="5" maxlength="1500"><?= root_business_html($businessEdit['description']) ?></textarea></label>
          <label>Телефон<input name="phone" type="tel" maxlength="40" value="<?= root_business_html($businessEdit['phone']) ?>"></label>
          <label>WhatsApp<input name="whatsapp" type="tel" maxlength="40" value="<?= root_business_html($businessEdit['whatsapp']) ?>"></label>
          <div class="donation-total-fields">
            <label>Широта<input name="latitude" type="number" step="0.0000001" min="-90" max="90" required value="<?= root_business_html($businessEdit['latitude']) ?>"></label>
            <label>Долгота<input name="longitude" type="number" step="0.0000001" min="-180" max="180" required value="<?= root_business_html($businessEdit['longitude']) ?>"></label>
          </div>
          <div><button type="submit">Сохранить</button> <a href="/root?page=businesses&amp;p=<?= $businessPage ?>">Закрыть</a></div>
        </form>
      <?php endif; ?>
      <?php if (!$businessLoadError && !$businessRows): ?><p class="note">Опубликованных компаний пока нет.</p><?php endif; ?>
      <?php foreach ($businessRows as $company): ?>
        <details class="card root-user-list business-directory-item">
          <summary><strong><?= root_business_html($company['name']) ?></strong> · <span class="feedback-state"><?= $company['on_map']?'На карте':'Не на карте' ?></span></summary>
          <p class="note"><?= root_business_html($company['category']) ?> · ID <?= (int)$company['id'] ?></p>
          <p>Адрес: <?= root_business_html($company['address'] ?? '—') ?></p>
          <p>Описание: <?= root_business_html($company['description'] ?? '—') ?></p>
          <p>Телефон: <?= root_business_html($company['phone'] ?? '—') ?><br>WhatsApp: <?= root_business_html($company['whatsapp'] ?? '—') ?></p>
          <p>Координаты: <?= root_business_html($company['latitude'] ?? '—') ?>, <?= root_business_html($company['longitude'] ?? '—') ?></p>
          <?php if ($company['latitude']!==null && $company['longitude']!==null): ?>
            <a target="_blank" rel="noopener noreferrer" href="https://www.openstreetmap.org/?mlat=<?= rawurlencode((string)$company['latitude']) ?>&amp;mlon=<?= rawurlencode((string)$company['longitude']) ?>#map=17/<?= rawurlencode((string)$company['latitude']) ?>/<?= rawurlencode((string)$company['longitude']) ?>">Посмотреть точку</a><br>
          <?php endif; ?>
          <a class="action" href="/root?page=businesses&amp;p=<?= $businessPage ?>&amp;edit=<?= (int)$company['id'] ?>">Редактировать</a>
        </details>
      <?php endforeach; ?>
      <?php if (!$businessLoadError && $businessPages>1): ?>
        <nav class="root-user-pages" aria-label="Страницы компаний">
          <?php if ($businessPage>1): ?><a href="/root?page=businesses&amp;p=<?= $businessPage-1 ?>">Назад</a><?php endif; ?>
          <span>Страница <?= $businessPage ?> из <?= $businessPages ?></span>
          <?php if ($businessPage<$businessPages): ?><a href="/root?page=businesses&amp;p=<?= $businessPage+1 ?>">Вперёд</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    </section>

    <section aria-labelledby="businessReviewTitle">
      <div class="section-heading"><h2 id="businessReviewTitle">Заявки компаний</h2><button type="button" id="businessReviewRefresh">Обновить</button></div>
      <p id="businessReviewStatus" role="status"></p>
      <div id="businessReviewCards"></div>
      <button type="button" id="businessReviewNext" hidden>Показать ещё</button>
    </section>
    <script src="/root-businesses.js?v=<?= hash_file('sha256', __DIR__.'/root-businesses.js') ?>" type="module"></script>
    <?php elseif ($page === 'donation'): ?>
    <section class="card">
      <h2>Поддержка проекта</h2>
      <?php if ($donationError !== ''): ?>
        <p role="alert"><?= htmlspecialchars($donationError, ENT_QUOTES, 'UTF-8') ?></p>
      <?php elseif (isset($_GET['saved'])): ?>
        <p role="status">Настройки сохранены.</p>
      <?php endif; ?>
      <?php if ($donation !== null): ?>
      <form class="donation-form" action="/root?page=donation" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <label>Сообщение <textarea name="message" rows="3" maxlength="240" required><?= htmlspecialchars($donation['message'], ENT_QUOTES, 'UTF-8') ?></textarea></label>
        <label>Текст кнопки <input name="button" maxlength="48" value="<?= htmlspecialchars($donation['button'], ENT_QUOTES, 'UTF-8') ?>" required></label>
        <div class="donation-total-fields">
          <label>Всего собрано, сом <input type="number" name="total_amount" min="0" max="999999999.99" step="0.01" inputmode="decimal" placeholder="Например, 12500" value="<?= htmlspecialchars((string)($donation['total_amount'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
          <label>Дата обновления суммы <input type="date" name="total_updated_at" value="<?= htmlspecialchars((string)($donation['total_updated_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
        </div>
        <p class="note">Сумма и дата указываются вручную. Оставьте оба поля пустыми, чтобы скрыть статистику на главной.</p>
        <label>QR-код <input name="qr" type="file" accept="image/png,image/jpeg,image/webp"></label>
        <p class="note"><?= $donation['image'] === '' ? 'QR-код ещё не загружен.' : 'QR-код загружен. Выберите новый файл для замены.' ?> PNG, JPEG или WebP до 2 МБ.</p>
        <label><input type="checkbox" name="enabled"<?= $donation['enabled'] ? ' checked' : '' ?>> Показывать на карте</label>
        <button type="submit">Сохранить</button>
      </form>
      <?php endif; ?>
    </section>
    <?php elseif ($page === 'dashboard' || $page === 'users'): ?>
    <section class="card users" aria-labelledby="users">
      <h2 id="users">Пользователи в базе</h2>
      <p class="total"><?= $userCount === null ? 'Недоступно' : number_format($userCount, 0, ',', ' ') ?></p>
    </section>
    <?php if ($page === 'users'): ?>
    <section class="card root-user-list" aria-labelledby="rootUserListTitle">
      <div class="section-heading"><h2 id="rootUserListTitle">Зарегистрированные пользователи</h2><span class="muted">По 25 на странице</span></div>
      <?php if ($userListError): ?>
        <p class="note" role="alert">Не удалось загрузить список пользователей.</p>
      <?php elseif (!$userRows): ?>
        <p class="note">Пользователей пока нет.</p>
      <?php else: ?>
        <div class="root-user-table-wrap">
          <table class="root-user-table">
            <thead><tr><th scope="col">ID</th><th scope="col">Email</th><th scope="col">Google</th></tr></thead>
            <tbody>
            <?php foreach ($userRows as $row): ?>
              <tr>
                <td><?= (int) $row['id'] ?></td>
                <td><?= htmlspecialchars((string) $row['email'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                <td><?= (int) $row['google_linked'] ? 'Подключён' : 'Нет' ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <nav class="root-user-pages" aria-label="Страницы пользователей">
          <?php if ($userPage > 1): ?><a href="/root?page=users&amp;p=<?= $userPage - 1 ?>">Назад</a><?php endif; ?>
          <span>Страница <?= $userPage ?> из <?= $userTotalPages ?></span>
          <?php if ($userPage < $userTotalPages): ?><a href="/root?page=users&amp;p=<?= $userPage + 1 ?>">Вперёд</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    </section>
    <?php endif; ?>
    <?php if ($page === 'dashboard'): ?>
    <section class="card users" aria-labelledby="teslaSummary">
      <h2 id="teslaSummary">Подключённые Tesla</h2>
      <p class="total"><?= $teslaVehicleCount === null ? 'Недоступно' : number_format($teslaVehicleCount, 0, ',', ' ') ?></p>
      <p class="note">Аккаунтов с локальной привязкой: <?= $teslaAccounts === null ? 'Недоступно' : number_format($teslaAccounts, 0, ',', ' ') ?> · Без сведений об автомобилях: <?= $teslaAccounts === null ? '—' : $teslaUnknown ?></p>
      <p class="note">По последним сохранённым данным, без запросов к Tesla API. Отозванные разрешения могут оставаться в локальной привязке.</p>
      <?php if ($page === 'dashboard'): ?><a href="/root?page=tesla">Список подключений</a><?php endif; ?>
    </section>
    <section aria-labelledby="networks">
      <div class="section-heading"><h2 id="networks">Зарядные сети</h2><button id="refreshNetworks" type="button">Обновить</button></div>
      <p class="note">Рабочая зарядка имеет хотя бы один порт в рабочем статусе, включая занятые порты.</p>
      <p id="networkStatus" role="status"></p>
      <div class="networks" id="networkCards"></div>
    </section>
    <?php endif; ?>
    <?php else: ?>
    <section class="feedback-section" aria-labelledby="feedbackTitle">
      <div class="section-heading"><h2 id="feedbackTitle">Сообщения о станциях</h2><button id="refreshFeedback" type="button">Обновить</button></div>
      <p class="note">Новые сообщения сверху. Метка показывает, виден ли текст на карте.</p>
      <p id="feedbackStatus" role="status" aria-live="polite">Загрузка сообщений…</p>
      <div id="feedbackCards" class="feedback-cards"></div>
      <div class="feedback-pages"><button id="feedbackPrev" type="button" disabled>Назад</button><span id="feedbackPage"></span><button id="feedbackNext" type="button" disabled>Вперёд</button></div>
    </section>
    <?php endif; ?>
    <?php if ($page === 'dashboard' || $page === 'comments'): ?>
    <script src="/root.js?v=<?= hash_file('sha256', __DIR__.'/root.js') ?>" type="module"></script>
    <?php endif; ?>
  <?php else: ?>
    <section class="card">
      <h2><?= $user ? 'Доступ закрыт' : 'Войдите в аккаунт владельца' ?></h2>
      <?php if (isset($_GET['auth_error'])): ?><p>Не удалось завершить вход. Попробуйте ещё раз.</p><?php endif; ?>
      <a class="action" href="/api/google-start.php?return=root">Войти через Google</a>
    </section>
  <?php endif; ?>
</main>
</body>
</html>
