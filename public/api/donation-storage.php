<?php
declare(strict_types=1);

function donation_path(): string {
  return dirname(getenv('EV_PRIVATE_ENV') ?: '/home/g143326/.tesla-ev.env').'/.tesla-ev-donation.json';
}

function donation_read(): array {
  $path = donation_path();
  if (!is_file($path)) {
    return ['enabled'=>false, 'message'=>'Помогите развивать карту зарядок Кыргызстана. Поддержите проект донатом.', 'button'=>'Поддержать проект', 'image'=>'', 'mime'=>'', 'total_amount'=>null, 'total_updated_at'=>null];
  }
  return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}

function donation_save(array $form, array $upload): void {
  $settings = donation_read();
  foreach (['message'=>240, 'button'=>48] as $field=>$limit) {
    $value = isset($form[$field]) && is_string($form[$field]) ? trim($form[$field]) : '';
    if ($value === '' || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value, 'UTF-8') > $limit) {
      throw new InvalidArgumentException('Проверьте текст сообщения и название кнопки.');
    }
    $settings[$field] = $value;
  }
  // Manual total of received donations; independent of payment/QR functionality.
  $rawAmount=isset($form['total_amount']) && is_string($form['total_amount'])
    ? trim($form['total_amount']) : '';
  $rawDate=isset($form['total_updated_at']) && is_string($form['total_updated_at'])
    ? trim($form['total_updated_at']) : '';
  if ($rawAmount==='') {
    if ($rawDate!=='') throw new InvalidArgumentException('Укажите сумму донатов или очистите дату обновления.');
    $settings['total_amount']=null;
    $settings['total_updated_at']=null;
  } else {
    if (!preg_match('/^(?:0|[1-9][0-9]{0,8})(?:\\.[0-9]{1,2})?$/D', $rawAmount)) {
      throw new InvalidArgumentException('Введите сумму в сомах от 0 до 999 999 999,99.');
    }
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$rawDate);
    if ($date===false || $date->format('Y-m-d')!==$rawDate) {
      throw new InvalidArgumentException('Укажите корректную дату обновления суммы.');
    }
    $settings['total_amount']=number_format((float)$rawAmount,2,'.','');
    $settings['total_updated_at']=$rawDate;
  }
  if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 2 * 1024 * 1024 || !is_uploaded_file($upload['tmp_name'])) {
      throw new InvalidArgumentException('Загрузите изображение размером до 2 МБ.');
    }
    $image = getimagesize($upload['tmp_name']);
    if (!$image || !in_array($image['mime'], ['image/png','image/jpeg','image/webp'], true)
        || max($image[0], $image[1]) > 4096) {
      throw new InvalidArgumentException('Поддерживаются PNG, JPEG и WebP размером до 4096 пикселей по каждой стороне.');
    }
    $settings['image'] = base64_encode(file_get_contents($upload['tmp_name']));
    $settings['mime'] = $image['mime'];
  }
  $settings['enabled'] = isset($form['enabled']);
  if ($settings['enabled'] && $settings['image'] === '') {
    throw new InvalidArgumentException('Сначала загрузите QR-код для донатов.');
  }

  $path = donation_path();
  $temp = tempnam(dirname($path), '.donation-');
  if ($temp === false) throw new RuntimeException('Cannot create donation settings file');
  try {
    $json = json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    if (file_put_contents($temp, $json) !== strlen($json) || !rename($temp, $path)) {
      throw new RuntimeException('Cannot save donation settings');
    }
  } finally {
    if (is_file($temp)) unlink($temp);
  }
}
