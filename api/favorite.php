<?php
/**
 * api/favorite.php — добавление/удаление тайтла в избранном.
 * Принимает JSON {title_id}. Требует авторизации.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Метод не поддерживается'], 405);
}

$user = current_user();
if (!$user) {
    json_out(['ok' => false, 'error' => 'auth', 'message' => 'Войдите, чтобы добавлять в избранное'], 401);
}

$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
$titleId = (int)($payload['title_id'] ?? 0);

$check = db()->prepare('SELECT id FROM titles WHERE id = ?');
$check->execute([$titleId]);
if (!$check->fetch()) {
    json_out(['ok' => false, 'error' => 'Тайтл не найден'], 404);
}

$stmt = db()->prepare('SELECT id FROM favorites WHERE user_id = ? AND title_id = ? LIMIT 1');
$stmt->execute([$user['id'], $titleId]);
$existing = $stmt->fetch();

if ($existing) {
    db()->prepare('DELETE FROM favorites WHERE id = ?')->execute([$existing['id']]);
    json_out(['ok' => true, 'active' => false]);
}

db()->prepare('INSERT INTO favorites (user_id, title_id) VALUES (?, ?)')->execute([$user['id'], $titleId]);
json_out(['ok' => true, 'active' => true]);
