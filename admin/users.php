<?php
/**
 * admin/users.php — пользователи сервиса: роли и блокировка.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

require_admin();
$current = current_user();

/* ---------- Смена роли ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'role') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        flash('Сессия устарела.', 'error');
        redirect(url('admin/users.php'));
    }

    $userId = (int)($_POST['id'] ?? 0);
    $role   = (string)($_POST['role'] ?? '');

    if ($userId === (int)$current['id']) {
        flash('Нельзя изменить собственную роль.', 'error');
    } elseif (!in_array($role, ['user', 'subscriber', 'admin'], true)) {
        flash('Недопустимая роль.', 'error');
    } else {
        db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $userId]);
        flash('Роль пользователя обновлена.', 'success');
    }
    redirect(url('admin/users.php'));
}

/* ---------- Блокировка / разблокировка ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'status') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        flash('Сессия устарела.', 'error');
        redirect(url('admin/users.php'));
    }

    $userId = (int)($_POST['id'] ?? 0);
    $status = (string)($_POST['status'] ?? '');

    if ($userId === (int)$current['id']) {
        flash('Нельзя заблокировать себя.', 'error');
    } elseif (!in_array($status, ['active', 'blocked'], true)) {
        flash('Недопустимый статус.', 'error');
    } else {
        db()->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$status, $userId]);
        flash($status === 'blocked' ? 'Пользователь заблокирован.' : 'Пользователь разблокирован.', 'success');
    }
    redirect(url('admin/users.php'));
}

$q = trim((string)($_GET['q'] ?? ''));
$role = (string)($_GET['role'] ?? '');

$where  = [];
$params = [];
if ($q !== '') {
    $where[] = '(login LIKE :q1 OR email LIKE :q2 OR last_name LIKE :q3 OR first_name LIKE :q4)';
    $like = '%' . $q . '%';
    $params['q1'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
    $params['q4'] = $like;
}
if (in_array($role, ['user', 'subscriber', 'admin'], true)) {
    $where[] = 'role = :role';
    $params['role'] = $role;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = db()->prepare(
    "SELECT u.*,
            (SELECT COUNT(*) FROM payments p WHERE p.user_id = u.id AND p.status = 'paid') AS paid_orders,
            (SELECT MAX(ends_at) FROM subscriptions s WHERE s.user_id = u.id AND s.status = 'active') AS sub_until
       FROM users u
       {$whereSql}
      ORDER BY u.created_at DESC"
);
$stmt->execute($params);
$users = $stmt->fetchAll();

$adminTab   = 'users';
$page_title = 'Пользователи — админ-панель';
require __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="admin-layout">
        <?php require __DIR__ . '/_nav.php'; ?>

        <div class="admin-main">
            <div class="admin-head">
                <div>
                    <h1>Пользователи</h1>
                    <p>Всего <?= count($users) ?> аккаунтов. Роли: администратор, подписчик, пользователь.</p>
                </div>
            </div>

            <form class="u-d-flex u-gap-10 u-flex-wrap u-mb-18" method="get" action="<?= url('admin/users.php') ?>">
                <input class="input u-max-w-300" type="search" name="q" value="<?= e($q) ?>"
                       placeholder="Логин, e-mail, имя, фамилия">
                <select class="select" name="role" onchange="this.form.submit()">
                    <option value="">Все роли</option>
                    <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Администраторы</option>
                    <option value="subscriber" <?= $role === 'subscriber' ? 'selected' : '' ?>>Подписчики</option>
                    <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>Пользователи</option>
                </select>
                <button class="btn btn--outline btn--sm" type="submit">Найти</button>
            </form>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>Пользователь</th><th>Контакты</th><th>Регистрация</th>
                        <th>Оплат</th><th>Подписка до</th><th>Роль</th><th>Статус</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $userRow): ?>
                        <tr>
                            <td>
                                <div class="admin-table__title">
                                    <span class="avatar avatar--initials"><?= e(user_initials($userRow)) ?></span>
                                    <div>
                                        <strong><?= e($userRow['last_name'] . ' ' . $userRow['first_name']) ?></strong>
                                        <span>@<?= e($userRow['login']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><?= e($userRow['email']) ?></td>
                            <td><?= e(date('d.m.Y', strtotime($userRow['created_at']))) ?></td>
                            <td><?= (int)$userRow['paid_orders'] ?></td>
                            <td><?= $userRow['sub_until'] ? e(date('d.m.Y', strtotime($userRow['sub_until']))) : '—' ?></td>
                            <td>
                                <form class="u-d-flex u-gap-6" method="post" action="<?= url('admin/users.php') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="role">
                                    <input type="hidden" name="id" value="<?= (int)$userRow['id'] ?>">
                                    <select class="select u-p-6-8 u-fs-12-5" name="role" aria-label="Роль">
                                        <option value="user" <?= $userRow['role'] === 'user' ? 'selected' : '' ?>>Пользователь</option>
                                        <option value="subscriber" <?= $userRow['role'] === 'subscriber' ? 'selected' : '' ?>>Подписчик</option>
                                        <option value="admin" <?= $userRow['role'] === 'admin' ? 'selected' : '' ?>>Администратор</option>
                                    </select>
                                    <button class="icon-btn" type="submit" title="Сохранить роль">✓</button>
                                </form>
                            </td>
                            <td>
                                <form method="post" action="<?= url('admin/users.php') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="id" value="<?= (int)$userRow['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $userRow['status'] === 'active' ? 'blocked' : 'active' ?>">
                                    <button class="status-chip status-chip--<?= e($userRow['status']) ?> u-border-0 u-cursor-pointer"
                                            type="submit"
                                            title="<?= $userRow['status'] === 'active' ? 'Заблокировать' : 'Разблокировать' ?>">
                                        <?= $userRow['status'] === 'active' ? 'Активен' : 'Заблокирован' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
