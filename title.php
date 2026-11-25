<?php
/**
 * title.php — карточка фильма или сериала.
 * Разделы: описание, оценки, актёры и режиссёр, трейлер,
 * сезоны и серии (для сериалов), отзывы и похожие тайтлы.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/includes/catalog.php';

$slug  = trim((string)($_GET['slug'] ?? ''));
$title = $slug !== '' ? find_title($slug) : null;

if (!$title) {
    http_response_code(404);
    $page_title = 'Страница не найдена';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="container">
        <div class="empty-state u-mt-64">
            <h3>Тайтл не найден</h3>
            <p>Возможно, он был удалён или ссылка устарела.</p>
            <a class="btn btn--primary" href="<?= url('catalog.php') ?>">Перейти в каталог</a>
        </div>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$user = current_user();

/* ---- Обработка формы отзыва (обычный POST + редирект, защита от повторной отправки) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_submit'])) {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        flash('Сессия устарела, попробуйте ещё раз.', 'error');
    } elseif (!$user) {
        flash('Войдите, чтобы оставить отзыв.', 'warning');
    } else {
        $score = max(1, min(10, (int)($_POST['score'] ?? 0)));
        $text  = trim((string)($_POST['text'] ?? ''));

        if ($score < 1) {
            flash('Поставьте оценку от 1 до 10.', 'error');
        } else {
            // ON DUPLICATE KEY UPDATE — одна оценка на пользователя и тайтл
            $stmt = db()->prepare(
                'INSERT INTO reviews (user_id, title_id, score, text)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE score = VALUES(score), text = VALUES(text), created_at = NOW()'
            );
            $stmt->execute([$user['id'], $title['id'], $score, $text !== '' ? $text : null]);
            flash('Спасибо! Ваша оценка сохранена.', 'success');
        }
    }

    redirect(url('title.php?slug=' . urlencode($title['slug']) . '#reviews'));
}

// Счётчик просмотров страницы
db()->prepare('UPDATE titles SET views = views + 1 WHERE id = ?')->execute([$title['id']]);

$isFavorite = in_array((int)$title['id'], favorites_ids(), true);
$userRating = null;
if ($user) {
    $stmt = db()->prepare('SELECT * FROM reviews WHERE user_id = ? AND title_id = ? LIMIT 1');
    $stmt->execute([$user['id'], $title['id']]);
    $userRating = $stmt->fetch() ?: null;
}

$communityRating = title_user_rating((int)$title['id']);
$reviews         = title_reviews((int)$title['id']);
$similar         = similar_titles($title, 6);
$seasons         = $title['type'] === 'series' ? title_seasons((int)$title['id']) : [];
$cast            = array_values(array_filter(array_map('trim', explode(',', (string)$title['cast_list']))));
$directors       = array_values(array_filter(array_map('trim', explode(',', (string)$title['director']))));

$userSub    = active_subscription();
$canWatch   = can_watch($title);
$isOwned    = owns_title((int)$title['id']);
$buyPrice   = (float)($title['price'] ?? 0);
$videoSrc   = asset('video/' . ($title['trailer'] ?: 'welcome.mp4'));

$page_title = $title['title'];
$active     = $title['type'] === 'series' ? 'series' : 'movies';

require __DIR__ . '/includes/header.php';
?>

<!-- ================= ВЕРХНИЙ БЛОК ТАЙТЛА ================= -->
<section class="title-hero">
    <?php if (!empty($title['backdrop'])): ?>
        <div class="title-hero__bg" style="background-image:url('<?= asset('img/backdrops/' . $title['backdrop']) ?>')"></div>
    <?php endif; ?>
    <div class="title-hero__overlay"></div>

    <div class="container title-hero__inner">
        <nav class="breadcrumbs" aria-label="Хлебные крошки">
            <a href="<?= url('index.php') ?>">Главная</a>
            <span>/</span>
            <a href="<?= url($title['type'] === 'series' ? 'series.php' : 'movies.php') ?>">
                <?= $title['type'] === 'series' ? 'Сериалы' : 'Фильмы' ?>
            </a>
            <span>/</span>
            <span><?= e($title['title']) ?></span>
        </nav>

        <div class="title-layout">
            <div class="title-poster">
                <img src="<?= poster_src($title) ?>" alt="Постер «<?= e($title['title']) ?>»">
            </div>

            <div class="title-info">
                <div class="meta-chips">
                    <span class="chip"><?= $title['type'] === 'series' ? 'Сериал' : 'Фильм' ?></span>
                    <?= badge_html($title['badge']) ?>
                    <span class="chip chip--rating">★ <?= number_format((float)$title['rating'], 1, '.', '') ?> Кинопоиск</span>
                    <?php if ($buyPrice > 0): ?>
                        <span class="chip chip--price"><?= e(price($buyPrice)) ?> · покупка</span>
                    <?php endif; ?>
                    <?php if ($isOwned): ?>
                        <span class="chip chip--owned">✓ Куплено</span>
                    <?php endif; ?>
                    <?php if ($communityRating['avg'] !== null): ?>
                        <span class="chip">Оценка зрителей: <?= $communityRating['avg'] ?> / 10</span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($title['logo'])): ?>
                    <img class="title-info__logo" src="<?= asset('img/logos/' . $title['logo']) ?>" alt="">
                <?php endif; ?>

                <h1><?= e($title['title']) ?></h1>
                <?php if (!empty($title['original_title'])): ?>
                    <div class="title-info__original"><?= e($title['original_title']) ?></div>
                <?php endif; ?>

                <div class="meta-chips">
                    <span class="chip"><?= e((string)$title['year']) ?><?= $title['year_end'] && $title['year_end'] != $title['year'] ? '–' . e((string)$title['year_end']) : '' ?></span>
                    <?php foreach ($title['genres_full'] as $genre): ?>
                        <a class="chip" href="<?= url('catalog.php?category=' . urlencode($genre['slug'])) ?>"><?= e($genre['name']) ?></a>
                    <?php endforeach; ?>
                    <?php if ($title['duration']): ?>
                        <span class="chip"><?= $title['type'] === 'series' ? duration_text((int)$title['duration']) . ' / серия' : duration_text((int)$title['duration']) ?></span>
                    <?php endif; ?>
                    <span class="chip chip--age"><?= e($title['age_limit']) ?></span>
                </div>

                <p class="title-info__desc"><?= nl2br(e($title['description'])) ?></p>

                <div class="title-actions">
                    <?php if ($canWatch): ?>
                        <button class="btn btn--primary"
                                data-player-src="<?= $videoSrc ?>"
                                data-player-caption="<?= e($title['title']) ?> — <?= $title['type'] === 'series' ? 'серия 1' : 'фильм' ?>"
                                data-history-title="<?= (int)$title['id'] ?>">
                            ▶ <?= $title['type'] === 'series' ? 'Смотреть сериал' : 'Смотреть' ?>
                        </button>
                    <?php else: ?>
                        <?php if ($buyPrice > 0 && !$isOwned): ?>
                            <button class="btn btn--primary" type="button" data-cart-add="<?= (int)$title['id'] ?>">
                                🛒 Купить за <?= e(price($buyPrice)) ?>
                            </button>
                            <a class="btn btn--outline" href="<?= url('pricing.php?title=' . urlencode($title['slug'])) ?>">
                                Смотреть по подписке
                            </a>
                        <?php else: ?>
                            <a class="btn btn--primary" href="<?= url('pricing.php?title=' . urlencode($title['slug'])) ?>">
                                ▶ Смотреть — нужна подписка
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>

                    <button class="btn btn--ghost" type="button"
                            data-player-src="<?= $videoSrc ?>"
                            data-player-caption="<?= e($title['title']) ?> — трейлер">
                        Трейлер
                    </button>

                    <button class="btn btn--outline <?= $isFavorite ? 'is-active' : '' ?>" type="button"
                            data-favorite="<?= (int)$title['id'] ?>" data-label="full"
                            aria-pressed="<?= $isFavorite ? 'true' : 'false' ?>">
                        ♥ <span class="js-fav-text"><?= $isFavorite ? 'В избранном' : 'В избранное' ?></span>
                    </button>
                </div>

                <dl class="title-facts">
                    <div>
                        <dt>Режиссёр</dt>
                        <dd><?= e($title['director']) ?></dd>
                    </div>
                    <div>
                        <dt><?= $title['type'] === 'series' ? 'В ролях' : 'Актёры' ?></dt>
                        <dd><?= e($title['cast_list']) ?></dd>
                    </div>
                    <div>
                        <dt>Страна</dt>
                        <dd><?= e($title['country']) ?></dd>
                    </div>
                    <div>
                        <dt><?= $title['type'] === 'series' ? 'Сезоны' : 'Длительность' ?></dt>
                        <dd>
                            <?= $title['type'] === 'series'
                                ? e(plural(count($seasons), 'сезон', 'сезона', 'сезонов'))
                                : e(duration_text((int)$title['duration'])) ?>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</section>

<div class="container">
    <!-- ================= ОЦЕНКА ================= -->
    <section class="title-section" id="rating">
        <h2 class="title-section__title">Оценки</h2>
        <div class="rating-panel">
            <div>
                <div class="rating-panel__value"><?= $communityRating['avg'] ?? number_format((float)$title['rating'], 1, '.', '') ?></div>
                <div class="rating-panel__label">
                    <?= $communityRating['avg'] !== null
                        ? 'средняя оценка зрителей'
                        : 'рейтинг Кинопоиска' ?>
                </div>
            </div>

            <div>
                <div class="rating-panel__title">Поставьте свою оценку</div>
                <?php if ($user): ?>
                    <p class="rating-panel__hint">
                        Ваша оценка: <strong><?= $userRating ? (int)$userRating['score'] : '—' ?></strong> из 10.
                        <?= $userRating ? 'Можно изменить в любой момент.' : 'Оценка сохранится в личном кабинете.' ?>
                    </p>
                    <div class="stars" data-rating-title="<?= (int)$title['id'] ?>">
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                            <button class="star-btn <?= $userRating && (int)$userRating['score'] === $i ? 'is-active' : '' ?>"
                                    type="button" data-score="<?= $i ?>"><?= $i ?></button>
                        <?php endfor; ?>
                    </div>
                <?php else: ?>
                    <p class="rating-panel__hint">
                        <a href="<?= url('login.php?next=' . urlencode('title.php?slug=' . $title['slug'])) ?>">Войдите</a>,
                        чтобы поставить оценку и оставить отзыв.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ================= ТРЕЙЛЕР ================= -->
    <section class="title-section">
        <h2 class="title-section__title">Трейлер</h2>
        <div class="trailer">
            <video id="trailerVideo" src="<?= $videoSrc ?>" preload="metadata" playsinline></video>
            <button class="trailer__play" type="button"
                    data-player-src="<?= $videoSrc ?>"
                    data-player-caption="<?= e($title['title']) ?> — трейлер">
                <span>▶</span>
            </button>
            <span class="trailer__label">Официальный трейлер</span>
        </div>
    </section>

    <?php if ($title['type'] === 'series' && $seasons): ?>
    <!-- ================= СЕЗОНЫ И СЕРИИ ================= -->
    <section class="title-section" id="seasons">
        <h2 class="title-section__title">Сезоны и серии</h2>
        <div class="seasons">
            <?php foreach ($seasons as $index => $season): ?>
                <div class="season <?= $index === 0 ? 'is-open' : '' ?>">
                    <button class="season__toggle" type="button" aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>">
                        <span>Сезон <?= (int)$season['number'] ?></span>
                        <span class="season__count"><?= e(plural(count($season['episodes']), 'серия', 'серии', 'серий')) ?><?= $season['year'] ? ' · ' . (int)$season['year'] : '' ?></span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    </button>

                    <div class="episodes">
                        <?php foreach ($season['episodes'] as $episode): ?>
                            <div class="episode">
                                <span class="episode__num"><?= (int)$episode['number'] ?></span>
                                <div>
                                    <div class="episode__name"><?= e($episode['name']) ?></div>
                                    <div class="episode__desc"><?= e($episode['description']) ?></div>
                                </div>
                                <span class="episode__dur"><?= e(duration_text((int)$episode['duration'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ================= АКТЁРЫ И РЕЖИССЁРЫ ================= -->
    <section class="title-section" id="cast">
        <h2 class="title-section__title">Актёры и режиссёры</h2>

        <div class="people-list u-mb-18">
            <?php foreach ($directors as $director): ?>
                <div class="person person--director">
                    <span class="person__avatar"><?= e(mb_strtoupper(mb_substr($director, 0, 1))) ?></span>
                    <span><?= e($director) ?><br><small class="u-text-muted">режиссёр</small></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="people-list">
            <?php foreach ($cast as $actor): ?>
                <div class="person">
                    <span class="person__avatar"><?= e(mb_strtoupper(mb_substr($actor, 0, 1))) ?></span>
                    <span><?= e($actor) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ================= ОТЗЫВЫ ================= -->
    <section class="title-section" id="reviews">
        <h2 class="title-section__title">Отзывы зрителей</h2>

        <?php if ($reviews): ?>
            <div class="reviews">
                <?php foreach ($reviews as $review): ?>
                    <article class="review">
                        <div class="review__head">
                            <span class="avatar avatar--initials"><?= e(mb_strtoupper(mb_substr($review['first_name'], 0, 1) . mb_substr($review['last_name'], 0, 1))) ?></span>
                            <strong><?= e($review['first_name'] . ' ' . $review['last_name']) ?></strong>
                            <span class="u-text-muted"><?= e(date('d.m.Y', strtotime($review['created_at']))) ?></span>
                            <span class="review__score"><?= (int)$review['score'] ?> / 10</span>
                        </div>
                        <?php if ($review['text']): ?>
                            <p class="review__text"><?= nl2br(e($review['text'])) ?></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state"><p>Пока нет отзывов. Станьте первым!</p></div>
        <?php endif; ?>

        <?php if ($user): ?>
            <form class="review-form" method="post"
                  action="<?= url('title.php?slug=' . urlencode($title['slug'])) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="review_submit" value="1">
                <div class="field u-m-0">
                    <label class="field__label" for="reviewText">Ваш отзыв</label>
                    <textarea id="reviewText" name="text" class="field__input"
                              placeholder="Поделитесь впечатлением о фильме…"><?= e($userRating['text'] ?? '') ?></textarea>
                </div>
                <div class="u-d-flex u-gap-12 u-flex-wrap u-items-center">
                    <select class="select" name="score" aria-label="Оценка">
                        <?php for ($i = 10; $i >= 1; $i--): ?>
                            <option value="<?= $i ?>" <?= $userRating && (int)$userRating['score'] === $i ? 'selected' : '' ?>>
                                <?= $i ?> / 10
                            </option>
                        <?php endfor; ?>
                    </select>
                    <button class="btn btn--primary" type="submit">Отправить отзыв</button>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <!-- ================= ПОХОЖИЕ ================= -->
    <?php if ($similar): ?>
        <section class="title-section">
            <h2 class="title-section__title">
                Похожие <?= $title['type'] === 'series' ? 'сериалы' : 'фильмы' ?>
            </h2>
            <div class="cards-row">
                <?php foreach ($similar as $similarTitle): ?>
                    <?php
                    $is_favorite = in_array((int)$similarTitle['id'], favorites_ids(), true);
                    $title = $similarTitle;
                    include __DIR__ . '/includes/card.php';
                    ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // --- Оценка звёздами через API (без перезагрузки) ---
        const stars = document.querySelector('[data-rating-title]');
        if (stars) {
            const titleId = stars.dataset.ratingTitle;
            stars.querySelectorAll('.star-btn').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const score = btn.dataset.score;
                    try {
                        const response = await fetch(`${window.APP.api}/rate.php`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            body: JSON.stringify({ title_id: titleId, score }),
                        });
                        const data = await response.json();
                        if (!data.ok) { showToast(data.message || 'Не удалось сохранить оценку', 'error'); return; }

                        stars.querySelectorAll('.star-btn').forEach((b) =>
                            b.classList.toggle('is-active', b.dataset.score <= score));
                        showToast(`Оценка ${score}/10 сохранена`, 'success');
                    } catch (error) {
                        showToast('Ошибка соединения', 'error');
                    }
                });
            });
        }

        // --- Кнопка «Смотреть» попутно пишет историю просмотра ---
        document.querySelectorAll('[data-history-title]').forEach((button) => {
            if (!window.APP_USER) return;
            button.addEventListener('click', () => {
                fetch(`${window.APP.api}/history.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ title_id: button.dataset.historyTitle, progress: 5 }),
                }).catch(() => {});
            });
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
