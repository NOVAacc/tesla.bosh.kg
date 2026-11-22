<?php
declare(strict_types=1);

require_once __DIR__.'/account-bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
  header('Allow: GET');
  ev_response(405, ['error'=>'method_not_allowed']);
}

try {
  $items = ev_db()->query("
    SELECT b.name, b.address, b.description, b.phone, b.whatsapp, b.latitude AS lat, b.longitude AS lng
    FROM businesses AS b
    JOIN business_categories AS c ON c.id = b.category_id
    WHERE b.status = 'approved' AND c.slug = 'ev-service'
      AND b.latitude BETWEEN -90 AND 90 AND b.longitude BETWEEN -180 AND 180
    ORDER BY b.id
  ")->fetchAll();
  ev_response(200, ['items'=>$items]);
} catch (Throwable $error) {
  error_log('EV map services: '.$error->getMessage());
  ev_response(503, ['error'=>'services_unavailable']);
}
