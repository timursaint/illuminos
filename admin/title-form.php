<?php
/**
 * admin/title-form.php — добавление и редактирование тайтла.
 * Здесь же управляются жанры, постер, трейлер, а для сериалов —
 * сезоны и серии.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/catalog.php';

require_admin();

$id    = (int)($_GET['id'] ?? 0);
$title = $id ? find_title_by_id($id) : null;
$isNew = $title === null;

/** Поиск тайтла по id (для формы редактирования) */
function find_title_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM titles WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

$errors = [];

/* ================== Сохранение тайтла ================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $errors[] = 'Сессия устарела, повторите отправку.';
    }

    $data = [
        'type'           => in_array($_POST['type'] ?? '', ['movie', 'series'], true) ? $_POST['type'] : 'movie',
        'title'          => trim((string)($_POST['title'] ?? '')),
        'original_title' => trim((string)($_POST['original_title'] ?? '')) ?: null,
        'description'    => trim((string)($_POST['description'] ?? '')),
        'year'           => (int)($_POST['year'] ?? 0) ?: null,
        'year_end'       => (int)($_POST['year_end'] ?? 0) ?: null,
        'country'        => trim((string)($_POST['country'] ?? '')) ?: null,
        'duration'       => (int)($_POST['duration'] ?? 0) ?: null,
        'age_limit'      => trim((string)($_POST['age_limit'] ?? '')) ?: null,
        'rating'         => (float)($_POST['rating'] ?? 0),
        'backdrop'       => trim((string)($_POST['backdrop'] ?? '')) ?: null,
        'logo'           => trim((string)($_POST['logo'] ?? '')) ?: null,
        'trailer'        => trim((string)($_POST['trailer'] ?? '')) ?: null,
        'accent_color'   => trim((string)($_POST['accent_color'] ?? '#FF6B35')),
        'badge'          => trim((string)($_POST['badge'] ?? '')) ?: null,
        'director'       => trim((string)($_POST['director'] ?? '')) ?: null,
        'cast_list'      => trim((string)($_POST['cast_list'] ?? '')) ?: null,
        'is_new'         => !empty($_POST['is_new']) ? 1 : 0,
        'is_popular'     => !empty($_POST['is_popular']) ? 1 : 0,
    ];

    if (mb_strlen($data['title']) < 2) {
        $errors[] = 'Укажите название тайтла (минимум 2 символа).';
    }
    if ($data['rating'] < 0 || $data['rating'] > 10) {
        $errors[] = 'Рейтинг должен быть от 0 до 10.';
    }

    // Slug: берём из поля или генерируем из названия
    $slug = trim((string)($_POST['slug'] ?? ''));
    $slug = $slug !== '' ? slugify($slug) : slugify($data['title']);

    $stmt = db()->prepare('SELECT id FROM titles WHERE slug = ? AND id <> ? LIMIT 1');
    $stmt->execute([$slug, $id]);
    if ($stmt->fetch()) {
        $slug .= '-' . time();
    }

    // Загрузка нового постера (необязательно)
    $poster = trim((string)($_POST['poster'] ?? ''));
    if (!empty($_FILES['poster_file']['name']) && $_FILES['poster_file']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $info = @getimagesize($_FILES['poster_file']['tmp_name']);
        $mime = $info['mime'] ?? '';

        if (!isset($allowed[$mime])) {
            $errors[] = 'Постер должен быть изображением JPG, PNG или WEBP.';
        } elseif ($_FILES['poster_file']['size'] > UPLOAD_MAX_SIZE) {
            $errors[] = 'Размер постера не должен превышать 5 МБ.';
        } else {
            if (!is_dir(POSTER_DIR)) {
                mkdir(POSTER_DIR, 0775, true);
            }
            $poster = 'poster_' . time() . '.' . $allowed[$mime];
            move_uploaded_file($_FILES['poster_file']['tmp_name'], POSTER_DIR . '/' . $poster);
        }
    }

    if (!$errors) {
        $genres = array_map('intval', $_POST['genres'] ?? []);

        if ($isNew) {
            $stmt = db()->prepare(
                'INSERT INTO titles (type, title, original_title, slug, description, year, year_end, country,
                                     duration, age_limit, rating, price, poster, backdrop, logo, trailer, accent_color,
                                     badge, director, cast_list, is_new, is_popular)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $data['type'], $data['title'], $data['original_title'], $slug, $data['description'],
                $data['year'], $data['year_end'], $data['country'], $data['duration'], $data['age_limit'],
                $data['rating'], $data['price'], $poster ?: null, $data['backdrop'], $data['logo'], $data['trailer'],
                $data['accent_color'], $data['badge'], $data['director'], $data['cast_list'],
                $data['is_new'], $data['is_popular'],
            ]);
            $id = (int)db()->lastInsertId();
            flash('Тайтл добавлен в каталог.', 'success');
        } else {
            $stmt = db()->prepare(
                'UPDATE titles SET type = ?, title = ?, original_title = ?, slug = ?, description = ?, year = ?,
                        year_end = ?, country = ?, duration = ?, age_limit = ?, rating = ?, price = ?, poster = ?,
                        backdrop = ?, logo = ?, trailer = ?, accent_color = ?, badge = ?, director = ?,
                        cast_list = ?, is_new = ?, is_popular = ?
                  WHERE id = ?'
            );
            $stmt->execute([
                $data['type'], $data['title'], $data['original_title'], $slug, $data['description'],
                $data['year'], $data['year_end'], $data['country'], $data['duration'], $data['age_limit'],
                $data['rating'], $data['price'], $poster ?: null, $data['backdrop'], $data['logo'], $data['trailer'],
                $data['accent_color'], $data['badge'], $data['director'], $data['cast_list'],
                $data['is_new'], $data['is_popular'], $id,
            ]);
            flash('Изменения сохранены.', 'success');
        }

        // Синхронизация жанров «многие ко многим»
        db()->prepare('DELETE FROM title_genres WHERE title_id = ?')->execute([$id]);
        if ($genres) {
            $insert = db()->prepare('INSERT INTO title_genres (title_id, genre_id) VALUES (?, ?)');
            foreach ($genres as $genreId) {
                $insert->execute([$id, $genreId]);
            }
        }

        redirect(url('admin/title-form.php?id=' . $id));
    }
}

/* ================== Сезоны и серии ================== */
$seasonAction = $_POST['season_action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $seasonAction !== '' && $id) {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        flash('Сессия устарела.', 'error');
        redirect(url('admin/title-form.php?id=' . $id));
    }

    switch ($seasonAction) {
        case 'add_season':
            $number = (int)($_POST['season_number'] ?? 0);
            $stmt = db()->prepare('INSERT IGNORE INTO seasons (title_id, number, name, year) VALUES (?, ?, ?, ?)');
            $stmt->execute([$id, $number, 'Сезон ' . $number, (int)($_POST['season_year'] ?? 0) ?: null]);
            flash('Сезон добавлен.', 'success');
            break;

        case 'add_episode':
            $seasonId = (int)($_POST['season_id'] ?? 0);
            $number = (int)($_POST['episode_number'] ?? 0);
            $stmt = db()->prepare(
                'INSERT INTO episodes (season_id, number, name, description, duration)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)'
            );
            $stmt->execute([
                $seasonId,
                $number,
                trim((string)($_POST['episode_name'] ?? 'Серия')),
                trim((string)($_POST['episode_desc'] ?? '')) ?: null,
                (int)($_POST['episode_duration'] ?? 0) ?: null,
            ]);
            flash('Серия добавлена.', 'success');
            break;

        case 'delete_season':
            db()->prepare('DELETE FROM seasons WHERE id = ? AND title_id = ?')
                ->execute([(int)($_POST['season_id'] ?? 0), $id]);
            flash('Сезон удалён.', 'success');
            break;

        case 'delete_episode':
            $seasonId = (int)($_POST['season_id'] ?? 0);
            db()->prepare(
                'DELETE e FROM episodes e JOIN seasons s ON s.id = e.season_id
                  WHERE e.id = ? AND s.title_id = ?'
            )->execute([(int)($_POST['episode_id'] ?? 0), $id]);
            flash('Серия удалена.', 'success');
            break;
    }

    redirect(url('admin/title-form.php?id=' . $id . '#seasons'));
}

