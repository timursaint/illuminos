<?php
/**
 * config/db.php
 * Подключение к базе данных MySQL/MariaDB через PDO.
 * Данные подключения соответствуют настройкам OpenServer Panel
 * (MySQL на порту 3306, пользователь root без пароля).
 */
declare(strict_types=1);

// Параметры подключения к БД
const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'illuminos_timur';
const DB_USER = 'root';
const DB_PASS = '';

/**
 * Возвращает единственный экземпляр соединения PDO (паттерн Singleton).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // ошибки как исключения
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // результат — ассоциативный массив
            PDO::ATTR_EMULATE_PREPARES   => false,                  // настоящие подготовленные запросы
        ]);
    } catch (PDOException $e) {
        // Понятное сообщение вместо «белого экрана»
        http_response_code(500);
        exit(
            '<div class="u-font-system u-p-40 u-max-w-720 u-m-0-auto u-text-eee u-bg-12121a">'
            . '<h2 class="u-text-accent-hex">Нет соединения с базой данных</h2>'
            . '<p>Проверьте, что MySQL запущен в OpenServer Panel, а база <b>' . DB_NAME . '</b> импортирована из '
            . '<code>database/illuminos.sql</code>.</p>'
            . '<p class="u-text-dim">' . htmlspecialchars($e->getMessage()) . '</p></div>'
        );
    }

    return $pdo;
}
