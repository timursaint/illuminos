<?php
/**
 * admin/_nav.php — боковое меню админ-панели.
 * Ожидает переменную $adminTab с активным разделом.
 */
$adminTab = $adminTab ?? '';

$counts = [
    'titles'   => (int)db()->query('SELECT COUNT(*) FROM titles')->fetchColumn(),
    'genres'   => (int)db()->query('SELECT COUNT(*) FROM genres')->fetchColumn(),
    'users'    => (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'payments' => (int)db()->query('SELECT COUNT(*) FROM payments WHERE status = "pending"')->fetchColumn(),
];
?>
<aside class="admin-side">
    <div class="admin-side__title">Управление сервисом</div>
    <nav class="admin-nav" aria-label="Разделы админ-панели">
        <a class="<?= $adminTab === 'dashboard' ? 'is-active' : '' ?>" href="<?= url('admin/index.php') ?>">
            <span>Дашборд</span>
        </a>
        <a class="<?= $adminTab === 'titles' ? 'is-active' : '' ?>" href="<?= url('admin/titles.php') ?>">
            <span>Фильмы и сериалы</span><span class="count"><?= $counts['titles'] ?></span>
        </a>
        <a class="<?= $adminTab === 'genres' ? 'is-active' : '' ?>" href="<?= url('admin/genres.php') ?>">
            <span>Категории</span><span class="count"><?= $counts['genres'] ?></span>
        </a>
        <a class="<?= $adminTab === 'payments' ? 'is-active' : '' ?>" href="<?= url('admin/payments.php') ?>">
            <span>Заказы и оплаты</span><span class="count"><?= $counts['payments'] ?></span>
        </a>
        <a class="<?= $adminTab === 'users' ? 'is-active' : '' ?>" href="<?= url('admin/users.php') ?>">
            <span>Пользователи</span><span class="count"><?= $counts['users'] ?></span>
        </a>
        <a class="<?= $adminTab === 'promocodes' ? 'is-active' : '' ?>" href="<?= url('admin/promocodes.php') ?>">
            <span>Промокоды</span>
        </a>
        <a href="<?= url('index.php') ?>"><span>← На сайт</span></a>
    </nav>
</aside>
