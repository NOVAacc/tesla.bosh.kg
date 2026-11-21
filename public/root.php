<?php
declare(strict_types=1);
require_once __DIR__.'/api/root-access.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
ev_session();
$user = ev_user();
$allowed = ev_root_access($user);
if (!$allowed) http_response_code($user ? 403 : 401);
$page = match ($_GET['page'] ?? null) {
  'comments' => 'comments',
  'faq' => 'faq',
  'donation' => 'donation',
  'users' => 'users',
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
$csrf = $_SESSION['csrf'] ?? '';
session_write_close();
$userCount = null;
if ($allowed && in_array($page, ['dashboard', 'users'], true)) {
  try {
    $userCount = (int) ev_db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
  } catch (Throwable $error) {
    error_log('EV admin user count unavailable');
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
    <?php if ($page === 'dashboard'): ?>
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
