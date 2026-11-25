<?php
/**
 * includes/functions.php
 * Небольшая библиотека хелперов, которая используется на всех страницах:
 * построение ссылок, экранирование, цены, flash-сообщения, CSRF, JSON-ответы.
 */
declare(strict_types=1);

/** Абсолютный путь внутри проекта: url('catalog.php') → /illuminos/catalog.php */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Ссылка на статический файл из папки assets */
function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

/** Ссылка на CSS/JS с версией по времени изменения — сбрасывает кэш браузера */
function asset_versioned(string $path): string
{
    $file = __DIR__ . '/../assets/' . ltrim($path, '/');
    $version = is_file($file) ? (string)filemtime($file) : '1';
    return asset($path) . '?v=' . $version;
}

/** Экранирование любого текста перед выводом в HTML (защита от XSS) */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Форматирование цены: 599 → «599 ₽» */
function price(float $value): string
{
    $decimals = fmod($value, 1.0) === 0.0 ? 0 : 2;
    return number_format($value, $decimals, ',', ' ') . ' ₽';
}

/** Фото тайтла или заглушка, если постер не загружен */
function poster_src(?array $title, string $folder = 'posters'): string
{
    $file = $title['poster'] ?? '';
    if ($file) {
        // Сначала ищем постер среди загруженных через админку, затем — в статике
        if (is_file(__DIR__ . '/../uploads/posters/' . $file)) {
            return url('uploads/posters/' . $file);
        }
        if (is_file(__DIR__ . '/../assets/img/' . $folder . '/' . $file)) {
            return asset('img/' . $folder . '/' . $file);
        }
    }
    return asset('img/no-poster.svg');
}

/** Транслитерация названия в slug: «Игра престолов» → igra-prestolov */
function slugify(string $text): string
{
    $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
        'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    $text = mb_strtolower(trim($text));
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text);

    return trim((string)$text, '-') ?: 'title-' . time();
}

/** Плашка «Новое» / «Топ-10» / любимый бейдж из БД */
function badge_html(?string $badge): string
{
    if (!$badge) {
        return '';
    }
    return '<span class="badge-chip">' . e($badge) . '</span>';
}

/** Русское склонение: 1 сезон / 2 сезона / 5 сезонов */
function plural(int $n, string $one, string $few, string $many): string
{
    $mod100 = $n % 100;
    $mod10  = $n % 10;
    if ($mod100 >= 11 && $mod100 <= 14) {
        return $n . ' ' . $many;
    }
    if ($mod10 === 1) {
        return $n . ' ' . $one;
    }
    if ($mod10 >= 2 && $mod10 <= 4) {
        return $n . ' ' . $few;
    }
    return $n . ' ' . $many;
}

/** Длительность в минутах → «2 ч 15 мин» */
function duration_text(?int $minutes): string
{
    if (!$minutes) {
        return '';
    }
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h > 0 ? "{$h} ч {$m} мин" : "{$m} мин";
}

/** Запомнить flash-сообщение до следующего запроса */
function flash(string $text, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['text' => $text, 'type' => $type];
}

/** Забрать и очистить flash-сообщения */
function take_flash(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

/** Редирект с завершением скрипта */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/** Текущий запрос пришёл через fetch/AJAX? */
function is_ajax(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

/** Единый формат JSON-ответа для api/*.php */
function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** CSRF-токен формы (хранится в сессии) */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Скрытое поле с CSRF-токеном для форм */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

/** Проверка CSRF-токена из POST-запроса */
function csrf_valid(?string $token): bool
{
    return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$token);
}

/**
 * Склонение времени назад для истории просмотра.
 */
function human_date(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    $ts = strtotime($datetime);
    $diff = time() - $ts;

    if ($diff < 3600) {
        return 'менее часа назад';
    }
    if ($diff < 86400) {
        return plural(intdiv($diff, 3600), 'час назад', 'часа назад', 'часов назад');
    }
    if ($diff < 172800) {
        return 'вчера';
    }
    return date('d.m.Y', $ts);
}
