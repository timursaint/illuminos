<?php
/**
 * admin/genres.php — управление категориями (жанрами):
 * добавление, редактирование, удаление.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/catalog.php';

require_admin();

$errors = [];

/* ---------- Добавление ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $errors[] = 'Сессия устарела.';
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $slug = trim((string)($_POST['slug'] ?? ''));
        $slug = $slug !== '' ? slugify($slug) : slugify($name);

        if (mb_strlen($name) < 2) {
            $errors[] = 'Название категории — минимум 2 символа.';
        }

        if (!$errors) {
            $stmt = db()->prepare('SELECT id FROM genres WHERE slug = ? LIMIT 1');
            $stmt->execute([$slug]);
            if ($stmt->fetch()) {
                $errors[] = 'Категория с таким адресом уже существует.';
            } else {
                db()->prepare('INSERT INTO genres (name, slug, icon, description) VALUES (?, ?, ?, ?)')
                    ->execute([
                        $name,
                        $slug,
                        trim((string)($_POST['icon'] ?? '')) ?: null,
                        trim((string)($_POST['description'] ?? '')) ?: null,
                    ]);
                flash('Категория добавлена.', 'success');
                redirect(url('admin/genres.php'));
            }
        }
    }
}

/* ---------- Обновление ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        flash('Сессия устарела.', 'error');
        redirect(url('admin/genres.php'));
    }

    $id = (int)($_POST['id'] ?? 0);
    db()->prepare('UPDATE genres SET name = ?, icon = ?, description = ? WHERE id = ?')
        ->execute([
            trim((string)($_POST['name'] ?? '')),
            trim((string)($_POST['icon'] ?? '')) ?: null,
            trim((string)($_POST['description'] ?? '')) ?: null,
            $id,
        ]);
    flash('Категория обновлена.', 'success');
    redirect(url('admin/genres.php'));
}

/* ---------- Удаление ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        flash('Сессия устарела.', 'error');
    } else {
        db()->prepare('DELETE FROM genres WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        flash('Категория удалена.', 'success');
    }
    redirect(url('admin/genres.php'));
}

$genres = genres_all();
$icons  = array_map('basename', glob(__DIR__ . '/../assets/img/categories/*.svg') ?: []);

$adminTab   = 'genres';
$page_title = 'Категории — админ-панель';
require __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="admin-layout">
        <?php require __DIR__ . '/_nav.php'; ?>

        <div class="admin-main">
            <div class="admin-head">
                <div>
                    <h1>Категории</h1>
                    <p>Жанры каталога: <?= count($genres) ?> шт. Удаление жанра убирает его из карточек тайтлов.</p>
                </div>
            </div>

            <?php foreach ($errors as $error): ?>
                <div class="alert alert--error"><?= e($error) ?></div>
            <?php endforeach; ?>

            <!-- Форма добавления -->
            <form class="admin-form u-mb-24" method="post" action="<?= url('admin/genres.php') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <h2 class="u-fs-18">Новая категория</h2>

                <div class="form-grid">
                    <div class="field">
                        <label class="field__label" for="name">Название</label>
                        <input class="field__input" id="name" name="name" placeholder="Документальное" required>
                    </div>
                    <div class="field">
                        <label class="field__label" for="slug">Адрес (slug)</label>
                        <input class="field__input" id="slug" name="slug" placeholder="documentary">
                    </div>
                    <div class="field">
                        <label class="field__label" for="icon">Иконка</label>
                        <select class="field__input" id="icon" name="icon">
                            <option value="">— без иконки —</option>
                            <?php foreach ($icons as $icon): ?>
                                <option value="<?= e($icon) ?>"><?= e($icon) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field__label" for="description">Описание</label>
                        <input class="field__input" id="description" name="description" placeholder="Короткое описание жанра">
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn btn--primary" type="submit">Добавить категорию</button>
                </div>
            </form>

            <!-- Список категорий -->
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                    <tr><th>Категория</th><th>Slug</th><th>Тайтлов</th><th>Описание</th><th>Действия</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($genres as $genre): ?>
                        <tr>
                            <td>
                                <div class="admin-table__title">
                                    <?php if ($genre['icon']): ?>
                                        <img class="u-w-28 u-h-28" src="<?= asset('img/categories/' . $genre['icon']) ?>" alt="">
                                    <?php endif; ?>
                                    <strong><?= e($genre['name']) ?></strong>
                                </div>
                            </td>
                            <td><code><?= e($genre['slug']) ?></code></td>
                            <td><?= (int)$genre['titles_count'] ?></td>
                            <td class="u-text-muted u-fs-13"><?= e($genre['description']) ?></td>
                            <td>
                                <div class="table-actions">
                                    <a class="icon-btn" href="<?= url('catalog.php?genre=' . urlencode($genre['slug'])) ?>"
                                       target="_blank" title="Открыть каталог">↗</a>
                                    <form method="post" action="<?= url('admin/genres.php') ?>"
                                          onsubmit="return confirm('Удалить категорию «<?= e($genre['name']) ?>»?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$genre['id'] ?>">
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
