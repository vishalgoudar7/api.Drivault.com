<?php
declare(strict_types=1);

return [
    'endpoint' => getenv('DRIVAULT_OCS_ENDPOINT') ?: 'https://login.drivault.com/ocs/v1.php/cloud/users',
    'username' => getenv('DRIVAULT_OCS_USERNAME') ?: 'admin',
    'password' => getenv('DRIVAULT_OCS_PASSWORD') ?: 'kuRsef-gobno8-gankux',
];
