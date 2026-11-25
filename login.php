<?php
/**
 * login.php — авторизация через сессии.
 * Работает и с AJAX (JSON-ответ без перезагрузки), и с обычным POST.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

if (is_logged_in()) {
    redirect(url('cabinet.php'));
}

$error  = '';
$login  = '';
$next   = (string)($_GET['next'] ?? $_POST['next'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim((string)($_POST['login'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');

    // CSRF: форма должна прийти с токеном текущей сессии
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $error = 'Сессия устарела. Обновите страницу и попробуйте снова.';
        if (is_ajax()) {
            json_out(['ok' => false, 'errors' => [], 'message' => $error], 403);
        }
    } else {
        [$ok, $message] = attempt_login($login, $pass);

        if ($ok) {
            $user = current_user();
            $redirect = $next !== ''
                ? $next
                : (($user['role'] ?? '') === 'admin' ? url('admin/index.php') : url('cabinet.php'));

            if (is_ajax()) {
                json_out(['ok' => true, 'message' => $message, 'redirect' => $redirect]);
            }
            flash($message, 'success');
            redirect($redirect);
        }

        $error = $message;

        if (is_ajax()) {
            json_out(['ok' => false, 'errors' => ['login' => $error], 'message' => $error], 422);
        }
    }
}

$page_title = 'Вход';
$page_scripts = ['auth.js'];
require __DIR__ . '/includes/header.php';
?>

<!-- ================= СТРАНИЦА ВХОДА ================= -->
<div class="auth-page">
    <div class="auth-card">
        <!-- Левая декоративная часть с преимуществами сервиса -->
        <aside class="auth-card__side">
            <div>
                <a class="logo" href="<?= url('index.php') ?>">
                    <img class="logo__img" src="<?= asset('img/logos/logo.svg') ?>" alt="Illuminos">
                </a>
                <h2 class="u-mt-28">С возвращением</h2>
                <p>Войдите, чтобы продолжить просмотр с того же места, где остановились.</p>

                <ul class="auth-card__features">
                    <li><span class="dot">✦</span> История просмотра синхронизируется</li>
                    <li><span class="dot">✦</span> Избранное всегда под рукой</li>
                    <li><span class="dot">✦</span> Персональные рекомендации</li>
                    <li><span class="dot">✦</span> Управление подпиской</li>
                </ul>
            </div>
            <p class="u-fs-12-5 u-text-muted-2 u-pos-relative u-z-1">
                Нет аккаунта? <a class="u-text-accent" href="<?= url('register.php') ?>">Зарегистрироваться</a>
            </p>
        </aside>

        <!-- Форма авторизации: AJAX-валидация без перезагрузки + обычный POST -->
        <form class="auth-form js-auth-form" data-mode="login" id="loginForm" method="post" action="<?= url('login.php') ?>" novalidate>
            <?= csrf_field() ?>
            <h1>Вход в аккаунт</h1>
            <p class="auth-form__sub">Используйте логин или e-mail.</p>

            <?php if ($error): ?>
                <div class="alert alert--error"><?= e($error) ?></div>
            <?php endif; ?>

            <input type="hidden" name="next" value="<?= e($next) ?>">

            <div class="field">
                <label class="field__label" for="login">Логин или e-mail</label>
                <input class="field__input" type="text" id="login" name="login"
                       value="<?= e($login) ?>" placeholder="admin" required autofocus>
                <div class="field__error" data-error="login"></div>
            </div>

            <div class="field">
                <label class="field__label" for="password">Пароль</label>
                <input class="field__input" type="password" id="password" name="password"
                       placeholder="••••••••" required>
                <div class="field__error" data-error="password"></div>
            </div>

            <div class="alert alert--error js-form-error u-d-none"></div>

            <button class="btn btn--primary btn--block" type="submit">Войти</button>

            <p class="auth-switch">Нет аккаунта? <a href="<?= url('register.php') ?>">Создать</a></p>

            <!-- Демонстрационные аккаунты для проверки трёх ролей -->
            <div class="alert alert--info u-mt-18 u-fs-12-5">
                <strong>Демо-аккаунты:</strong><br>
                администратор — <b>admin / admin123</b><br>
                подписчик — <b>anna_s / subscriber123</b><br>
                пользователь — <b>dmitry_k / user123</b>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
