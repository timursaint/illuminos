<?php
/**
 * payment-success.php — страница успешной оплаты (чек).
 * Показывает данные заказа: подписку или купленные фильмы.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

require_auth();
$user = current_user();

$orderNumber = trim((string)($_GET['order'] ?? ''));

$stmt = db()->prepare(
    'SELECT p.*, t.name AS tariff_name, t.period_days, t.quality
       FROM payments p
       LEFT JOIN tariffs t ON t.id = p.tariff_id
      WHERE p.order_number = ? AND p.user_id = ?
      LIMIT 1'
);
$stmt->execute([$orderNumber, $user['id']]);
$payment = $stmt->fetch();

if (!$payment) {
    flash('Заказ не найден.', 'error');
    redirect(url('cabinet.php'));
}

$isFilms = ($payment['kind'] ?? 'subscription') === 'films';
$items = [];
if ($isFilms) {
    $stmt = db()->prepare('SELECT * FROM order_items WHERE payment_id = ? ORDER BY id');
    $stmt->execute([$payment['id']]);
    $items = $stmt->fetchAll();
}

$subscription = $isFilms ? null : active_subscription();

$page_title = 'Оплата успешна';
require __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="checkout u-grid-cols-1-3-1">
        <div class="checkout-card">
            <div class="u-d-flex u-items-center u-gap-16 u-mb-18">
                <span class="rating-panel__value u-border-success-60 u-bg-success-12 u-text-success-soft u-fs-38">✓</span>
                <div>
                    <h1 class="u-fs-28 u-m-0">Оплата прошла успешно</h1>
                    <p class="u-m-6-0-0 u-text-muted">
                        <?php if ($isFilms): ?>
                            Купленные фильмы добавлены в раздел «Мои покупки».
                        <?php else: ?>
                            Подписка «<?= e($payment['tariff_name']) ?>» активирована. Чек отправлен на <?= e($user['email']) ?>.
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <?php if ($isFilms): ?>
                <h2 class="u-fs-18">Фильмы в заказе</h2>
                <div class="cart-items u-mb-18">
                    <?php foreach ($items as $item): ?>
                        <div class="summary__row u-p-10-0">
                            <span><?= e($item['title_name']) ?><?= (int)$item['quantity'] > 1 ? ' × ' . (int)$item['quantity'] : '' ?></span>
                            <strong><?= e(price((float)$item['price'] * (int)$item['quantity'])) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="summary u-pos-static">
                <div class="summary__row"><span>Номер заказа</span><strong><?= e($payment['order_number']) ?></strong></div>
                <div class="summary__row"><span>Тип заказа</span><strong><?= $isFilms ? 'Покупка фильмов' : 'Подписка' ?></strong></div>
                <?php if (!$isFilms): ?>
                    <div class="summary__row"><span>Тариф</span><strong><?= e($payment['tariff_name']) ?></strong></div>
                    <div class="summary__row"><span>Срок подписки</span><strong><?= (int)$payment['period_days'] ?> дней</strong></div>
                <?php endif; ?>
                <div class="summary__row"><span>Способ оплаты</span><strong><?= e(match ($payment['method']) {
                    'card' => 'Банковская карта' . ($payment['card_mask'] ? ' ' . $payment['card_mask'] : ''),
                    'sbp' => 'СБП',
                    default => 'Электронный кошелёк',
                }) ?></strong></div>
                <?php if ((float)$payment['discount'] > 0): ?>
                    <div class="summary__row"><span>Скидка (<?= e($payment['promo_code']) ?>)</span><strong>−<?= e(price((float)$payment['discount'])) ?></strong></div>
                <?php endif; ?>
                <div class="summary__row"><span>Дата оплаты</span><strong><?= e(date('d.m.Y H:i', strtotime($payment['paid_at'] ?? $payment['created_at']))) ?></strong></div>
                <div class="summary__total"><span>Сумма</span><strong><?= e(price((float)$payment['total'])) ?></strong></div>
            </div>

            <div class="form-actions u-mt-26">
                <a class="btn btn--primary" href="<?= url('cabinet.php?tab=' . ($isFilms ? 'purchases' : 'subscription')) ?>">
                    <?= $isFilms ? 'Мои покупки' : 'Моя подписка' ?>
                </a>
                <a class="btn btn--outline" href="<?= url('catalog.php') ?>">Начать просмотр</a>
            </div>
        </div>

        <aside class="checkout-card">
            <h2><?= $isFilms ? 'Что доступно сейчас' : 'Ваша подписка' ?></h2>

            <?php if ($isFilms): ?>
                <p class="summary__note u-mt-0">
                    Купленные фильмы доступны без подписки и остаются в аккаунте навсегда.
                    Смотреть можно с любого устройства после входа.
                </p>
                <a class="btn btn--ghost btn--block" href="<?= url('cabinet.php?tab=purchases') ?>">Перейти к покупкам</a>
            <?php else: ?>
                <?php if ($subscription): ?>
                    <div class="summary__row"><span>Тариф</span><strong><?= e($subscription['tariff_name']) ?></strong></div>
                    <div class="summary__row"><span>Действует до</span><strong><?= e(date('d.m.Y', strtotime($subscription['ends_at']))) ?></strong></div>
                    <div class="summary__row"><span>Качество</span><strong><?= e($subscription['quality']) ?></strong></div>
                <?php endif; ?>

                <p class="summary__note u-mt-16">
                    Подписка даёт доступ ко всем фильмам и сериалам каталога,
                    просмотр без рекламы и персональные рекомендации.
                </p>

                <a class="btn btn--ghost btn--block u-mt-12" href="<?= url('movies.php') ?>">Смотреть фильмы</a>
                <a class="btn btn--ghost btn--block u-mt-10" href="<?= url('series.php') ?>">Смотреть сериалы</a>
            <?php endif; ?>
        </aside>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
