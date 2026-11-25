<?php
/**
 * config/config.php
 * Основные настройки проекта Illuminos.
 * Значения вынесены в константы, чтобы их можно было менять в одном месте.
 */
declare(strict_types=1);

// Название сервиса и слоган (используются в <title> и шапке сайта)
const SITE_NAME    = 'Illuminos';
const SITE_TAGLINE = 'Онлайн-кинотеатр нового поколения';

// Префикс URL. Проект лежит в подпапке http://localhost/illuminos,
// поэтому все ссылки строятся через хелпер url().
const BASE_URL = '/illuminos';

// Контактные данные для подвала сайта
const SITE_EMAIL   = 'support@illuminos.ru';
const SITE_PHONE   = '8 (800) 555-35-35';
const SITE_ADDRESS = 'г. Стерлитамак, пр. Октября, 1';

// Настройки загрузки изображений (админка)
const UPLOAD_MAX_SIZE = 5 * 1024 * 1024; // 5 МБ
const POSTER_DIR      = __DIR__ . '/../uploads/posters';
const POSTER_URL      = BASE_URL . '/uploads/posters';

// Часовой пояс и кодировка по умолчанию
date_default_timezone_set('Asia/Yekaterinburg');
mb_internal_encoding('UTF-8');

// Режим отладки: true — подробные ошибки на экран (только для разработки)
const APP_DEBUG = true;

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}
