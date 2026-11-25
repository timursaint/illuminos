<?php
/**
 * includes/auth_modals.php
 * Модальные окна входа и регистрации для главной страницы:
 * требование практики — регистрация и авторизация доступны с главной.
 * Формы отправляются через fetch в login.php / register.php (AJAX).
 */
?>
<!-- ================= МОДАЛЬНОЕ ОКНО: ВХОД ================= -->
<div class="modal-auth" id="authLoginModal" data-auth-modal="login" hidden>
    <div class="modal-auth__backdrop" data-auth-close></div>
    <div class="modal-auth__box" role="dialog" aria-modal="true" aria-labelledby="loginModalTitle">
        <button class="modal-auth__close" type="button" data-auth-close aria-label="Закрыть">×</button>

        <form class="auth-form js-auth-form" data-mode="login" method="post"
              action="<?= url('login.php') ?>" novalidate>
            <?= csrf_field() ?>
            <h2 class="u-fs-26 u-mb-6" id="loginModalTitle">Вход в аккаунт</h2>
            <p class="auth-form__sub">Используйте логин или e-mail.</p>

            <div class="field">
                <label class="field__label" for="modal_login">Логин или e-mail</label>
                <input class="field__input" type="text" id="modal_login" name="login"
                       placeholder="admin" required autocomplete="username">
                <div class="field__error" data-error="login"></div>
            </div>

            <div class="field">
                <label class="field__label" for="modal_password">Пароль</label>
                <input class="field__input" type="password" id="modal_password" name="password"
                       placeholder="••••••••" required autocomplete="current-password">
                <div class="field__error" data-error="password"></div>
            </div>

            <div class="alert alert--error js-form-error u-d-none"></div>

            <button class="btn btn--primary btn--block" type="submit">Войти</button>

            <p class="auth-switch">
                Нет аккаунта? <a href="<?= url('register.php') ?>" data-auth-open="register">Зарегистрироваться</a>
            </p>

            <div class="alert alert--info u-mt-16 u-fs-12-5">
                Демо: <b>admin / admin123</b>, <b>anna_s / subscriber123</b>, <b>dmitry_k / user123</b>
            </div>
        </form>
    </div>
</div>

<!-- ================= МОДАЛЬНОЕ ОКНО: РЕГИСТРАЦИЯ ================= -->
<div class="modal-auth" id="authRegisterModal" data-auth-modal="register" hidden>
    <div class="modal-auth__backdrop" data-auth-close></div>
    <div class="modal-auth__box" role="dialog" aria-modal="true" aria-labelledby="registerModalTitle">
        <button class="modal-auth__close" type="button" data-auth-close aria-label="Закрыть">×</button>

        <form class="auth-form js-auth-form" data-mode="register" method="post"
              action="<?= url('register.php') ?>" novalidate>
            <?= csrf_field() ?>
            <h2 class="u-fs-26 u-mb-6" id="registerModalTitle">Регистрация</h2>
            <p class="auth-form__sub">Заполните данные — это займёт меньше минуты.</p>

            <div class="form-row">
                <div class="field">
                    <label class="field__label" for="modal_last_name">Фамилия</label>
                    <input class="field__input" type="text" id="modal_last_name" name="last_name"
                           placeholder="Иванов" required autocomplete="family-name">
                    <div class="field__error" data-error="last_name"></div>
                </div>
                <div class="field">
                    <label class="field__label" for="modal_first_name">Имя</label>
                    <input class="field__input" type="text" id="modal_first_name" name="first_name"
                           placeholder="Иван" required autocomplete="given-name">
                    <div class="field__error" data-error="first_name"></div>
                </div>
            </div>

            <div class="field">
                <label class="field__label" for="modal_middle_name">Отчество <span class="u-text-muted-2">(необязательно)</span></label>
                <input class="field__input" type="text" id="modal_middle_name" name="middle_name" placeholder="Иванович">
                <div class="field__error" data-error="middle_name"></div>
            </div>

            <div class="form-row">
                <div class="field">
                    <label class="field__label" for="modal_login_new">Логин</label>
                    <input class="field__input" type="text" id="modal_login_new" name="login"
                           placeholder="ivanov_2026" required autocomplete="username">
                    <div class="field__error" data-error="login"></div>
                </div>
                <div class="field">
                    <label class="field__label" for="modal_email">E-mail</label>
                    <input class="field__input" type="email" id="modal_email" name="email"
                           placeholder="ivan@mail.ru" required autocomplete="email">
                    <div class="field__error" data-error="email"></div>
                </div>
            </div>

            <div class="form-row">
                <div class="field">
                    <label class="field__label" for="modal_password_new">Пароль</label>
                    <input class="field__input" type="password" id="modal_password_new" name="password"
                           placeholder="Минимум 8 символов" required autocomplete="new-password">
                    <div class="field__error" data-error="password"></div>
                </div>
                <div class="field">
                    <label class="field__label" for="modal_password_repeat">Повторите пароль</label>
                    <input class="field__input" type="password" id="modal_password_repeat" name="password_repeat"
                           placeholder="Ещё раз" required autocomplete="new-password">
                    <div class="field__error" data-error="password_repeat"></div>
                </div>
            </div>

            <div class="field">
                <label class="checkbox">
                    <input type="checkbox" name="consent" value="1">
                    <span>Я согласен с <a class="u-text-accent" href="#">правилами регистрации</a>
                          и обработкой персональных данных</span>
                </label>
                <div class="field__error" data-error="consent"></div>
            </div>

            <div class="alert alert--error js-form-error u-d-none"></div>

            <button class="btn btn--primary btn--block" type="submit">Создать аккаунт</button>

            <p class="auth-switch">
                Уже зарегистрированы? <a href="<?= url('login.php') ?>" data-auth-open="login">Войти</a>
            </p>
        </form>
    </div>
</div>
