<?php
/**
 * admin/index.php — дашборд администратора.
 * Ключевые показатели сервиса, последние заказы и самые просматриваемые тайтлы.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

require_admin();

$stats = [
    'titles'      => (int)db()->query('SELECT COUNT(*) FROM titles')->fetchColumn(),
    'movies'      => (int)db()->query('SELECT COUNT(*) FROM titles WHERE type = "movie"')->fetchColumn(),
    'series'      => (int)db()->query('SELECT COUNT(*) FROM titles WHERE type = "series"')->fetchColumn(),
    'users'       => (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'subscribers' => (int)db()->query('SELECT COUNT(*) FROM users WHERE role = "subscriber"')->fetchColumn(),
    'revenue'     => (float)db()->query('SELECT COALESCE(SUM(total), 0) FROM payments WHERE status = "paid"')->fetchColumn(),
    'pending'     => (int)db()->query('SELECT COUNT(*) FROM payments WHERE status = "pending"')->fetchColumn(),
    'reviews'     => (int)db()->query('SELECT COUNT(*) FROM reviews')->fetchColumn(),
    'views'       => (int)db()->query('SELECT COALESCE(SUM(views), 0) FROM titles')->fetchColumn(),
];

$recentPayments = db()->query(
    'SELECT p.*, u.login, t.name AS tariff_name
       FROM payments p
       JOIN users u ON u.id = p.user_id
       JOIN tariffs t ON t.id = p.tariff_id
      ORDER BY p.created_at DESC
      LIMIT 6'
)->fetchAll();

$topTitles = db()->query(
    'SELECT title, type, views, rating, slug FROM titles ORDER BY views DESC LIMIT 6'
)->fetchAll();

$adminTab   = 'dashboard';
$page_title = 'Админ-панель';
require __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="admin-layout">
        <?php require __DIR__ . '/_nav.php'; ?>

        <div class="admin-main">
            <div class="admin-head">
                <div>
                    <h1>Дашборд</h1>
                    <p>Сводка по каталогу, пользователям и платежам</p>
                </div>
                <a class="btn btn--primary btn--sm" href="<?= url('admin/title-form.php') ?>">+ Добавить тайтл</a>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card__label">Тайтлов в каталоге</div>
                    <div class="stat-card__value"><?= $stats['titles'] ?>
                        <small><?= $stats['movies'] ?> фильмов / <?= $stats['series'] ?> сериалов</small>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__label">Пользователей</div>
                    <div class="stat-card__value"><?= $stats['users'] ?>
                        <small><?= $stats['subscribers'] ?> с подпиской</small>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__label">Выручка (оплачено)</div>
                    <div class="stat-card__value"><?= e(price($stats['revenue'])) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__label">Заказы в ожидании</div>
                    <div class="stat-card__value"><?= $stats['pending'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__label">Отзывов и оценок</div>
                    <div class="stat-card__value"><?= $stats['reviews'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__label">Просмотров карточек</div>
                    <div class="stat-card__value"><?= number_format($stats['views'], 0, '.', ' ') ?></div>
                </div>
            </div>

            <div class="admin-head u-mt-10">
                <div>
                    <h2 class="u-fs-20 u-m-0">Последние заказы</h2>
                </div>
                <a class="section__link" href="<?= url('admin/payments.php') ?>">Все заказы →</a>
            </div>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>Заказ</th><th>Пользователь</th><th>Тариф</th><th>Сумма</th><th>Дата</th><th>Статус</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentPayments as $payment): ?>
                        <tr>
                            <td><?= e($payment['order_number']) ?></td>
                            <td><?= e($payment['login']) ?></td>
                            <td><?= e($payment['tariff_name']) ?></td>
                            <td><?= e(price((float)$payment['total'])) ?></td>
                            <td><?= e(date('d.m.Y', strtotime($payment['created_at']))) ?></td>
                            <td><span class="status-chip status-chip--<?= e($payment['status']) ?>">
                                <?= e(match ($payment['status']) {
                                    'paid' => 'Оплачен', 'pending' => 'Ожидает',
                                    'cancelled' => 'Отменён', default => 'Возврат',
                                }) ?>
                            </span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="admin-head u-mt-28">
                <div><h2 class="u-fs-20 u-m-0">Самые просматриваемые</h2></div>
                <a class="section__link" href="<?= url('admin/titles.php') ?>">Управление каталогом →</a>
            </div>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Тайтл</th><th>Тип</th><th>Просмотры</th><th>Рейтинг</th></tr></thead>
                    <tbody>
                    <?php foreach ($topTitles as $row): ?>
                        <tr>
                            <td><a href="<?= url('title.php?slug=' . urlencode($row['slug'])) ?>"><?= e($row['title']) ?></a></td>
                            <td><?= $row['type'] === 'series' ? 'Сериал' : 'Фильм' ?></td>
                            <td><?= number_format((int)$row['views'], 0, '.', ' ') ?></td>
                            <td>★ <?= number_format((float)$row['rating'], 1, '.', '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
