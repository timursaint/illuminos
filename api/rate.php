<?php
/**
 * api/rate.php — сохранение оценки тайтла (1–10) без перезагрузки страницы.
 * Принимает JSON {title_id, score}.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'message' => 'Метод не поддерживается'], 405);
}

$user = current_user();
if (!$user) {
    json_out(['ok' => false, 'message' => 'Войдите, чтобы поставить оценку'], 401);
}

$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
$titleId = (int)($payload['title_id'] ?? 0);
$score   = (int)($payload['score'] ?? 0);

if ($score < 1 || $score > 10) {
    json_out(['ok' => false, 'message' => 'Оценка должна быть от 1 до 10'], 422);
}

$check = db()->prepare('SELECT id FROM titles WHERE id = ?');
$check->execute([$titleId]);
if (!$check->fetch()) {
    json_out(['ok' => false, 'message' => 'Тайтл не найден'], 404);
}

$stmt = db()->prepare(
    'INSERT INTO reviews (user_id, title_id, score)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE score = VALUES(score), created_at = NOW()'
);
$stmt->execute([$user['id'], $titleId, $score]);

$avg = db()->prepare('SELECT ROUND(AVG(score), 1) AS avg_score, COUNT(*) AS total FROM reviews WHERE title_id = ?');
$avg->execute([$titleId]);
$row = $avg->fetch();

json_out([
    'ok'    => true,
    'score' => $score,
    'avg'   => (float)$row['avg_score'],
    'total' => (int)$row['total'],
]);
