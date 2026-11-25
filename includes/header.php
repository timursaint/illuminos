<?php
/**
 * includes/header.php
 * Общая шапка всех страниц: подключение стилей, логотип, меню,
 * живой поиск, блок пользователя и flash-уведомления.
 *
 * Перед подключением страница может задать переменные:
 *   $page_title — заголовок вкладки браузера
 *   $active     — активный пункт меню: home | movies | series | pricing | cabinet
 */
$user       = current_user();
$page_title = $page_title ?? SITE_NAME;
$active     = $active ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Illuminos — онлайн-кинотеатр: фильмы и сериалы в высоком качестве, персональные рекомендации и подписка без рекламы.">
    <title><?= e($page_title) ?> — <?= SITE_NAME ?></title>

    <!-- Bootstrap 5 (локальная копия, работает без интернета) -->
    <link rel="stylesheet" href="<?= asset_versioned('vendor/bootstrap.min.css') ?>">
    <!-- Основная таблица стилей проекта -->
    <link rel="stylesheet" href="<?= asset_versioned('css/style.css') ?>">
    <link rel="icon" href="<?= asset('img/logos/logo.svg') ?>" type="image/svg+xml">
</head>
<body>

<!-- Индикатор загрузки страницы -->
<div class="page-loader" id="pageLoader" aria-hidden="true"><span></span></div>

<!-- ================= ШАПКА САЙТА ================= -->
<header class="site-header" id="siteHeader">
    <div class="container site-header__inner">
        <a class="logo" href="<?= url('index.php') ?>" aria-label="Illuminos — на главную">
            <img class="logo__img" src="<?= asset('img/logos/logo.svg') ?>" alt="Illuminos">
        </a>

        <nav class="main-nav" id="mainNav" aria-label="Основная навигация">
            <a class="main-nav__link <?= $active === 'home' ? 'is-active' : '' ?>" href="<?= url('index.php') ?>">Главная</a>
            <a class="main-nav__link <?= $active === 'movies' ? 'is-active' : '' ?>" href="<?= url('movies.php') ?>">Фильмы</a>
            <a class="main-nav__link <?= $active === 'series' ? 'is-active' : '' ?>" href="<?= url('series.php') ?>">Сериалы</a>
            <a class="main-nav__link <?= $active === 'pricing' ? 'is-active' : '' ?>" href="<?= url('pricing.php') ?>">Подписка</a>
            <?php if ($user): ?>
                <a class="main-nav__link <?= $active === 'cabinet' ? 'is-active' : '' ?>" href="<?= url('cabinet.php') ?>">Мой Illuminos</a>
            <?php endif; ?>
            <?php if (is_admin()): ?>
                <a class="main-nav__link main-nav__link--admin" href="<?= url('admin/index.php') ?>">Админ-панель</a>
            <?php endif; ?>
        </nav>

        <!-- Живой поиск: результаты подгружаются через api/search.php -->
        <form class="search" id="searchForm" action="<?= url('catalog.php') ?>" method="get" autocomplete="off" role="search">
            <svg class="search__icon" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="11" cy="11" r="7"></circle>
                <line x1="21" y1="21" x2="16.4" y2="16.4"></line>
            </svg>
            <input class="search__input" type="search" name="q" id="searchInput"
                   placeholder="Фильмы, сериалы, актёры" aria-label="Поиск по каталогу"
                   value="<?= e($_GET['q'] ?? '') ?>">
            <div class="search__results" id="searchResults" hidden></div>
        </form>

        <div class="header-actions">
            <!-- Корзина покупки фильмов -->
            <a class="cart-link" href="<?= url('cart.php') ?>" aria-label="Корзина">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L21 8H6"></path>
                    <circle cx="10" cy="20" r="1.4"></circle>
                    <circle cx="18" cy="20" r="1.4"></circle>
                </svg>
                <?php $cartCount = cart_count(); ?>
                <span class="cart-link__badge" id="cartBadge" <?= $cartCount ? '' : 'hidden' ?>><?= $cartCount ?></span>
            </a>

            <?php if ($user): ?>
                <div class="user-menu" id="userMenu">
                    <button class="user-menu__toggle" type="button" aria-haspopup="true" aria-expanded="false">
                        <?php if (!empty($user['avatar'])): ?>
                            <img class="avatar" src="<?= url('uploads/avatars/' . $user['avatar']) ?>" alt="">
                        <?php else: ?>
                            <span class="avatar avatar--initials"><?= e(user_initials($user)) ?></span>
                        <?php endif; ?>
                        <span class="user-menu__name"><?= e($user['first_name']) ?></span>
                        <svg class="user-menu__chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div class="user-menu__dropdown">
                        <div class="user-menu__head">
                            <strong><?= e($user['last_name'] . ' ' . $user['first_name']) ?></strong>
                            <span><?= e($user['email']) ?></span>
                            <span class="role-chip role-chip--<?= e($user['role']) ?>"><?= e(role_name($user['role'])) ?></span>
                        </div>
                        <a href="<?= url('cabinet.php') ?>">Личный кабинет</a>
                        <a href="<?= url('cabinet.php?tab=favorites') ?>">Избранное</a>
                        <a href="<?= url('cabinet.php?tab=history') ?>">История просмотра</a>
                        <a href="<?= url('pricing.php') ?>">Моя подписка</a>
                        <?php if (is_admin()): ?>
                            <a href="<?= url('admin/index.php') ?>">Админ-панель</a>
                        <?php endif; ?>
                        <a class="user-menu__logout" href="<?= url('logout.php') ?>">Выйти</a>
                    </div>
                </div>
            <?php else: ?>
                <a class="btn btn--ghost btn--sm" href="<?= url('login.php') ?>"
                   <?= !empty($auth_modals) ? 'data-auth-open="login"' : '' ?>>Войти</a>
                <a class="btn btn--primary btn--sm" href="<?= url('register.php') ?>"
                   <?= !empty($auth_modals) ? 'data-auth-open="register"' : '' ?>>Регистрация</a>
            <?php endif; ?>

            <button class="burger" id="burger" type="button" aria-label="Открыть меню" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>

<!-- Flash-сообщения (появляются после действий и исчезают сами) -->
<?php $messages = take_flash(); ?>
<?php if ($messages): ?>
    <div class="toasts" id="toasts">
        <?php foreach ($messages as $m): ?>
            <div class="toast toast--<?= e($m['type']) ?>"><?= e($m['text']) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<main class="site-main">
