<?php
/**
 * admin/payments.php — заказы на подписку: просмотр всех платежей
 * и смена статусов (оплачен / ожидает / отменён / возврат).
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

require_admin();

/* ---------- Смена статуса заказа ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'status') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        flash('Сессия устарела.', 'error');
        redirect(url('admin/payments.php'));
    }

    $paymentId = (int)($_POST['id'] ?? 0);
    $status    = (string)($_POST['status'] ?? '');

    if (!in_array($status, ['pending', 'paid', 'cancelled', 'refunded'], true)) {
        flash('Недопустимый статус.', 'error');
        redirect(url('admin/payments.php'));
    }

    $stmt = db()->prepare('SELECT * FROM payments WHERE id = ? LIMIT 1');
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch();

    if (!$payment) {
        flash('Заказ не найден.', 'error');
        redirect(url('admin/payments.php'));
    }

    db()->prepare('UPDATE payments SET status = ?, paid_at = IF(? = "paid", NOW(), paid_at) WHERE id = ?')
        ->execute([$status, $status, $paymentId]);

    $isFilms = ($payment['kind'] ?? 'subscription') === 'films';

    // Если заказ отменён или возвращён — снимаем подписку и доступ к купленным фильмам
    if (in_array($status, ['cancelled', 'refunded'], true)) {
        db()->prepare('UPDATE subscriptions SET status = "cancelled" WHERE payment_id = ?')->execute([$paymentId]);
        db()->prepare('DELETE FROM purchases WHERE payment_id = ?')->execute([$paymentId]);
    }

    // Заказ фильмов: при оплате выдаём доступ, при отмене — забираем
    if ($isFilms && $status === 'paid') {
        $items = db()->prepare('SELECT title_id FROM order_items WHERE payment_id = ?');
        $items->execute([$paymentId]);

        $insert = db()->prepare('INSERT IGNORE INTO purchases (user_id, title_id, payment_id) VALUES (?, ?, ?)');
        foreach ($items->fetchAll(PDO::FETCH_COLUMN) as $titleId) {
            if ($titleId) {
                $insert->execute([$payment['user_id'], (int)$titleId, $paymentId]);
            }
        }
    }

    // Заказ подписки: при оплате создаём подписку, если её ещё нет
    if (!$isFilms && $status === 'paid' && $payment['tariff_id']) {
        $check = db()->prepare('SELECT id FROM subscriptions WHERE payment_id = ? LIMIT 1');
        $check->execute([$paymentId]);

        if (!$check->fetch()) {
            $tariff = db()->prepare('SELECT period_days FROM tariffs WHERE id = ?');
            $tariff->execute([$payment['tariff_id']]);
            $days = (int)$tariff->fetchColumn();

            db()->prepare(
                'INSERT INTO subscriptions (user_id, tariff_id, payment_id, starts_at, ends_at, status)
                 VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), "active")'
            )->execute([$payment['user_id'], $payment['tariff_id'], $paymentId, $days]);

            db()->prepare('UPDATE users SET role = "subscriber" WHERE id = ? AND role = "user"')
                ->execute([$payment['user_id']]);
        }
    }

    flash('Статус заказа обновлён.', 'success');
    redirect(url('admin/payments.php' . (!empty($_POST['back']) ? '?' . $_POST['back'] : '')));
}

/* ---------- Фильтры ---------- */
$status = (string)($_GET['status'] ?? '');
$q      = trim((string)($_GET['q'] ?? ''));

