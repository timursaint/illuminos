<?php
/**
 * checkout.php — оформление и оплата.
 * Два режима:
 *   ?tariff=month  — подписка (тариф);
 *   ?cart=1        — заказ фильмов из корзины (покупка навсегда).
 * Платёжный шлюз эмулируется: при корректной форме заказ сразу оплачивается.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

require_auth();
$user = current_user();

$mode = (($_GET['cart'] ?? $_POST['cart'] ?? '') === '1') ? 'films' : 'subscription';

$tariff = null;
$cartItems = [];
$baseAmount = 0.0;

if ($mode === 'subscription') {
    $tariffCode = (string)($_GET['tariff'] ?? $_POST['tariff'] ?? '');
    $stmt = db()->prepare('SELECT * FROM tariffs WHERE (code = ? OR id = ?) AND is_active = 1 LIMIT 1');
    $stmt->execute([$tariffCode, (int)$tariffCode]);
    $tariff = $stmt->fetch();

    if (!$tariff) {
        flash('Выберите тариф для оформления подписки.', 'warning');
        redirect(url('pricing.php'));
    }
    $baseAmount = (float)$tariff['price'];
} else {
    $cartItems = cart_items();
    if (!$cartItems) {
        flash('Корзина пуста — добавьте фильмы для покупки.', 'warning');
        redirect(url('cart.php'));
    }
    $baseAmount = cart_total();
}

$errors = [];
$method = 'card';

/* --------------------------- Обработка оплаты --------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay'])) {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $errors[] = 'Сессия устарела. Обновите страницу и попробуйте снова.';
    }

    $method = in_array($_POST['method'] ?? '', ['card', 'sbp', 'wallet'], true) ? $_POST['method'] : 'card';
    $cardMask = null;

    if ($method === 'card') {
        $number = preg_replace('/\D/', '', (string)($_POST['card_number'] ?? ''));
        $expiry = trim((string)($_POST['card_expiry'] ?? ''));
        $cvv    = preg_replace('/\D/', '', (string)($_POST['card_cvv'] ?? ''));

        if (strlen($number) !== 16) {
            $errors[] = 'Номер карты должен содержать 16 цифр.';
        }
        if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiry)) {
            $errors[] = 'Срок действия укажите в формате ММ/ГГ.';
        } else {
            [$mm, $yy] = array_map('intval', explode('/', $expiry));
            if (mktime(0, 0, 0, $mm + 1, 1, 2000 + $yy) < time()) {
                $errors[] = 'Срок действия карты истёк.';
            }
        }
        if (strlen($cvv) !== 3) {
            $errors[] = 'CVV-код должен содержать 3 цифры.';
        }

        $cardMask = '**** ' . substr($number, -4);
    }

    if (empty($_POST['agree'])) {
        $errors[] = 'Подтвердите согласие с условиями покупки.';
    }

    // Промокод проверяем на сервере
    $promoCode = mb_strtoupper(trim((string)($_POST['promo_code'] ?? '')));
    $promo = null;
    if ($promoCode !== '') {
        $stmt = db()->prepare(
            'SELECT * FROM promocodes
              WHERE code = ? AND is_active = 1
                AND (valid_until IS NULL OR valid_until >= CURDATE())
                AND used_count < usage_limit
              LIMIT 1'
        );
        $stmt->execute([$promoCode]);
        $promo = $stmt->fetch() ?: null;

        if (!$promo) {
            $errors[] = 'Промокод недействителен или уже использован.';
        }
    }

    if (!$errors) {
        $amount   = $baseAmount;
        $discount = $promo ? round($amount * (int)$promo['discount_percent'] / 100, 2) : 0.0;
        $total    = max(0, $amount - $discount);

        $nextId = (int)db()->query('SELECT COALESCE(MAX(id), 0) + 1 FROM payments')->fetchColumn();
        $orderNumber = sprintf('IL-%s-%05d', date('Y'), $nextId);

        db()->beginTransaction();
        try {
            // Эмуляция успешного ответа платёжного шлюза
            $stmt = db()->prepare(
                'INSERT INTO payments (order_number, kind, user_id, tariff_id, amount, discount, total, method,
                                       status, promo_code, card_mask, paid_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, "paid", ?, ?, NOW())'
            );
            $stmt->execute([
                $orderNumber,
                $mode === 'films' ? 'films' : 'subscription',
                $user['id'],
                $tariff['id'] ?? null,
                $amount,
                $discount,
                $total,
                $method,
                $promo['code'] ?? null,
                $cardMask,
            ]);
            $paymentId = (int)db()->lastInsertId();

            if ($mode === 'subscription') {
                // Подписка: если уже есть активная — продлеваем от её конца
                $existing = active_subscription((int)$user['id']);
                $startsAt = $existing
                    ? date('Y-m-d', strtotime($existing['ends_at'] . ' +1 day'))
                    : date('Y-m-d');
                $endsAt = date('Y-m-d', strtotime($startsAt . ' +' . (int)$tariff['period_days'] . ' days'));

                db()->prepare(
                    'INSERT INTO subscriptions (user_id, tariff_id, payment_id, starts_at, ends_at, status)
                     VALUES (?, ?, ?, ?, ?, "active")'
                )->execute([$user['id'], $tariff['id'], $paymentId, $startsAt, $endsAt]);

                if ($user['role'] === 'user') {
                    db()->prepare('UPDATE users SET role = "subscriber" WHERE id = ?')->execute([$user['id']]);
                }
            } else {
                // Покупка фильмов: позиции заказа и доступ к просмотру
                $insertItem = db()->prepare(
                    'INSERT INTO order_items (payment_id, title_id, title_name, price, quantity)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $insertPurchase = db()->prepare(
                    'INSERT IGNORE INTO purchases (user_id, title_id, payment_id) VALUES (?, ?, ?)'
                );

                foreach ($cartItems as $item) {
                    $insertItem->execute([
                        $paymentId,
                        (int)$item['title_id'],
                        $item['title'],
                        (float)$item['price'],
                        (int)$item['quantity'],
                    ]);
                    $insertPurchase->execute([$user['id'], (int)$item['title_id'], $paymentId]);
                }

                db()->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([$user['id']]);
            }

            if ($promo) {
                db()->prepare('UPDATE promocodes SET used_count = used_count + 1 WHERE id = ?')->execute([$promo['id']]);
            }

            db()->commit();
        } catch (Throwable $exception) {
            db()->rollBack();
            $errors[] = 'Не удалось провести платёж: ' . $exception->getMessage();
        }

        if (!$errors) {
            flash($mode === 'films' ? 'Оплата прошла успешно. Фильмы добавлены в покупки!' : 'Оплата прошла успешно. Подписка активирована!', 'success');
            redirect(url('payment-success.php?order=' . urlencode($orderNumber)));
        }
    }
}

$page_title = $mode === 'films' ? 'Оплата фильмов' : 'Оплата подписки';
$page_scripts = ['checkout.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="page-head">
        <nav class="breadcrumbs" aria-label="Хлебные крошки">
            <a href="<?= url('index.php') ?>">Главная</a><span>/</span>
            <?php if ($mode === 'films'): ?>
                <a href="<?= url('cart.php') ?>">Корзина</a><span>/</span><span>Оплата</span>
            <?php else: ?>
                <a href="<?= url('pricing.php') ?>">Тарифы</a><span>/</span><span>Оплата</span>
            <?php endif; ?>
        </nav>
        <h1><?= $mode === 'films' ? 'Оплата фильмов' : 'Оплата подписки' ?></h1>
        <p>
            <?php if ($mode === 'films'): ?>
                <?= plural(count($cartItems), 'фильм', 'фильма', 'фильмов') ?> из корзины ·
                <?= e(price($baseAmount)) ?>
            <?php else: ?>
                Тариф «<?= e($tariff['name']) ?>» · <?= (int)$tariff['period_days'] ?> дней · <?= e($tariff['quality']) ?>
            <?php endif; ?>
        </p>
    </div>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert--error"><?= e($error) ?></div>
    <?php endforeach; ?>

    <!-- ТЕСТОВЫЙ РЕЖИМ: реальный платёжный шлюз не подключён, оплата эмулируется -->
    <div class="alert alert--info u-mb-18">
        <strong>Тестовый режим оплаты.</strong> Платёжный шлюз эмулируется: реальные деньги
        не списываются, достаточно тестовой карты <b>4111 1111 1111 1111</b>, срок <b>12/30</b>, CVV <b>123</b>.
    </div>

    <div class="checkout">
        <!-- ================= Форма оплаты ================= -->
        <form class="checkout-card" method="post"
              action="<?= $mode === 'films'
                    ? url('checkout.php?cart=1')
                    : url('checkout.php?tariff=' . urlencode($tariff['code'])) ?>"
              id="checkoutForm"
              data-tariff-id="<?= (int)($tariff['id'] ?? 0) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="pay" value="1">
            <?php if ($mode === 'films'): ?>
                <input type="hidden" name="cart" value="1">
            <?php else: ?>
                <input type="hidden" name="tariff" value="<?= e($tariff['code']) ?>">
            <?php endif; ?>

            <h2>1. Способ оплаты</h2>
            <div class="pay-methods-grid">
                <label class="pay-method">
                    <input type="radio" name="method" value="card" <?= $method === 'card' ? 'checked' : '' ?>>
                    <span><strong>Банковская карта</strong>МИР, Visa, Mastercard</span>
                </label>
                <label class="pay-method">
                    <input type="radio" name="method" value="sbp" <?= $method === 'sbp' ? 'checked' : '' ?>>
                    <span><strong>СБП</strong>по QR-коду</span>
                </label>
                <label class="pay-method">
                    <input type="radio" name="method" value="wallet" <?= $method === 'wallet' ? 'checked' : '' ?>>
                    <span><strong>Кошелёк</strong>ЮMoney и другие</span>
                </label>
            </div>

            <div id="cardFields">
                <h2>2. Данные карты</h2>
                <div class="form-grid">
                    <div class="field field--full">
                        <label class="field__label" for="card_number">Номер карты</label>
                        <input class="field__input" type="text" id="card_number" name="card_number"
                               placeholder="0000 0000 0000 0000" inputmode="numeric" maxlength="19"
                               value="<?= e($_POST['card_number'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label class="field__label" for="card_expiry">Срок действия</label>
                        <input class="field__input" type="text" id="card_expiry" name="card_expiry"
                               placeholder="ММ/ГГ" maxlength="5" value="<?= e($_POST['card_expiry'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label class="field__label" for="card_cvv">CVV-код</label>
                        <input class="field__input" type="password" id="card_cvv" name="card_cvv"
                               placeholder="***" maxlength="3" inputmode="numeric">
                    </div>
                </div>
                <p class="form-note">
                    Демо-режим: используйте тестовую карту <b>4111 1111 1111 1111</b>,
                    срок <b>12/30</b>, CVV <b>123</b>. Данные карты не сохраняются — только маска номера.
                </p>
            </div>

            <h2 class="u-mt-26">3. Подтверждение</h2>
            <div class="field">
                <label class="field__label" for="promo_code">Промокод</label>
                <div class="promo-row">
                    <input class="input" type="text" id="promo_code" name="promo_code"
                           placeholder="Например, START15" value="<?= e($_POST['promo_code'] ?? '') ?>">
                    <button class="btn btn--outline" type="button" id="applyPromo">Применить</button>
                </div>
                <div class="promo-msg" id="promoMsg"></div>
            </div>

            <label class="checkbox u-mb-18">
                <input type="checkbox" name="agree" value="1" <?= !empty($_POST['agree']) ? 'checked' : '' ?>>
                <span><?= $mode === 'films'
                        ? 'Я согласен с условиями покупки фильмов'
                        : 'Я согласен с условиями подписки и правилами автоматического продления' ?></span>
            </label>

            <button class="btn btn--primary btn--block" type="submit">
                Оплатить <span id="payButtonSum"><?= e(price($baseAmount)) ?></span>
            </button>
        </form>

        <!-- ================= Итог заказа ================= -->
        <aside class="checkout-card summary">
            <h2>Ваш заказ</h2>

            <?php if ($mode === 'films'): ?>
                <?php foreach ($cartItems as $item): ?>
                    <div class="summary__row">
                        <span><?= e($item['title']) ?><?= (int)$item['quantity'] > 1 ? ' × ' . (int)$item['quantity'] : '' ?></span>
                        <strong><?= e(price((float)$item['price'] * (int)$item['quantity'])) ?></strong>
                    </div>
                <?php endforeach; ?>
                <div class="summary__row"><span>Стоимость</span><strong data-base-sum="<?= $baseAmount ?>"><?= e(price($baseAmount)) ?></strong></div>
            <?php else: ?>
                <div class="summary__row"><span>Тариф</span><strong><?= e($tariff['name']) ?></strong></div>
                <div class="summary__row"><span>Срок</span><strong><?= (int)$tariff['period_days'] ?> дней</strong></div>
                <div class="summary__row"><span>Качество</span><strong><?= e($tariff['quality']) ?></strong></div>
                <div class="summary__row"><span>Устройств</span><strong><?= (int)$tariff['devices'] ?></strong></div>
                <div class="summary__row"><span>Стоимость</span><strong data-base-sum="<?= $baseAmount ?>"><?= e(price($baseAmount)) ?></strong></div>
            <?php endif; ?>

            <div class="summary__row" id="discountRow" hidden><span>Скидка по промокоду</span><strong id="discountValue">—</strong></div>

            <div class="summary__total">
                <span>Итого</span>
                <strong id="totalValue"><?= e(price($baseAmount)) ?></strong>
            </div>

            <p class="summary__note">
                Оплата проходит на стороне платёжного шлюза. В учебной версии шлюз эмулируется:
                после нажатия «Оплатить» заказ сразу переходит в статус «Оплачен».
            </p>
        </aside>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
