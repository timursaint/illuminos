<?php
/**
 * logout.php — завершение сессии и возврат на главную.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

logout();
flash('Вы вышли из аккаунта.', 'success');
redirect(url('index.php'));
