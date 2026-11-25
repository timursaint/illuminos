<?php
/**
 * config/bootstrap.php
 * Единая точка инициализации: настройки, БД, хелперы, сессия.
 * Подключается первой строкой на каждой странице сайта.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cart.php';

// Сессия хранит авторизацию и flash-сообщения.
// Cookie недоступна из JavaScript — защита от кражи сессии через XSS.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
