<?php
/**
 * admin/promocodes.php — промокоды: добавление, включение/отключение, удаление.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

require_admin();

$errors = [];

/* ---------- Добавление ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $errors[] = 'Сессия устарела.';
    } else {
        $code    = mb_strtoupper(trim((string)($_POST['code'] ?? '')));
        $percent = (int)($_POST['discount_percent'] ?? 0);
        $until   = trim((string)($_POST['valid_until'] ?? ''));
        $limit   = max(1, (int)($_POST['usage_limit'] ?? 1));

        if (!preg_match('/^[A-Z0-9_-]{3,30}$/', $code)) {
            $errors[] = 'Код: 3–30 символов, латиница, цифры, «_» и «-».';
        }
        if ($percent < 1 || $percent > 90) {
            $errors[] = 'Скидка должна быть от 1 до 90%.';
        }

        if (!$errors) {
            $stmt = db()->prepare('SELECT id FROM promocodes WHERE code = ? LIMIT 1');
            $stmt->execute([$code]);
            if ($stmt->fetch()) {
                $errors[] = 'Такой промокод уже существует.';
            } else {
                db()->prepare(
                    'INSERT INTO promocodes (code, discount_percent, valid_until, usage_limit) VALUES (?, ?, ?, ?)'
                )->execute([$code, $percent, $until !== '' ? $until : null, $limit]);
                flash('Промокод создан.', 'success');
                redirect(url('admin/promocodes.php'));
            }
        }
    }
}

/* ---------- Переключение активности ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    if (csrf_valid($_POST['csrf'] ?? null)) {
        db()->prepare('UPDATE promocodes SET is_active = 1 - is_active WHERE id = ?')
            ->execute([(int)($_POST['id'] ?? 0)]);
        flash('Статус промокода изменён.', 'success');
    }
    redirect(url('admin/promocodes.php'));
}

/* ---------- Удаление ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (csrf_valid($_POST['csrf'] ?? null)) {
        db()->prepare('DELETE FROM promocodes WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        flash('Промокод удалён.', 'success');
    }
    redirect(url('admin/promocodes.php'));
}

$promocodes = db()->query('SELECT * FROM promocodes ORDER BY id DESC')->fetchAll();

$adminTab   = 'promocodes';
$page_title = 'Промокоды — админ-панель';
require __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="admin-layout">
        <?php require __DIR__ . '/_nav.php'; ?>

        <div class="admin-main">
            <div class="admin-head">
                <div>
                    <h1>Промокоды</h1>
                    <p>Скидки на подписку, которые вводятся на странице оплаты</p>
                </div>
            </div>

            <?php foreach ($errors as $error): ?>
                <div class="alert alert--error"><?= e($error) ?></div>
            <?php endforeach; ?>

            <form class="admin-form u-mb-24" method="post" action="<?= url('admin/promocodes.php') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <h2 class="u-fs-18">Новый промокод</h2>

                <div class="form-grid">
                    <div class="field">
                        <label class="field__label" for="code">Код</label>
                        <input class="field__input" id="code" name="code" placeholder="SUMMER25" required>
                    </div>
                    <div class="field">
                        <label class="field__label" for="discount_percent">Скидка, %</label>
                        <input class="field__input" type="number" id="discount_percent" name="discount_percent"
                               min="1" max="90" value="15" required>
                    </div>
                    <div class="field">
                        <label class="field__label" for="valid_until">Действует до</label>
                        <input class="field__input" type="date" id="valid_until" name="valid_until">
                    </div>
                    <div class="field">
                        <label class="field__label" for="usage_limit">Лимит использований</label>
                        <input class="field__input" type="number" id="usage_limit" name="usage_limit" min="1" value="100">
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn btn--primary" type="submit">Создать промокод</button>
                </div>
            </form>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                    <tr><th>Код</th><th>Скидка</th><th>Действует до</th><th>Использовано</th><th>Статус</th><th>Действия</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($promocodes as $promo): ?>
                        <tr>
                            <td><strong><?= e($promo['code']) ?></strong></td>
                            <td><?= (int)$promo['discount_percent'] ?>%</td>
                            <td><?= $promo['valid_until'] ? e(date('d.m.Y', strtotime($promo['valid_until']))) : 'бессрочно' ?></td>
                            <td><?= (int)$promo['used_count'] ?> / <?= (int)$promo['usage_limit'] ?></td>
                            <td>
                                <span class="status-chip status-chip--<?= $promo['is_active'] ? 'active' : 'blocked' ?>">
                                    <?= $promo['is_active'] ? 'Активен' : 'Отключён' ?>
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <form method="post" action="<?= url('admin/promocodes.php') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int)$promo['id'] ?>">
                                        <button class="icon-btn" type="submit" title="Вкл/выкл">⏻</button>
                                    </form>
                                    <form method="post" action="<?= url('admin/promocodes.php') ?>"
                                          onsubmit="return confirm('Удалить промокод <?= e($promo['code']) ?>?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$promo['id'] ?>">
                                        <button class="icon-btn icon-btn--danger" type="submit" title="Удалить">🗑</button>
                                    </form>
                                </div>
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
