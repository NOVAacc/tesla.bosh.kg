<?php
declare(strict_types=1);
require_once __DIR__.'/account-bootstrap.php';

function ev_root_access(?array $user): bool {
  return $user !== null
    && !empty($user['email_verified_at'])
    && strcasecmp($user['email'], 'gmederio@gmail.com') === 0;
}