$where  = [];
$params = [];
if (in_array($status, ['pending', 'paid', 'cancelled', 'refunded'], true)) {
    $where[] = 'p.status = :status';
    $params['status'] = $status;
}
if ($q !== '') {
    $where[] = '(p.order_number LIKE :q1 OR u.login LIKE :q2 OR u.email LIKE :q3)';
    $like = '%' . $q . '%';
    $params['q1'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = db()->prepare(
    "SELECT p.*, u.login, u.email, t.name AS tariff_name,
              (SELECT COUNT(*) FROM order_items oi WHERE oi.payment_id = p.id) AS items_count
       FROM payments p
       JOIN users u ON u.id = p.user_id
       LEFT JOIN tariffs t ON t.id = p.tariff_id
       {$whereSql}
      ORDER BY p.created_at DESC
      LIMIT 100"
);
$stmt->execute($params);
$payments = $stmt->fetchAll();

$totals = db()->query(
    'SELECT status, COUNT(*) AS cnt, COALESCE(SUM(total), 0) AS sum FROM payments GROUP BY status'
)->fetchAll();

$byStatus = [];
foreach ($totals as $row) {
    $byStatus[$row['status']] = $row;
}

$adminTab   = 'payments';
$page_title = 'Заказы и оплаты — админ-панель';
require __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="admin-layout">
        <?php require __DIR__ . '/_nav.php'; ?>

        <div class="admin-main">
            <div class="admin-head">
                <div>
                    <h1>Заказы и оплаты</h1>
                    <p>Все заказы подписок: просмотр и смена статусов</p>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card__label">Оплачено</div>
                    <div class="stat-card__value"><?= e(price((float)($byStatus['paid']['sum'] ?? 0))) ?>
                        <small><?= (int)($byStatus['paid']['cnt'] ?? 0) ?> заказов</small>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__label">Ожидает оплаты</div>
                    <div class="stat-card__value"><?= (int)($byStatus['pending']['cnt'] ?? 0) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__label">Отменено</div>
                    <div class="stat-card__value"><?= (int)($byStatus['cancelled']['cnt'] ?? 0) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__label">Возвраты</div>
                    <div class="stat-card__value"><?= (int)($byStatus['refunded']['cnt'] ?? 0) ?></div>
                </div>
            </div>

            <form class="u-d-flex u-gap-10 u-flex-wrap u-mb-18" method="get" action="<?= url('admin/payments.php') ?>">
                <input class="input u-max-w-280" type="search" name="q" value="<?= e($q) ?>"
                       placeholder="Номер заказа, логин, e-mail">
                <select class="select" name="status" onchange="this.form.submit()">
                    <option value="">Все статусы</option>
                    <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Оплачен</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Ожидает</option>
                    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Отменён</option>
                    <option value="refunded" <?= $status === 'refunded' ? 'selected' : '' ?>>Возврат</option>
                </select>
                <button class="btn btn--outline btn--sm" type="submit">Найти</button>
                <?php if ($q || $status): ?>
                    <a class="btn btn--ghost btn--sm" href="<?= url('admin/payments.php') ?>">Сбросить</a>
                <?php endif; ?>
            </form>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>Заказ</th><th>Пользователь</th><th>Состав</th><th>Сумма</th>
                        <th>Способ</th><th>Дата</th><th>Статус</th><th>Изменить</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><strong><?= e($payment['order_number']) ?></strong></td>
                            <td>
                                <?= e($payment['login']) ?><br>
                                <span class="u-text-muted u-fs-12"><?= e($payment['email']) ?></span>
                            </td>
                            <td>
                                <?php if (($payment['kind'] ?? 'subscription') === 'films'): ?>
                                    Фильмы
                                    <br><span class="u-text-muted u-fs-12">
                                        <?= e(plural((int)$payment['items_count'], 'фильм', 'фильма', 'фильмов')) ?>
                                    </span>
                                <?php else: ?>
                                    Подписка
                                    <br><span class="u-text-muted u-fs-12"><?= e($payment['tariff_name']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e(price((float)$payment['total'])) ?>
                                <?php if ((float)$payment['discount'] > 0): ?>
                                    <br><span class="u-text-success u-fs-12">
                                        скидка <?= e($payment['promo_code']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e(match ($payment['method']) {
                                    'card' => 'Карта',
                                    'sbp' => 'СБП',
                                    default => 'Кошелёк',
                                }) ?>
                                <?php if ($payment['card_mask']): ?>
                                    <br><span class="u-text-muted u-fs-12"><?= e($payment['card_mask']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= e(date('d.m.Y H:i', strtotime($payment['created_at']))) ?></td>
                            <td>
                                <span class="status-chip status-chip--<?= e($payment['status']) ?>">
                                    <?= e(match ($payment['status']) {
                                        'paid' => 'Оплачен',
                                        'pending' => 'Ожидает',
                                        'cancelled' => 'Отменён',
                                        default => 'Возврат',
                                    }) ?>
                                </span>
                            </td>
                            <td>
                                <form class="u-d-flex u-gap-6" method="post" action="<?= url('admin/payments.php') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="id" value="<?= (int)$payment['id'] ?>">
                                    <input type="hidden" name="back" value="<?= e(http_build_query(array_filter(['status' => $status, 'q' => $q]))) ?>">
                                    <select class="select u-p-6-8 u-fs-12-5" name="status" aria-label="Статус заказа">
                                        <option value="paid" <?= $payment['status'] === 'paid' ? 'selected' : '' ?>>Оплачен</option>
                                        <option value="pending" <?= $payment['status'] === 'pending' ? 'selected' : '' ?>>Ожидает</option>
                                        <option value="cancelled" <?= $payment['status'] === 'cancelled' ? 'selected' : '' ?>>Отменён</option>
                                        <option value="refunded" <?= $payment['status'] === 'refunded' ? 'selected' : '' ?>>Возврат</option>
                                    </select>
                                    <button class="icon-btn" type="submit" title="Сохранить статус">✓</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!$payments): ?>
                <div class="empty-state u-mt-16"><p>Заказы не найдены.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
