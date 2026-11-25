<?php
/**
 * pricing.php — страница тарифов подписки.
 * Показывает перечень тарифов и их возможности, FAQ и текущую подписку.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

$tariffs = db()->query('SELECT * FROM tariffs WHERE is_active = 1 ORDER BY sort_order')->fetchAll();
$user    = current_user();
$subscription = active_subscription();

$page_title = 'Подписка и тарифы';
$active     = 'pricing';
require __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="page-head u-text-center u-max-w-760 u-m-0-auto">
        <h1>Одна подписка — весь Illuminos</h1>
        <p class="u-m-0-auto">
            Без рекламы, без скрытых платежей. Выберите срок — и смотрите всё,
            что есть в каталоге, на любом устройстве.
        </p>
    </div>

    <?php if ($subscription): ?>
        <div class="sub-card u-mb-28">
            <h2 class="sub-card__title">Ваша подписка «<?= e($subscription['tariff_name']) ?>» активна</h2>
            <p class="sub-card__meta">
                Действует до <?= e(date('d.m.Y', strtotime($subscription['ends_at']))) ?>.
                При покупке нового тарифа срок продлевается.
            </p>
        </div>
    <?php endif; ?>

    <!-- ================= ТАРИФЫ ================= -->
    <div class="plans">
        <?php foreach ($tariffs as $tariff): ?>
            <?php
            $features = array_values(array_filter(array_map('trim', explode("\n", (string)$tariff['features']))));
            $periodText = match ((int)$tariff['period_days']) {
                30  => 'на 1 месяц',
                92  => 'на 3 месяца',
                365 => 'на 12 месяцев',
                default => 'на ' . $tariff['period_days'] . ' дней',
            };
            ?>
            <article class="plan <?= (int)$tariff['is_popular'] ? 'plan--popular' : '' ?>">
                <?php if ((int)$tariff['is_popular']): ?>
                    <span class="plan__ribbon">Выбор зрителей</span>
                <?php endif; ?>

                <div>
                    <h2 class="plan__name"><?= e($tariff['name']) ?></h2>
                    <div class="plan__period"><?= e($periodText) ?></div>
                </div>

                <div class="plan__price">
                    <strong><?= e(price((float)$tariff['price'])) ?></strong>
                    <?php if ($tariff['old_price']): ?>
                        <s><?= e(price((float)$tariff['old_price'])) ?></s>
                    <?php endif; ?>
                </div>

                <span class="plan__quality"><?= e($tariff['quality']) ?> · до <?= (int)$tariff['devices'] ?> <?= plural((int)$tariff['devices'], 'устройства', 'устройств', 'устройств') ?></span>

                <ul class="plan__features">
                    <?php foreach ($features as $feature): ?>
                        <li><span class="check">✓</span> <?= e($feature) ?></li>
                    <?php endforeach; ?>
                </ul>

                <a class="btn <?= (int)$tariff['is_popular'] ? 'btn--primary' : 'btn--outline' ?> btn--block"
                   href="<?= url('checkout.php?tariff=' . urlencode($tariff['code'])) ?>">
                    Оформить
                </a>
            </article>
        <?php endforeach; ?>
    </div>

    <!-- ================= СРАВНЕНИЕ ================= -->
    <section class="section">
        <div class="section__head">
            <div>
                <h2 class="section__title">Что входит в подписку</h2>
                <p class="section__sub">Все тарифы включают полный каталог — отличается только качество и количество устройств</p>
            </div>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Возможность</th>
                        <?php foreach ($tariffs as $tariff): ?>
                            <th><?= e($tariff['name']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Цена</td>
                        <?php foreach ($tariffs as $tariff): ?>
                            <td><strong><?= e(price((float)$tariff['price'])) ?></strong></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td>Срок</td>
                        <?php foreach ($tariffs as $tariff): ?>
                            <td><?= (int)$tariff['period_days'] ?> дней</td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td>Качество</td>
                        <?php foreach ($tariffs as $tariff): ?>
                            <td><?= e($tariff['quality']) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td>Устройств одновременно</td>
                        <?php foreach ($tariffs as $tariff): ?>
                            <td><?= (int)$tariff['devices'] ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td>Без рекламы</td>
                        <?php foreach ($tariffs as $tariff): ?>
                            <td>✓</td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td>Личные рекомендации</td>
                        <?php foreach ($tariffs as $tariff): ?>
                            <td>✓</td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td>Скачивание для офлайна</td>
                        <?php foreach ($tariffs as $tariff): ?>
                            <td><?= (int)$tariff['period_days'] >= 365 ? '✓' : '—' ?></td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ================= FAQ ================= -->
    <section class="section">
        <div class="section__head">
            <div>
                <h2 class="section__title">Частые вопросы</h2>
            </div>
        </div>

        <div class="faq">
            <?php
            $faq = [
                ['Как оплатить подписку?', 'Доступны банковская карта МИР/Visa/Mastercard, СБП по QR-коду и электронный кошелёк. Оплата проходит на защищённой странице платёжного шлюза.'],
                ['Можно ли отменить подписку?', 'Да, в любой момент в личном кабинете. Доступ сохранится до конца оплаченного периода, следующие списания не произойдут.'],
                ['Что будет, если я оплачу новый тариф при активной подписке?', 'Срок продлится: новый период начнётся с даты окончания текущей подписки.'],
                ['Есть ли промокоды?', 'Да. Например, <b>START15</b> даёт скидку 15%, <b>ILLUMINOS20</b> — 20%. Промокод вводится на странице оплаты.'],
                ['На скольких устройствах можно смотреть?', 'Зависит от тарифа: от 1 до 5 устройств одновременно.'],
            ];
            foreach ($faq as $item): ?>
                <div class="faq__item">
                    <button class="faq__q" type="button">
                        <?= e($item[0]) ?>
                        <span class="u-text-accent">+</span>
                    </button>
                    <div class="faq__a"><?= $item[1] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
