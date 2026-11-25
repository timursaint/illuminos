<?php
/**
 * api/history.php — запись просмотра в историю личного кабинета.
 * Принимает JSON {title_id, episode_id, progress}.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'message' => 'Метод не поддерживается'], 405);
}

$user = current_user();
if (!$user) {
    json_out(['ok' => false, 'message' => 'Требуется авторизация'], 401);
}

$payload   = json_decode(file_get_contents('php://input') ?: '[]', true);
$titleId   = (int)($payload['title_id'] ?? 0);
$episodeId = !empty($payload['episode_id']) ? (int)$payload['episode_id'] : null;
$progress  = max(0, min(100, (int)($payload['progress'] ?? 0)));

$check = db()->prepare('SELECT id FROM titles WHERE id = ?');
$check->execute([$titleId]);
if (!$check->fetch()) {
    json_out(['ok' => false, 'message' => 'Тайтл не найден'], 404);
}

db()->prepare('INSERT INTO watch_history (user_id, title_id, episode_id, progress) VALUES (?, ?, ?, ?)')
    ->execute([$user['id'], $titleId, $episodeId, $progress]);

// Держим историю компактной: у пользователя не больше 50 последних записей
db()->prepare(
    'DELETE FROM watch_history
      WHERE user_id = ?
        AND id NOT IN (SELECT id FROM (
              SELECT id FROM watch_history WHERE user_id = ? ORDER BY watched_at DESC LIMIT 50
        ) AS recent)'
)->execute([$user['id'], $user['id']]);

json_out(['ok' => true]);
