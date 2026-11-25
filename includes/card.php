<?php
/**
 * includes/card.php
 * Карточка тайтла для сеток и горизонтальных подборок.
 * Ожидает массив $title и необязательный флаг $is_favorite.
 * Если у фильма задана цена — показывается кнопка покупки (корзина).
 */
$isFavorite = $is_favorite ?? false;
$link = url('title.php?slug=' . urlencode($title['slug']));
$price = (float)($title['price'] ?? 0);
?>
<article class="movie-card">
    <a class="movie-card__poster" href="<?= $link ?>">
        <img src="<?= poster_src($title) ?>" alt="Постер «<?= e($title['title']) ?>»" loading="lazy">

        <div class="movie-card__top">
            <span class="movie-card__rating">★ <?= number_format((float)$title['rating'], 1, '.', '') ?></span>
            <?= badge_html($title['badge']) ?>
        </div>

        <div class="movie-card__play"><span>▶</span></div>
    </a>

    <div class="movie-card__body">
        <a class="movie-card__title" href="<?= $link ?>"><?= e($title['title']) ?></a>
        <div class="movie-card__meta">
            <span><?= e((string)$title['year']) ?></span>
            <span class="dot">•</span>
            <span><?= $title['type'] === 'series' ? 'Сериал' : 'Фильм' ?></span>
            <?php if (!empty($title['age_limit'])): ?>
                <span class="dot">•</span><span><?= e($title['age_limit']) ?></span>
            <?php endif; ?>
        </div>
        <?php if ($price > 0): ?>
            <div class="movie-card__price"><?= e(price($price)) ?> · купить навсегда</div>
        <?php endif; ?>
    </div>

    <!-- Кнопка избранного: работает без перезагрузки страницы -->
    <button class="movie-card__fav <?= $isFavorite ? 'is-active' : '' ?>"
            type="button"
            data-favorite="<?= (int)$title['id'] ?>"
            aria-pressed="<?= $isFavorite ? 'true' : 'false' ?>"
            aria-label="Добавить в избранное"
            title="<?= $isFavorite ? 'Убрать из избранного' : 'Добавить в избранное' ?>">♥</button>

    <?php if ($price > 0): ?>
        <button class="movie-card__cart" type="button"
                data-cart-add="<?= (int)$title['id'] ?>"
                title="Добавить в корзину"
                aria-label="Добавить «<?= e($title['title']) ?>» в корзину">🛒</button>
    <?php endif; ?>
</article>