/* ================== Данные для формы ================== */
$genres      = genres_all();
$allGenres   = $genres;
$selectedGenres = [];
$seasons = [];
if ($title) {
    // Идентификаторы жанров, уже привязанных к тайтлу
    $selectedGenres = array_map('intval', array_column(
        db()->query('SELECT genre_id FROM title_genres WHERE title_id = ' . (int)$title['id'])->fetchAll(),
        'genre_id'
    ));
    $seasons = title_seasons((int)$title['id']);
}

$videos   = array_map('basename', glob(__DIR__ . '/../assets/video/*.mp4') ?: []);
$logos    = array_map('basename', glob(__DIR__ . '/../assets/img/logos/*.png') ?: []);
$backdrops = array_map('basename', glob(__DIR__ . '/../assets/img/backdrops/*.jpg') ?: []);

$adminTab   = 'titles';
$page_title = $isNew ? 'Новый тайтл' : 'Редактирование: ' . $title['title'];
require __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="admin-layout">
        <?php require __DIR__ . '/_nav.php'; ?>

        <div class="admin-main">
            <div class="admin-head">
                <div>
                    <h1><?= $isNew ? 'Новый тайтл' : 'Редактирование тайтла' ?></h1>
                    <p><?= $isNew ? 'Заполните карточку — она сразу появится в каталоге' : e($title['title']) ?></p>
                </div>
                <a class="btn btn--ghost btn--sm" href="<?= url('admin/titles.php') ?>">← К списку</a>
            </div>

            <?php foreach ($errors as $error): ?>
                <div class="alert alert--error"><?= e($error) ?></div>
            <?php endforeach; ?>

            <form class="admin-form" method="post" enctype="multipart/form-data"
                  action="<?= url('admin/title-form.php' . ($id ? '?id=' . $id : '')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">

                <div class="form-grid">
                    <div class="field">
                        <label class="field__label" for="type">Тип</label>
                        <select class="field__input" id="type" name="type">
                            <option value="movie" <?= ($title['type'] ?? '') === 'movie' ? 'selected' : '' ?>>Фильм</option>
                            <option value="series" <?= ($title['type'] ?? '') === 'series' ? 'selected' : '' ?>>Сериал</option>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field__label" for="title">Название</label>
                        <input class="field__input" id="title" name="title" required
                               value="<?= e($title['title'] ?? ($_POST['title'] ?? '')) ?>">
                    </div>
                    <div class="field">
                        <label class="field__label" for="original_title">Оригинальное название</label>
                        <input class="field__input" id="original_title" name="original_title"
                               value="<?= e($title['original_title'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label class="field__label" for="slug">Адрес (slug)</label>
                        <input class="field__input" id="slug" name="slug"
                               value="<?= e($title['slug'] ?? '') ?>" placeholder="сгенерируется автоматически">
                    </div>

                    <div class="field field--full">
                        <label class="field__label" for="description">Описание</label>
                        <textarea class="field__input" id="description" name="description" rows="5"><?= e($title['description'] ?? '') ?></textarea>
                    </div>

                    <div class="field">
                        <label class="field__label" for="year">Год</label>
                        <input class="field__input" type="number" id="year" name="year" min="1900" max="2100"
                               value="<?= e((string)($title['year'] ?? '')) ?>">
                    </div>
                    <div class="field">
                        <label class="field__label" for="year_end">Год окончания (для сериала)</label>
                        <input class="field__input" type="number" id="year_end" name="year_end" min="1900" max="2100"
                               value="<?= e((string)($title['year_end'] ?? '')) ?>">
                    </div>
                    <div class="field">
                        <label class="field__label" for="country">Страна</label>
                        <input class="field__input" id="country" name="country" value="<?= e($title['country'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label class="field__label" for="duration">Длительность, мин</label>
                        <input class="field__input" type="number" id="duration" name="duration" min="1"
                               value="<?= e((string)($title['duration'] ?? '')) ?>">
                    </div>
                    <div class="field">
                        <label class="field__label" for="age_limit">Возрастной рейтинг</label>
                        <select class="field__input" id="age_limit" name="age_limit">
                            <?php foreach (['0+', '6+', '12+', '16+', '18+'] as $age): ?>
                                <option value="<?= $age ?>" <?= ($title['age_limit'] ?? '') === $age ? 'selected' : '' ?>><?= $age ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field__label" for="rating">Рейтинг (0–10)</label>
                        <input class="field__input" type="number" step="0.1" min="0" max="10" id="rating" name="rating"
                               value="<?= e((string)($title['rating'] ?? '7.0')) ?>">
                    </div>
                    <div class="field">
                        <label class="field__label" for="price">Цена покупки, ₽</label>
                        <input class="field__input" type="number" step="1" min="0" id="price" name="price"
                               value="<?= e((string)($title['price'] ?? '0')) ?>">
                        <div class="field__hint">0 — фильм доступен только по подписке</div>
                    </div>
                    <div class="field">
                        <label class="field__label" for="badge">Плашка</label>
                        <input class="field__input" id="badge" name="badge" placeholder="Новое, Топ-10…"
                               value="<?= e($title['badge'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label class="field__label" for="accent_color">Акцентный цвет</label>
                        <input class="field__input u-h-46 u-p-6" type="color" id="accent_color" name="accent_color"
                               value="<?= e($title['accent_color'] ?? '#FF6B35') ?>">
                    </div>

                    <div class="field">
                        <label class="field__label" for="director">Режиссёр</label>
                        <input class="field__input" id="director" name="director" value="<?= e($title['director'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label class="field__label" for="cast_list">Актёры (через запятую)</label>
                        <input class="field__input" id="cast_list" name="cast_list" value="<?= e($title['cast_list'] ?? '') ?>">
                    </div>

                    <div class="field">
                        <label class="field__label" for="poster">Постер из библиотеки</label>
                        <select class="field__input" id="poster" name="poster">
                            <option value="">— не выбран —</option>
                            <?php foreach (array_map('basename', glob(__DIR__ . '/../assets/img/posters/*.jpg') ?: []) as $file): ?>
                                <option value="<?= e($file) ?>" <?= ($title['poster'] ?? '') === $file ? 'selected' : '' ?>><?= e($file) ?></option>
                            <?php endforeach; ?>
                            <?php if (!empty($title['poster']) && str_starts_with((string)$title['poster'], 'poster_')): ?>
                                <option value="<?= e($title['poster']) ?>" selected><?= e($title['poster']) ?> (загружен)</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field__label" for="poster_file">Или загрузить новый постер</label>
                        <input class="field__input" type="file" id="poster_file" name="poster_file" accept="image/*">
                    </div>

                    <div class="field">
                        <label class="field__label" for="backdrop">Фон баннера</label>
                        <select class="field__input" id="backdrop" name="backdrop">
                            <option value="">— без фона —</option>
                            <?php foreach ($backdrops as $file): ?>
                                <option value="<?= e($file) ?>" <?= ($title['backdrop'] ?? '') === $file ? 'selected' : '' ?>><?= e($file) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field__label" for="logo">Логотип названия</label>
                        <select class="field__input" id="logo" name="logo">
                            <option value="">— без логотипа —</option>
                            <?php foreach ($logos as $file): ?>
                                <option value="<?= e($file) ?>" <?= ($title['logo'] ?? '') === $file ? 'selected' : '' ?>><?= e($file) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field__label" for="trailer">Видео (трейлер)</label>
                        <select class="field__input" id="trailer" name="trailer">
                            <option value="">— без видео —</option>
                            <?php foreach ($videos as $file): ?>
                                <option value="<?= e($file) ?>" <?= ($title['trailer'] ?? '') === $file ? 'selected' : '' ?>><?= e($file) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field field--full">
                        <label class="field__label">Жанры</label>
                        <div class="check-list">
                            <?php foreach ($allGenres as $genre): ?>
                                <label class="check-chip">
                                    <input type="checkbox" name="genres[]" value="<?= (int)$genre['id'] ?>"
                                        <?= in_array((int)$genre['id'], $selectedGenres, true) ? 'checked' : '' ?>>
                                    <span><?= e($genre['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="field field--full u-d-flex u-gap-24 u-flex-wrap">
                        <label class="checkbox">
                            <input type="checkbox" name="is_new" value="1" <?= !empty($title['is_new']) ? 'checked' : '' ?>>
                            <span>Новинка</span>
                        </label>
                        <label class="checkbox">
                            <input type="checkbox" name="is_popular" value="1" <?= !empty($title['is_popular']) ? 'checked' : '' ?>>
                            <span>Популярное</span>
                        </label>
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn btn--primary" type="submit"><?= $isNew ? 'Добавить тайтл' : 'Сохранить изменения' ?></button>
                    <a class="btn btn--outline" href="<?= url('admin/titles.php') ?>">Отмена</a>
                </div>
            </form>

            <?php if ($title && $title['type'] === 'series'): ?>
                <!-- ============ Сезоны и серии ============ -->
                <div class="admin-form u-mt-24" id="seasons">
                    <h2 class="u-fs-20">Сезоны и серии</h2>
                    <p class="u-text-muted u-fs-14">
                        Для каждого сезона добавьте серии — они появятся на странице сериала.
                    </p>

                    <?php foreach ($seasons as $season): ?>
                        <div class="season is-open u-mb-12">
                            <div class="season__toggle u-cursor-default">
                                <span>Сезон <?= (int)$season['number'] ?></span>
                                <span class="season__count"><?= plural(count($season['episodes']), 'серия', 'серии', 'серий') ?></span>
                                <form class="u-ml-8" method="post" action="<?= url('admin/title-form.php?id=' . $id) ?>"
                                      onsubmit="return confirm('Удалить сезон со всеми сериями?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="season_action" value="delete_season">
                                    <input type="hidden" name="season_id" value="<?= (int)$season['id'] ?>">
                                    <button class="icon-btn icon-btn--danger" type="submit" title="Удалить сезон">🗑</button>
                                </form>
                            </div>

                            <div class="episodes u-d-block">
                                <?php foreach ($season['episodes'] as $episode): ?>
                                    <div class="episode">
                                        <span class="episode__num"><?= (int)$episode['number'] ?></span>
                                        <div>
                                            <div class="episode__name"><?= e($episode['name']) ?></div>
                                            <div class="episode__desc"><?= e($episode['description']) ?></div>
                                        </div>
                                        <form method="post" action="<?= url('admin/title-form.php?id=' . $id) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="season_action" value="delete_episode">
                                            <input type="hidden" name="season_id" value="<?= (int)$season['id'] ?>">
                                            <input type="hidden" name="episode_id" value="<?= (int)$episode['id'] ?>">
                                            <button class="icon-btn icon-btn--danger" type="submit" title="Удалить серию">×</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>

                                <!-- Быстрое добавление серии -->
                                <form class="u-d-flex u-gap-8 u-flex-wrap u-p-14-22" method="post" action="<?= url('admin/title-form.php?id=' . $id) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="season_action" value="add_episode">
                                    <input type="hidden" name="season_id" value="<?= (int)$season['id'] ?>">
                                    <input class="input u-w-80" type="number" name="episode_number"
                                           placeholder="№" min="1" required>
                                    <input class="input u-flex-1 u-min-w-160" name="episode_name"
                                           placeholder="Название серии" required>
                                    <input class="input u-flex-1 u-min-w-140" name="episode_desc" placeholder="Краткое описание">
                                    <button class="btn btn--outline btn--sm" type="submit">+ Серия</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <form class="u-d-flex u-gap-8 u-flex-wrap u-mt-14" method="post" action="<?= url('admin/title-form.php?id=' . $id) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="season_action" value="add_season">
                        <input class="input u-w-110" type="number" name="season_number"
                               placeholder="Сезон №" min="1" required>
                        <input class="input u-w-130" type="number" name="season_year"
                               placeholder="Год" min="1900" max="2100">
                        <button class="btn btn--primary btn--sm" type="submit">Добавить сезон</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
