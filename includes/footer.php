<?php
/**
 * includes/footer.php
 * Подвал сайта: навигация, контакты, способы оплаты и подключение скриптов.
 */
?>
</main>

<!-- ================= ПОДВАЛ ================= -->
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col footer-col--brand">
                <img class="footer-logo" src="<?= asset('img/logos/logo.svg') ?>" alt="Illuminos">
                <p>Онлайн-кинотеатр нового поколения: фильмы, сериалы и мультфильмы
                   в Full HD и 4K без рекламы. Смотрите где угодно и когда угодно.</p>
                <div class="footer-social">
                    <a href="#" aria-label="ВКонтакте">VK</a>
                    <a href="#" aria-label="Telegram">TG</a>
                    <a href="#" aria-label="YouTube">YT</a>
                </div>
            </div>

            <div class="footer-col">
                <h4>Каталог</h4>
                <a href="<?= url('movies.php') ?>">Фильмы</a>
                <a href="<?= url('series.php') ?>">Сериалы</a>
                <a href="<?= url('catalog.php?is_new=1') ?>">Новинки</a>
                <a href="<?= url('catalog.php?sort=rating') ?>">Лучшее по рейтингу</a>
            </div>

            <div class="footer-col">
                <h4>Сервис</h4>
                <a href="<?= url('pricing.php') ?>">Тарифы и оплата</a>
                <a href="<?= url('cabinet.php') ?>">Личный кабинет</a>
                <a href="<?= url('register.php') ?>">Регистрация</a>
                <a href="<?= url('docs/palette.html') ?>">Дизайн-система</a>
            </div>

            <div class="footer-col">
                <h4>Контакты</h4>
                <a href="mailto:<?= SITE_EMAIL ?>"><?= SITE_EMAIL ?></a>
                <a href="tel:<?= preg_replace('/\D/', '', SITE_PHONE) ?>"><?= SITE_PHONE ?></a>
                <p class="footer-address"><?= SITE_ADDRESS ?></p>
                <p class="footer-address">Поддержка 24/7</p>
            </div>
        </div>

        <div class="footer-bottom">
            <span>© <?= date('Y') ?> <?= SITE_NAME ?>. Учебный проект по ПМ.01, специальность 09.02.07</span>
            <div class="pay-methods" aria-label="Способы оплаты">
                <span>МИР</span><span>Visa</span><span>Mastercard</span><span>СБП</span>
            </div>
        </div>
    </div>
</footer>

<!-- Модальное окно плеера: используется для трейлеров и просмотра -->
<div class="modal-player" id="playerModal" hidden>
    <div class="modal-player__backdrop" data-close-player></div>
    <div class="modal-player__box" role="dialog" aria-modal="true" aria-label="Плеер">
        <button class="modal-player__close" type="button" data-close-player aria-label="Закрыть">×</button>
        <video id="playerVideo" controls playsinline preload="none"></video>
        <div class="modal-player__caption" id="playerCaption"></div>
    </div>
</div>

<script>
    // Адрес API передаём в JS, чтобы не хардкодить путь в скриптах
    window.APP = { base: '<?= BASE_URL ?>', api: '<?= url('api') ?>' };
    window.APP_USER = <?= is_logged_in() ? 'true' : 'false' ?>;
</script>
<script src="<?= asset_versioned('vendor/bootstrap.bundle.min.js') ?>" defer></script>
<script src="<?= asset_versioned('js/main.js') ?>" defer></script>
<?php if (!empty($page_scripts)): ?>
    <?php foreach ($page_scripts as $script): ?>
        <script src="<?= asset_versioned('js/' . $script) ?>" defer></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
