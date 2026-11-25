<?php
/**
 * api/cart.php — работа с корзиной без перезагрузки страницы.
 * Принимает JSON: { action: add|update|remove|clear, title_id, quantity }.
 * Возвращает количество позиций и сумму корзины.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/cart.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'message' => 'Метод не поддерживается'], 405);
}

$user = current_user();
if (!$user) {
    json_out([
        'ok'       => false,
        'message'  => 'Войдите, чтобы добавлять фильмы в корзину',
        'loginUrl' => url('login.php?next=' . urlencode($_SERVER['HTTP_REFERER'] ?? url('cart.php'))),
    ], 401);
}

$payload  = json_decode(file_get_contents('php://input') ?: '[]', true);
$action   = (string)($payload['action'] ?? 'add');
$titleId  = (int)($payload['title_id'] ?? 0);
$quantity = max(1, min(5, (int)($payload['quantity'] ?? 1)));

$respond = static function (string $message) use ($user): never {
    $items = cart_items((int)$user['id']);
    json_out([
        'ok'       => true,
        'message'  => $message,
        'count'    => array_sum(array_map(static fn($i) => (int)$i['quantity'], $items)),
        'items'    => count($items),
        'total'    => cart_total((int)$user['id']),
    ]);
};

switch ($action) {
    case 'add':
        $stmt = db()->prepare('SELECT id, title, price FROM titles WHERE id = ?');
        $stmt->execute([$titleId]);
        $title = $stmt->fetch();

        if (!$title) {
            json_out(['ok' => false, 'message' => 'Тайтл не найден'], 404);
        }
        if ((float)$title['price'] <= 0) {
            json_out(['ok' => false, 'message' => 'Этот фильм доступен только по подписке'], 422);
        }
        if (owns_title($titleId)) {
            json_out(['ok' => false, 'message' => 'Фильм уже куплен'], 422);
        }

        // Если позиция уже есть — увеличиваем количество (не больше 5)
        $stmt = db()->prepare(
            'INSERT INTO cart_items (user_id, title_id, quantity) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + 1, 5)'
        );
        $stmt->execute([$user['id'], $titleId]);
        $respond('«' . $title['title'] . '» добавлен в корзину');
        // no break

    case 'update':
        $stmt = db()->prepare('UPDATE cart_items SET quantity = ? WHERE user_id = ? AND title_id = ?');
        $stmt->execute([$quantity, $user['id'], $titleId]);
        $respond('Количество обновлено');
        // no break

    case 'remove':
        db()->prepare('DELETE FROM cart_items WHERE user_id = ? AND title_id = ?')
            ->execute([$user['id'], $titleId]);
        $respond('Фильм удалён из корзины');
        // no break

    case 'clear':
        db()->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([$user['id']]);
        $respond('Корзина очищена');
        // no break

    default:
        json_out(['ok' => false, 'message' => 'Неизвестное действие'], 422);
}
