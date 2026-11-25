<?php
/**
 * includes/catalog_page.php — общий шаблон каталога.
 * Используется страницами catalog.php, movies.php и series.php:
 * обёртка задаёт $presetType ('movie' / 'series') и заголовок.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/catalog.php';

$presetType = $presetType ?? null;

$q        = trim((string)($_GET['q'] ?? ''));
// Категория: основной параметр ?category=, ?genre= поддержан для старых ссылок
$genre    = trim((string)($_GET['category'] ?? $_GET['genre'] ?? ''));
$year     = (int)($_GET['year'] ?? 0);
$sort     = (string)($_GET['sort'] ?? 'popular');
$page     = max(1, (int)($_GET['page'] ?? 1));
$type     = $presetType ?: (string)($_GET['type'] ?? '');
$isNew    = !empty($_GET['is_new']);

$activeGenre = $genre !== '' ? genre_by_slug($genre) : null;

$result = search_titles([
    'q'      => $q,
    'type'   => $type,
    'genre'  => $genre,
    'year'   => $year,
    'sort'   => $sort,
    'page'   => $page,
    'is_new' => $isNew,
    'per_page' => 24,
]);

$genres   = genres_all();
$years    = db()->query('SELECT DISTINCT year FROM titles WHERE year IS NOT NULL ORDER BY year DESC')->fetchAll(PDO::FETCH_COLUMN);
$favoriteIds = favorites_ids();

// Собираем ссылку с учётом текущих фильтров
function filter_url(array $overrides = []): string
{
    $params = array_filter([
        'q'        => $_GET['q'] ?? '',
        'type'     => $_GET['type'] ?? '',
        // В ссылках используем читаемый параметр ?category=, ?genre= — устаревший алиас
        'category' => $_GET['category'] ?? $_GET['genre'] ?? '',
        'year'     => $_GET['year'] ?? '',
        'sort'     => $_GET['sort'] ?? '',
        'is_new'   => $_GET['is_new'] ?? '',
    ], fn($v) => $v !== '');

    $params = array_merge($params, array_filter($overrides, fn($v) => $v !== '' && $v !== null));
    foreach ($overrides as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }

    return url('catalog.php' . ($params ? '?' . http_build_query($params) : ''));
}

// Заголовок страницы зависит от выбранных фильтров
if ($presetType === 'movie') {
    $page_title = 'Фильмы';
    $h1 = 'Фильмы';
    $lead = 'Полнометражные картины: от классики до премьер этого года.';
} elseif ($presetType === 'series') {
    $page_title = 'Сериалы';
    $h1 = 'Сериалы';
    $lead = 'Многосерийные истории, которые затягивают с первой серии.';
} else {
    $page_title = $activeGenre ? $activeGenre['name'] : 'Каталог';
    $h1 = $activeGenre ? $activeGenre['name'] : 'Каталог фильмов и сериалов';
    $lead = $activeGenre ? $activeGenre['description'] : 'Все фильмы и сериалы Illuminos в одном месте.';
}

if ($q !== '') {
    $page_title = 'Поиск: ' . $q;
    $h1 = 'Результаты поиска';
    $lead = 'По запросу «' . $q . '» найдено: ' . $result['total'];
}

require_once __DIR__ . '/header.php';
?>

<div class="container">
    <div class="page-head">
        <nav class="breadcrumbs" aria-label="Хлебные крошки">
            <a href="<?= url('index.php') ?>">Главная</a>
            <span>/</span>
            <a href="<?= url('catalog.php') ?>">Каталог</a>
            <?php if ($activeGenre): ?>
                <span>/</span><span><?= e($activeGenre['name']) ?></span>
            <?php endif; ?>
        </nav>
        <h1><?= e($h1) ?></h1>
        <p><?= e($lead) ?></p>
    </div>

    <div class="catalog">
        <!-- ===== Фильтры ===== -->
        <aside class="filters" aria-label="Фильтры каталога">
            <form method="get" action="<?= url('catalog.php') ?>">
                <?php if ($presetType): ?>
                    <input type="hidden" name="type" value="<?= e($presetType) ?>">
                <?php endif; ?>
                <?php if ($isNew): ?>
                    <input type="hidden" name="is_new" value="1">
                <?php endif; ?>

                <div class="filters__group">
                    <h4>Поиск</h4>
                    <input class="field__input" type="search" name="q" value="<?= e($q) ?>"
                           placeholder="Название, актёр, режиссёр">
                </div>

                <?php if (!$presetType): ?>
                <div class="filters__group">
                    <h4>Тип</h4>
                    <div class="filters__list">
                        <a class="filter-chip <?= $type === '' ? 'is-active' : '' ?>"
                           href="<?= filter_url(['type' => '']) ?>">Все</a>
                        <a class="filter-chip <?= $type === 'movie' ? 'is-active' : '' ?>"
                           href="<?= filter_url(['type' => 'movie']) ?>">Фильмы</a>
                        <a class="filter-chip <?= $type === 'series' ? 'is-active' : '' ?>"
                           href="<?= filter_url(['type' => 'series']) ?>">Сериалы</a>
                    </div>
                </div>
                <?php endif; ?>

                <div class="filters__group">
                    <h4>Жанр</h4>
                    <div class="filters__list">
                        <a class="filter-chip <?= $genre === '' ? 'is-active' : '' ?>"
                           href="<?= filter_url(['category' => '']) ?>">Все</a>
                        <?php foreach ($genres as $item): ?>
                            <a class="filter-chip <?= $genre === $item['slug'] ? 'is-active' : '' ?>"
                               href="<?= filter_url(['category' => $item['slug']]) ?>"><?= e($item['name']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="filters__group">
                    <h4>Год</h4>
                    <select class="select u-w-full" name="year" onchange="this.form.submit()">
                        <option value="">Любой год</option>
                        <?php foreach ($years as $y): ?>
                            <option value="<?= (int)$y ?>" <?= $year === (int)$y ? 'selected' : '' ?>><?= (int)$y ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filters__group">
                    <h4>Сортировка</h4>
                    <select class="select u-w-full" name="sort" onchange="this.form.submit()">
                        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>По популярности</option>
                        <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>По рейтингу</option>
                        <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>Сначала новые</option>
                        <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>По алфавиту</option>
                    </select>
                </div>

                <button class="btn btn--primary btn--block" type="submit">Применить</button>
                <?php if ($q || $genre || $year || $isNew): ?>
                    <a class="btn btn--outline btn--block" href="<?= url('catalog.php' . ($presetType ? '?type=' . $presetType : '')) ?>">Сбросить</a>
                <?php endif; ?>
            </form>
        </aside>

        <!-- ===== Результаты ===== -->
        <div>
            <div class="catalog__top">
                <span class="catalog__count">
                    Найдено: <?= plural($result['total'], 'тайтл', 'тайтла', 'тайтлов') ?>
                    <?php if ($isNew): ?> · только новинки<?php endif; ?>
                </span>
                <span class="catalog__count">Страница <?= $result['page'] ?> из <?= $result['pages'] ?></span>
            </div>

            <?php if (!$result['items']): ?>
                <div class="empty-state">
                    <h3>Ничего не найдено</h3>
                    <p>Попробуйте изменить фильтры или поисковый запрос.</p>
                    <a class="btn btn--primary" href="<?= url('catalog.php') ?>">Сбросить фильтры</a>
                </div>
            <?php else: ?>
                <div class="cards-grid">
                    <?php foreach ($result['items'] as $title): ?>
                        <?php $is_favorite = in_array((int)$title['id'], $favoriteIds, true); ?>
                        <?php include __DIR__ . '/card.php'; ?>
                    <?php endforeach; ?>
                </div>

                <?php if ($result['pages'] > 1): ?>
                    <nav class="pagination" aria-label="Постраничная навигация">
                        <?php if ($page > 1): ?>
                            <a href="<?= filter_url(['page' => $page - 1]) ?>">←</a>
                        <?php endif; ?>

                        <?php for ($p = max(1, $page - 2); $p <= min($result['pages'], $page + 2); $p++): ?>
                            <?php if ($p === $page): ?>
                                <span class="is-active"><?= $p ?></span>
                            <?php else: ?>
                                <a href="<?= filter_url(['page' => $p]) ?>"><?= $p ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $result['pages']): ?>
                            <a href="<?= filter_url(['page' => $page + 1]) ?>">→</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
