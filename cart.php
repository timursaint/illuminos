<?php
/**
 * cart.php — корзина покупки фильмов.
 * Пользователь может изменить количество, удалить позицию, очистить корзину
 * и оформить заказ. Все действия доступны без перезагрузки (api/cart.php).
 */
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/includes/catalog.php';

if (!is_logged_in()) {
    flash('Войдите, чтобы открыть корзину.', 'warning');
    redirect(url('login.php?next=' . urlencode('cart.php')));
}

$items = cart_items();
$total = cart_total();
$purchased = purchased_ids();

$page_title = 'Корзина';
$active     = 'cart';
require __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="page-head">
        <nav class="breadcrumbs" aria-label="Хлебные крошки">
            <a href="<?= url('index.php') ?>">Главная</a><span>/</span><span>Корзина</span>
        </nav>
        <h1>Корзина</h1>
        <p>Купленные фильмы остаются доступны навсегда — даже без подписки.</p>
    </div>

    <?php if (!$items): ?>
        <div class="empty-state">
            <h3>Корзина пуста</h3>
            <p>Добавьте фильмы с пометкой «купить навсегда» — они появятся здесь.</p>
            <a class="btn btn--primary" href="<?= url('catalog.php') ?>">Перейти в каталог</a>
        </div>
    <?php else: ?>
        <div class="cart-layout">
            <!-- ===== Позиции ===== -->
            <div class="cart-items" id="cartItems">
                <?php foreach ($items as $item): ?>
                    <article class="cart-item"
                             data-cart-item
                             data-title-id="<?= (int)$item['title_id'] ?>"
                             data-price="<?= (float)$item['price'] ?>"
                             data-quantity="<?= (int)$item['quantity'] ?>">
                        <a href="<?= url('title.php?slug=' . urlencode($item['slug'])) ?>">
                            <img class="cart-item__poster" src="<?= poster_src($item) ?>" alt="">
                        </a>

                        <div>
                            <div class="cart-item__name">
                                <a href="<?= url('title.php?slug=' . urlencode($item['slug'])) ?>"><?= e($item['title']) ?></a>
                            </div>
                            <div class="cart-item__meta">
                                <?= $item['type'] === 'series' ? 'Сериал' : 'Фильм' ?> ·
                                <?= e((string)$item['year']) ?> ·
                                <?= e(price((float)$item['price'])) ?> за лицензию
                            </div>
                            <div class="cart-item__price" data-line-total>
                                <?= e(price((float)$item['price'] * (int)$item['quantity'])) ?>
                            </div>
                        </div>

                        <div class="cart-item__controls">
                            <div class="qty" role="group" aria-label="Количество лицензий">
                                <button type="button" data-qty-minus aria-label="Меньше">−</button>
                                <span data-qty-value><?= (int)$item['quantity'] ?></span>
                                <button type="button" data-qty-plus aria-label="Больше">+</button>
                            </div>
                            <button class="icon-btn icon-btn--danger" type="button"
                                    data-cart-remove title="Удалить из корзины">🗑</button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <!-- ===== Итог ===== -->
            <aside class="checkout-card cart-summary">
                <h2>Итого</h2>

                <div class="summary__row">
                    <span>Позиций</span>
                    <strong id="cartPositions"><?= count($items) ?></strong>
                </div>
                <div class="summary__row">
                    <span>Лицензий</span>
                    <strong id="cartUnits"><?= cart_count() ?></strong>
                </div>
                <div class="summary__row">
                    <span>Стоимость</span>
                    <strong id="cartTotal"><?= e(price($total)) ?></strong>
                </div>

                <div class="summary__total">
                    <span>К оплате</span>
                    <strong id="cartGrandTotal"><?= e(price($total)) ?></strong>
                </div>

                <p class="summary__note">
                    После оплаты фильмы появятся в разделе «Мои покупки» личного кабинета
                    и будут доступны без подписки.
                </p>

                <a class="btn btn--primary btn--block" href="<?= url('checkout.php?cart=1') ?>">
                    Оформить заказ
                </a>
                <button class="btn btn--outline btn--block u-mt-10" type="button" id="cartClear">
                    Очистить корзину
                </button>
                <a class="btn btn--ghost btn--block u-mt-10" href="<?= url('catalog.php') ?>">
                    Продолжить выбор
                </a>
            </aside>
        </div>
    <?php endif; ?>

    <?php if ($purchased): ?>
        <section class="section">
            <div class="section__head">
                <div>
                    <h2 class="section__title">Уже куплено</h2>
                    <p class="section__sub">Эти фильмы доступны без подписки</p>
                </div>
                <a class="section__link" href="<?= url('cabinet.php?tab=purchases') ?>">Все покупки →</a>
            </div>
            <div class="cards-grid">
                <?php
                $stmt = db()->prepare(
                    'SELECT t.* FROM purchases p JOIN titles t ON t.id = p.title_id
                      WHERE p.user_id = ? ORDER BY p.purchased_at DESC LIMIT 6'
                );
                $stmt->execute([(int)current_user()['id']]);
                foreach ($stmt->fetchAll() as $title):
                    $is_favorite = in_array((int)$title['id'], favorites_ids(), true);
                    include __DIR__ . '/includes/card.php';
                endforeach;
                ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
