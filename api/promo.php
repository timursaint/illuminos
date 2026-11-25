<?php
/**
 * api/promo.php — проверка промокода на странице оплаты.
 * Принимает JSON {code, tariff_id}. Возвращает размер скидки и итог.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'message' => 'Метод не поддерживается'], 405);
}

$payload   = json_decode(file_get_contents('php://input') ?: '[]', true);
$code      = mb_strtoupper(trim((string)($payload['code'] ?? '')));
$tariffId  = (int)($payload['tariff_id'] ?? 0);

if ($code === '') {
    json_out(['ok' => false, 'message' => 'Введите промокод'], 422);
}

/* Подписка: сумма берётся из тарифа. Корзина фильмов: тарифа нет, сумму присылает клиент
   (на оплате checkout.php всё равно пересчитывает её из cart_total()). */
$amount = 0.0;
if ($tariffId > 0) {
    $stmt = db()->prepare('SELECT price FROM tariffs WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$tariffId]);
    $amount = (float)($stmt->fetchColumn() ?: 0);
}
if ($amount <= 0) {
    $amount = (float)($payload['amount'] ?? 0);
}

if ($amount <= 0) {
    json_out(['ok' => false, 'message' => 'Сумма заказа не найдена'], 404);
}

$stmt = db()->prepare(
    'SELECT * FROM promocodes
      WHERE code = ? AND is_active = 1
        AND (valid_until IS NULL OR valid_until >= CURDATE())
        AND used_count < usage_limit
      LIMIT 1'
);
$stmt->execute([$code]);
$promo = $stmt->fetch();

if (!$promo) {
    json_out(['ok' => false, 'message' => 'Промокод недействителен или уже использован'], 404);
}

$discount = round($amount * (int)$promo['discount_percent'] / 100, 2);
$total    = max(0, $amount - $discount);

json_out([
    'ok'       => true,
    'code'     => $promo['code'],
    'percent'  => (int)$promo['discount_percent'],
    'discount' => $discount,
    'total'    => $total,
    'message'  => 'Промокод применён: скидка ' . (int)$promo['discount_percent'] . '%',
]);
