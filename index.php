<?php
/**
 * index.php — главная страница Illuminos.
 * Блоки: hero-баннер, «Новинки», «Популярное», категории,
 * персональные рекомендации, продолжение просмотра и промо-подписка.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/includes/catalog.php';

$user       = current_user();
$page_title = 'Онлайн-кинотеатр';
$active     = 'home';

// На главной формы входа и регистрации открываются в модальных окнах
$auth_modals = true;
$page_scripts = ['auth.js'];

// --- Подборки для главной страницы ---
$newTitles     = home_section('new', 10);
$popularTitles = home_section('popular', 10);
$genres        = genres_all();
$recommended   = recommendations_for($user['id'] ?? null, 8);
$favoriteIds   = favorites_ids();

// Hero: до пяти тайтлов с баннером
$heroTitles = db()->query(
    'SELECT * FROM titles
      WHERE backdrop IS NOT NULL AND backdrop <> ""
      ORDER BY is_new DESC, rating DESC
      LIMIT 5'
)->fetchAll();
$heroTitles = decorate_titles($heroTitles);
$hero = $heroTitles[0] ?? null;

// «Продолжить просмотр» — только для авторизованных
$continueWatching = [];
if ($user) {
    $stmt = db()->prepare(
        'SELECT t.*, h.progress, h.watched_at AS last_view
           FROM watch_history h
           JOIN titles t ON t.id = h.title_id
           JOIN (
                SELECT title_id, MAX(id) AS last_id
                  FROM watch_history
                 WHERE user_id = ?
                 GROUP BY title_id
           ) latest ON latest.last_id = h.id
          ORDER BY h.watched_at DESC
          LIMIT 4'
    );
    $stmt->execute([$user['id']]);
    $continueWatching = decorate_titles($stmt->fetchAll());
}

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($hero): ?>
<!-- ================= HERO-БАННЕР ================= -->
<section class="hero" id="hero">
    <div class="hero__bg" id="heroBg"
         style="background-image:url('<?= asset('img/backdrops/' . $hero['backdrop']) ?>')"></div>
    <div class="hero__overlay"></div>

    <div class="container hero__inner" id="heroInner">
        <div class="hero__badges">
            <span class="hero__badge"><?= $hero['type'] === 'series' ? 'Сериал' : 'Фильм' ?></span>
            <?php if (!empty($hero['badge'])): ?>
                <span class="hero__badge hero__badge--ghost"><?= e($hero['badge']) ?></span>
            <?php endif; ?>
        </div>

        <?php if (!empty($hero['logo'])): ?>
            <img class="hero__logo" id="heroLogo" src="<?= asset('img/logos/' . $hero['logo']) ?>" alt="<?= e($hero['title']) ?>">
        <?php endif; ?>

        <h1 class="hero__title <?= !empty($hero['logo']) ? 'sr-only' : '' ?>" id="heroTitle"><?= e($hero['title']) ?></h1>

        <div class="hero__meta" id="heroMeta">
            <span class="rating">★ <?= number_format((float)$hero['rating'], 1, '.', '') ?></span>
            <span><?= e((string)$hero['year']) ?><?= !empty($hero['year_end']) && $hero['year_end'] != $hero['year'] ? '–' . e((string)$hero['year_end']) : '' ?></span>
            <span><?= e(implode(', ', $hero['genres'])) ?></span>
            <?php if (!empty($hero['duration'])): ?>
                <span><?= $hero['type'] === 'series' ? duration_text((int)$hero['duration']) . ' / серия' : duration_text((int)$hero['duration']) ?></span>
            <?php endif; ?>
            <span><?= e($hero['age_limit']) ?></span>
        </div>

        <p class="hero__desc" id="heroDesc"><?= e($hero['description']) ?></p>

        <div class="hero__actions">
            <button class="btn btn--primary"
                    data-player-src="<?= asset('video/' . $hero['trailer']) ?>"
                    data-player-caption="<?= e($hero['title']) ?> — трейлер">
                ▶ Смотреть
            </button>
            <a class="btn btn--ghost" id="heroMore" href="<?= url('title.php?slug=' . urlencode($hero['slug'])) ?>">Подробнее</a>
            <button class="btn btn--outline" type="button"
                    data-favorite="<?= (int)$hero['id'] ?>" data-label="full"
                    aria-pressed="<?= in_array((int)$hero['id'], $favoriteIds, true) ? 'true' : 'false' ?>">
                ♥ <span class="js-fav-text"><?= in_array((int)$hero['id'], $favoriteIds, true) ? 'В избранном' : 'В избранное' ?></span>
            </button>
        </div>
    </div>

    <!-- Миниатюры для переключения hero без перезагрузки -->
    <div class="hero__thumbs" id="heroThumbs">
        <?php foreach ($heroTitles as $index => $item): ?>
            <button class="hero__thumb <?= $index === 0 ? 'is-active' : '' ?>"
                    type="button"
                    data-bg="<?= asset('img/backdrops/' . $item['backdrop']) ?>"
                    data-logo="<?= !empty($item['logo']) ? asset('img/logos/' . $item['logo']) : '' ?>"
                    data-title="<?= e($item['title']) ?>"
                    data-url="<?= url('title.php?slug=' . urlencode($item['slug'])) ?>"
                    data-desc="<?= e($item['description']) ?>"
                    data-rating="<?= number_format((float)$item['rating'], 1, '.', '') ?>"
                    data-year="<?= e((string)$item['year']) ?>"
                    data-genres="<?= e(implode(', ', $item['genres'])) ?>"
                    data-age="<?= e($item['age_limit']) ?>"
                    data-duration="<?= $item['type'] === 'series' ? duration_text((int)$item['duration']) . ' / серия' : duration_text((int)$item['duration']) ?>"
                    data-trailer="<?= asset('video/' . $item['trailer']) ?>"
                    data-id="<?= (int)$item['id'] ?>"
                    aria-label="<?= e($item['title']) ?>">
                <img src="<?= poster_src($item) ?>" alt="">
            </button>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ================= НОВИНКИ ================= -->
<section class="section">
    <div class="container">
        <div class="section__head">
            <div>
                <h2 class="section__title">Топ новинки</h2>
                <p class="section__sub">Свежие премьеры и новые сезоны — уже в каталоге</p>
            </div>
            <a class="section__link" href="<?= url('catalog.php?is_new=1') ?>">Смотреть все →</a>
        </div>

        <div class="cards-row">
            <?php foreach ($newTitles as $title): ?>
                <?php $is_favorite = in_array((int)$title['id'], $favoriteIds, true); ?>
                <?php include __DIR__ . '/includes/card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ================= ПРОДОЛЖИТЬ ПРОСМОТР ================= -->
<?php if ($continueWatching): ?>
<section class="section">
    <div class="container">
        <div class="section__head">
            <div>
                <h2 class="section__title">Продолжить просмотр</h2>
                <p class="section__sub">Вы остановились здесь</p>
            </div>
            <a class="section__link" href="<?= url('cabinet.php?tab=history') ?>">Вся история →</a>
        </div>

        <div class="cards-row">
            <?php foreach ($continueWatching as $title): ?>
                <?php $is_favorite = in_array((int)$title['id'], $favoriteIds, true); ?>
                <?php include __DIR__ . '/includes/card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ================= ПОПУЛЯРНОЕ ================= -->
<section class="section">
    <div class="container">
        <div class="section__head">
            <div>
                <h2 class="section__title">Популярное</h2>
                <p class="section__sub">Что чаще всего смотрят на Illuminos</p>
            </div>
            <a class="section__link" href="<?= url('catalog.php?sort=popular') ?>">Смотреть все →</a>
        </div>

        <div class="cards-grid">
            <?php foreach ($popularTitles as $title): ?>
                <?php $is_favorite = in_array((int)$title['id'], $favoriteIds, true); ?>
                <?php include __DIR__ . '/includes/card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ================= КАТЕГОРИИ ================= -->
<section class="section" id="categories">
    <div class="container">
        <div class="section__head">
            <div>
                <h2 class="section__title">Категории</h2>
                <p class="section__sub">Выберите настроение — остальное сделает подборка</p>
            </div>
        </div>

        <div class="categories-grid">
            <?php foreach ($genres as $genre): ?>
                <a class="category-card" href="<?= url('catalog.php?category=' . urlencode($genre['slug'])) ?>">
                    <span class="category-card__icon">
                        <img src="<?= asset('img/categories/' . ($genre['icon'] ?: 'drama.svg')) ?>" alt="">
                    </span>
                    <span>
                        <span class="category-card__name"><?= e($genre['name']) ?></span><br>
                        <span class="category-card__count"><?= plural((int)$genre['titles_count'], 'тайтл', 'тайтла', 'тайтлов') ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ================= РЕКОМЕНДАЦИИ ================= -->
<section class="section">
    <div class="container">
        <div class="section__head">
            <div>
                <h2 class="section__title"><?= $user ? 'Рекомендуем вам' : 'Рекомендации' ?></h2>
                <p class="section__sub">
                    <?= $user
                        ? 'Подобрано по вашим жанрам, избранному и истории просмотра'
                        : 'Лучшее по рейтингу — войдите, чтобы получать персональные подборки' ?>
                </p>
            </div>
        </div>

        <div class="cards-grid">
            <?php foreach ($recommended as $title): ?>
                <?php $is_favorite = in_array((int)$title['id'], $favoriteIds, true); ?>
                <?php include __DIR__ . '/includes/card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ================= ПРОМО ПОДПИСКИ ================= -->
<section class="section">
    <div class="container">
        <div class="promo-banner">
            <div class="promo-banner__text">
                <h2 class="promo-banner__title">Смотрите без ограничений</h2>
                <p class="promo-banner__sub">
                    Первый месяц по промокоду <strong>START15</strong> — скидка 15% на любой тариф.
                    Отмена в любой момент, без скрытых платежей.
                </p>
            </div>
            <a class="btn btn--primary" href="<?= url('pricing.php') ?>">Выбрать тариф</a>
        </div>
    </div>
</section>

<script>
    // Переключение hero-баннера по миниатюрам (без перезагрузки страницы)
    document.addEventListener('DOMContentLoaded', () => {
        const bg = document.getElementById('heroBg');
        const logo = document.getElementById('heroLogo');
        const title = document.getElementById('heroTitle');
        const desc = document.getElementById('heroDesc');
        const meta = document.getElementById('heroMeta');
        const more = document.getElementById('heroMore');
        const thumbs = document.querySelectorAll('.hero__thumb');
        const favBtn = document.querySelector('.hero__actions [data-favorite]');
        const watchBtn = document.querySelector('.hero__actions [data-player-src]');

        thumbs.forEach((thumb) => {
            thumb.addEventListener('click', () => {
                thumbs.forEach((t) => t.classList.remove('is-active'));
                thumb.classList.add('is-active');

                bg.style.backgroundImage = `url('${thumb.dataset.bg}')`;
                title.textContent = thumb.dataset.title;
                desc.textContent = thumb.dataset.desc;
                more.href = thumb.dataset.url;

                if (thumb.dataset.logo) {
                    if (logo) { logo.src = thumb.dataset.logo; logo.hidden = false; }
                } else if (logo) {
                    logo.hidden = true;
                }

                meta.innerHTML = `
                    <span class="rating">★ ${thumb.dataset.rating}</span>
                    <span>${thumb.dataset.year}</span>
                    <span>${thumb.dataset.genres}</span>
                    <span>${thumb.dataset.duration}</span>
                    <span>${thumb.dataset.age}</span>`;

                watchBtn.dataset.playerSrc = thumb.dataset.trailer;
                watchBtn.dataset.playerCaption = `${thumb.dataset.title} — трейлер`;
                favBtn.dataset.favorite = thumb.dataset.id;

                // Сбрасываем состояние сердечка: актуальный статус подгрузит сервер при клике
                favBtn.classList.remove('is-active');
                favBtn.querySelector('.js-fav-text').textContent = 'В избранное';
            });
        });
    });
</script>

<?php require_once __DIR__ . '/includes/auth_modals.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
