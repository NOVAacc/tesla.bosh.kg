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
session_write_close();
$allowed = ev_root_access($user);
if (!$allowed) http_response_code($user ? 403 : 401);
$comments = ($_GET['page'] ?? null) === 'comments';
$userCount = null;
if ($allowed && !$comments) {
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
  <link rel="stylesheet" href="/root.css">
</head>
<body>
<main>
  <nav><a href="/">Карта зарядок</a><a href="/account.html">Кабинет</a></nav>
  <h1>Панель управления</h1>
  <?php if ($allowed): ?>
    <nav class="root-menu" aria-label="Разделы админки">
      <a href="/root"<?= $comments ? '' : ' aria-current="page"' ?>>Дашборд</a>
      <a href="/root?page=comments"<?= $comments ? ' aria-current="page"' : '' ?>>Комментарии</a>
    </nav>
    <?php if (!$comments): ?>
    <section class="card users" aria-labelledby="users">
      <h2 id="users">Пользователи в базе</h2>
      <p class="total"><?= $userCount === null ? 'Недоступно' : number_format($userCount, 0, ',', ' ') ?></p>
    </section>
    <section aria-labelledby="networks">
      <div class="section-heading"><h2 id="networks">Зарядные сети</h2><button id="refreshNetworks" type="button">Обновить</button></div>
      <p class="note">Рабочая зарядка имеет хотя бы один порт в рабочем статусе, включая занятые порты.</p>
      <p id="networkStatus" role="status"></p>
      <div class="networks" id="networkCards"></div>
    </section>
    <?php else: ?>
    <section class="feedback-section" aria-labelledby="feedbackTitle">
      <div class="section-heading"><h2 id="feedbackTitle">Сообщения о станциях</h2><button id="refreshFeedback" type="button">Обновить</button></div>
      <p class="note">Новые сообщения сверху. Метка показывает, виден ли текст на карте.</p>
      <p id="feedbackStatus" role="status" aria-live="polite">Загрузка сообщений…</p>
      <div id="feedbackCards" class="feedback-cards"></div>
      <div class="feedback-pages"><button id="feedbackPrev" type="button" disabled>Назад</button><span id="feedbackPage"></span><button id="feedbackNext" type="button" disabled>Вперёд</button></div>
    </section>
    <?php endif; ?>
    <script src="/root.js?v=<?= hash_file('sha256', __DIR__.'/root.js') ?>" type="module"></script>
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
