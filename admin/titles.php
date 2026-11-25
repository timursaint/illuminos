<?php
/**
 * admin/titles.php — список фильмов и сериалов с поиском,
 * удалением и быстрым переходом к редактированию.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/catalog.php';

require_admin();

// Удаление тайтла (каскадно удаляются жанры, сезоны, избранное)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        flash('Сессия устарела.', 'error');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM titles WHERE id = ?')->execute([$id]);
        flash('Тайтл удалён из каталога.', 'success');
    }
    redirect(url('admin/titles.php'));
}

$q    = trim((string)($_GET['q'] ?? ''));
$type = (string)($_GET['type'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

$result = search_titles([
    'q' => $q, 'type' => $type, 'page' => $page, 'per_page' => 12, 'sort' => 'new',
]);

$adminTab   = 'titles';
$page_title = 'Каталог — админ-панель';
require __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="admin-layout">
        <?php require __DIR__ . '/_nav.php'; ?>

        <div class="admin-main">
            <div class="admin-head">
                <div>
                    <h1>Фильмы и сериалы</h1>
                    <p>Добавление, редактирование и удаление тайтлов — <?= $result['total'] ?> записей</p>
                </div>
                <a class="btn btn--primary btn--sm" href="<?= url('admin/title-form.php') ?>">+ Добавить тайтл</a>
            </div>

            <form class="u-d-flex u-gap-10 u-flex-wrap u-mb-18" method="get" action="<?= url('admin/titles.php') ?>">
                <input class="input u-max-w-320" type="search" name="q" value="<?= e($q) ?>"
                       placeholder="Поиск по названию, актёру, режиссёру">
                <select class="select" name="type" onchange="this.form.submit()">
                    <option value="">Все типы</option>
                    <option value="movie" <?= $type === 'movie' ? 'selected' : '' ?>>Фильмы</option>
                    <option value="series" <?= $type === 'series' ? 'selected' : '' ?>>Сериалы</option>
                </select>
                <button class="btn btn--outline btn--sm" type="submit">Найти</button>
                <?php if ($q || $type): ?>
                    <a class="btn btn--ghost btn--sm" href="<?= url('admin/titles.php') ?>">Сбросить</a>
                <?php endif; ?>
            </form>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>Тайтл</th><th>Тип</th><th>Жанры</th><th>Год</th>
                        <th>Рейтинг</th><th>Просмотры</th><th>Действия</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($result['items'] as $item): ?>
                        <tr>
                            <td>
                                <div class="admin-table__title">
                                    <img class="admin-table__poster" src="<?= poster_src($item) ?>" alt="">
                                    <div>
                                        <strong><?= e($item['title']) ?></strong>
                                        <span><?= e(implode(', ', $item['genres'])) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><?= $item['type'] === 'series' ? 'Сериал' : 'Фильм' ?></td>
                            <td><?= e(implode(', ', array_slice($item['genres'], 0, 2))) ?></td>
                            <td><?= e((string)$item['year']) ?></td>
                            <td>★ <?= number_format((float)$item['rating'], 1, '.', '') ?></td>
                            <td><?= number_format((int)$item['views'], 0, '.', ' ') ?></td>
                            <td>
                                <div class="table-actions">
                                    <a class="icon-btn" href="<?= url('admin/title-form.php?id=' . (int)$item['id']) ?>"
                                       title="Редактировать">✎</a>
                                    <a class="icon-btn" href="<?= url('title.php?slug=' . urlencode($item['slug'])) ?>"
                                       title="Открыть на сайте" target="_blank">↗</a>
                                    <form method="post" action="<?= url('admin/titles.php') ?>"
                                          onsubmit="return confirm('Удалить «<?= e($item['title']) ?>»? Действие необратимо.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                        <button class="icon-btn icon-btn--danger" type="submit" title="Удалить">🗑</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($result['pages'] > 1): ?>
                <nav class="pagination">
                    <?php for ($p = 1; $p <= $result['pages']; $p++): ?>
                        <?php if ($p === $result['page']): ?>
                            <span class="is-active"><?= $p ?></span>
                        <?php else: ?>
                            <a href="<?= url('admin/titles.php?page=' . $p . ($q ? '&q=' . urlencode($q) : '') . ($type ? '&type=' . $type : '')) ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
