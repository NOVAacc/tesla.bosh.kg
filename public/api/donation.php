<?php
declare(strict_types=1);
require_once __DIR__.'/donation-storage.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
  header('Allow: GET');
  http_response_code(405);
  exit;
}

try {
  $settings = donation_read();
  if (isset($_GET['qr'])) {
    if (!$settings['enabled'] || $settings['image'] === '') {
      http_response_code(404);
      exit;
    }
    header('Content-Type: '.$settings['mime']);
    echo base64_decode($settings['image'], true);
    exit;
  }
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'enabled'=>$settings['enabled'] && $settings['image'] !== '',
    'message'=>$settings['message'],
    'button'=>$settings['button'],
    'total_amount'=>$settings['total_amount']??null,
    'total_updated_at'=>$settings['total_updated_at']??null,
  ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
  error_log('EV donation settings: '.$error->getMessage());
  http_response_code(503);
}
