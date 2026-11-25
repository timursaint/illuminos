<?php
/**
 * includes/auth.php
 * Авторизация и роли. Пользователь хранится в сессии (user_id),
 * данные подтягиваются из БД в current_user().
 */
declare(strict_types=1);

/** Данные авторизованного пользователя или null */
function current_user(): ?array
{
    static $user = null;
    static $loaded = false;

    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND status = "active" LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch() ?: null;

    if (!$user) {
        unset($_SESSION['user_id']);
    }
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

/** Активная подписка пользователя или null */
function active_subscription(?int $userId = null): ?array
{
    $userId ??= (int)(current_user()['id'] ?? 0);
    if (!$userId) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT s.*, t.name AS tariff_name, t.quality
           FROM subscriptions s
           JOIN tariffs t ON t.id = s.tariff_id
          WHERE s.user_id = ? AND s.status = "active" AND s.ends_at >= CURDATE()
          ORDER BY s.ends_at DESC LIMIT 1'
    );
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

/** Есть ли действующая подписка (админ имеет доступ всегда) */
function is_subscriber(): bool
{
    if (is_admin()) {
        return true;
    }
    return active_subscription() !== null;
}

/**
 * Попытка входа. Возвращает массив [успех, сообщение].
 */
function attempt_login(string $login, string $password): array
{
    $login = trim($login);
    if ($login === '' || $password === '') {
        return [false, 'Заполните логин и пароль.'];
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE login = ? OR email = ? LIMIT 1');
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return [false, 'Неверный логин или пароль.'];
    }
    if ($user['status'] === 'blocked') {
        return [false, 'Аккаунт заблокирован администратором.'];
    }

    // Защита от фиксации сессии: выдаём новый идентификатор
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['role']    = $user['role'];

    return [true, 'Добро пожаловать, ' . $user['first_name'] . '!'];
}

/** Выход из аккаунта */
function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Доступ только для авторизованных */
function require_auth(): void
{
    if (!is_logged_in()) {
        flash('Войдите в аккаунт, чтобы продолжить.', 'warning');
        redirect(url('login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '')));
    }
}

/** Доступ только для администратора */
function require_admin(): void
{
    require_auth();
    if (!is_admin()) {
        http_response_code(403);
        flash('Раздел доступен только администратору.', 'error');
        redirect(url('index.php'));
    }
}

/** Человекочитаемое название роли для интерфейса */
function role_name(string $role): string
{
    return match ($role) {
        'admin'      => 'Администратор',
        'subscriber' => 'Подписчик',
        default      => 'Пользователь',
    };
}

/** Первая буква имени для аватара-заглушки */
function user_initials(array $user): string
{
    return mb_strtoupper(mb_substr($user['first_name'] ?? '', 0, 1) . mb_substr($user['last_name'] ?? '', 0, 1));
}
