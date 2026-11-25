<?php
/**
 * includes/cart.php
 * Корзина и покупки: позиции корзины, количество, сумма,
 * проверка «фильм уже куплен», доступ к просмотру.
 */
declare(strict_types=1);

/** Позиции корзины пользователя с данными тайтла */
function cart_items(?int $userId = null): array
{
    $userId ??= (int)(current_user()['id'] ?? 0);
    if (!$userId) {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT c.id, c.title_id, c.quantity, c.added_at,
                t.title, t.slug, t.poster, t.year, t.type, t.price, t.rating
           FROM cart_items c
           JOIN titles t ON t.id = c.title_id
          WHERE c.user_id = ?
          ORDER BY c.added_at DESC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/** Сколько всего единиц в корзине (для значка в шапке) */
function cart_count(?int $userId = null): int
{
    $items = cart_items($userId);
    return array_sum(array_map(static fn($item) => (int)$item['quantity'], $items));
}

/** Сумма корзины, ₽ */
function cart_total(?int $userId = null): float
{
    $total = 0.0;
    foreach (cart_items($userId) as $item) {
        $total += (float)$item['price'] * (int)$item['quantity'];
    }
    return round($total, 2);
}

/** ID купленных фильмов пользователя */
function purchased_ids(?int $userId = null): array
{
    $userId ??= (int)(current_user()['id'] ?? 0);
    if (!$userId) {
        return [];
    }

    $stmt = db()->prepare('SELECT title_id FROM purchases WHERE user_id = ?');
    $stmt->execute([$userId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Куплен ли конкретный фильм */
function owns_title(int $titleId): bool
{
    if (!is_logged_in()) {
        return false;
    }
    return in_array($titleId, purchased_ids(), true);
}

/** Доступ к просмотру: подписка, покупка фильма или права администратора */
function can_watch(array $title): bool
{
    if (is_admin()) {
        return true;
    }
    if (is_subscriber()) {
        return true;
    }
    return owns_title((int)$title['id']);
}
