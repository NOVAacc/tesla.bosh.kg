<?php
declare(strict_types=1);
require_once __DIR__.'/api/root-access.php';
ev_session();
$user=ev_user();
if (!ev_root_access($user)) {http_response_code($user?403:401);header('Content-Type: text/html; charset=utf-8');echo '<p>Доступ закрыт. <a href="/root">Войти в панель управления</a></p>';exit;}
session_write_close();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
?><!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Управление FAQ · EV Карта</title><link rel="stylesheet" href="/root.css"><link rel="stylesheet" href="/root-faq.css"></head>
<body><main><nav><a href="/root">← Админка</a><a href="/faq">Открыть FAQ</a></nav>
<h1>Вопросы и ответы</h1><p class="note">Изменения сохраняются в базе данных и сразу становятся доступны посетителям. Скрытые вопросы видны только здесь.</p>
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
<section class="faq-admin-list"><div class="section-heading"><h2>Все вопросы</h2><input type="search" id="faqFilter" placeholder="Найти вопрос…"></div><div id="faqCards"></div></section>
</main><script src="/root-faq.js" type="module"></script></body></html>