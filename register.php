<?php
/**
 * register.php — регистрация нового пользователя.
 * Валидация выполняется и на клиенте (assets/js/auth.js, без перезагрузки),
 * и на сервере — это обязательное условие безопасности.
 * При AJAX-запросе возвращается JSON, при обычном POST — редирект.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

if (is_logged_in()) {
    redirect(url('cabinet.php'));
}

$errors = [];
$old = [
    'last_name'   => '',
    'first_name'  => '',
    'middle_name' => '',
    'login'       => '',
    'email'       => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF: без валидного токена регистрация не выполняется
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $errors['csrf'] = 'Сессия устарела. Обновите страницу и попробуйте снова.';
    }

    $old['last_name']   = trim((string)($_POST['last_name'] ?? ''));
    $old['first_name']  = trim((string)($_POST['first_name'] ?? ''));
    $old['middle_name'] = trim((string)($_POST['middle_name'] ?? ''));
    $old['login']       = trim((string)($_POST['login'] ?? ''));
    $old['email']       = trim((string)($_POST['email'] ?? ''));
    $password           = (string)($_POST['password'] ?? '');
    $passwordRepeat     = (string)($_POST['password_repeat'] ?? '');
    $consent            = !empty($_POST['consent']);

    if ($old['last_name'] === '' || mb_strlen($old['last_name']) < 2) {
        $errors['last_name'] = 'Укажите фамилию (минимум 2 символа).';
    }
    if ($old['first_name'] === '' || mb_strlen($old['first_name']) < 2) {
        $errors['first_name'] = 'Укажите имя (минимум 2 символа).';
    }
    if (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $old['login'])) {
        $errors['login'] = 'Логин: 3–32 символа, только латиница, цифры и «_».';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный e-mail.';
    }
    if (mb_strlen($password) < 8) {
        $errors['password'] = 'Пароль должен содержать минимум 8 символов.';
    } elseif (!preg_match('/[A-Za-zА-Яа-я]/u', $password) || !preg_match('/\d/', $password)) {
        $errors['password'] = 'Пароль должен содержать буквы и хотя бы одну цифру.';
    }
    if ($password !== $passwordRepeat) {
        $errors['password_repeat'] = 'Пароли не совпадают.';
    }
    if (!$consent) {
        $errors['consent'] = 'Подтвердите согласие с правилами регистрации.';
    }

    // Проверка уникальности логина и e-mail
    if (!$errors) {
        $stmt = db()->prepare('SELECT login, email FROM users WHERE login = ? OR email = ? LIMIT 1');
        $stmt->execute([$old['login'], $old['email']]);
        $existing = $stmt->fetch();

        if ($existing) {
            if (mb_strtolower($existing['login']) === mb_strtolower($old['login'])) {
                $errors['login'] = 'Этот логин уже занят.';
            }
            if (mb_strtolower($existing['email']) === mb_strtolower($old['email'])) {
                $errors['email'] = 'На этот e-mail уже зарегистрирован аккаунт.';
            }
        }
    }

    if (!$errors) {
        $stmt = db()->prepare(
            'INSERT INTO users (last_name, first_name, middle_name, login, email, password_hash, role)
             VALUES (?, ?, ?, ?, ?, ?, "user")'
        );
        $stmt->execute([
            $old['last_name'],
            $old['first_name'],
            $old['middle_name'] !== '' ? $old['middle_name'] : null,
            $old['login'],
            mb_strtolower($old['email']),
            password_hash($password, PASSWORD_DEFAULT),
        ]);

        // Автоматический вход после регистрации
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)db()->lastInsertId();
        $_SESSION['role']    = 'user';

        if (is_ajax()) {
            json_out([
                'ok'       => true,
                'message'  => 'Регистрация успешна. Добро пожаловать в Illuminos!',
                'redirect' => url('cabinet.php'),
            ]);
        }

        flash('Регистрация прошла успешно. Добро пожаловать!', 'success');
        redirect(url('cabinet.php'));
    }

    if (is_ajax()) {
        json_out(['ok' => false, 'errors' => $errors, 'message' => 'Проверьте заполнение полей.'], 422);
    }
}

$page_title = 'Регистрация';
$page_scripts = ['auth.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <!-- Левая декоративная часть -->
        <aside class="auth-card__side">
            <div>
                <a class="logo" href="<?= url('index.php') ?>">
                    <img class="logo__img" src="<?= asset('img/logos/logo.svg') ?>" alt="Illuminos">
                </a>
                <h2 class="u-mt-28">Кино начинается здесь</h2>
                <p>Создайте аккаунт, чтобы собирать избранное, получать персональные подборки и смотреть без рекламы.</p>

                <ul class="auth-card__features">
                    <li><span class="dot">✦</span> Более 5000 фильмов и сериалов</li>
                    <li><span class="dot">✦</span> Качество Full HD и 4K Ultra HD</li>
                    <li><span class="dot">✦</span> Просмотр на любом устройстве</li>
                    <li><span class="dot">✦</span> Персональные рекомендации</li>
                </ul>
            </div>
            <p class="u-fs-12-5 u-text-muted-2 u-pos-relative u-z-1">
                Уже есть аккаунт? <a class="u-text-accent" href="<?= url('login.php') ?>">Войти</a>
            </p>
        </aside>

        <!-- Форма регистрации -->
        <form class="auth-form js-auth-form" data-mode="register" id="registerForm" method="post" action="<?= url('register.php') ?>" novalidate>
            <?= csrf_field() ?>
            <h1>Регистрация</h1>
            <p class="auth-form__sub">Заполните данные — это займёт меньше минуты.</p>

            <?php if ($errors && !is_ajax()): ?>
                <!-- Серверная валидация: ошибки видны и без JavaScript -->
                <div class="alert alert--error">
                    <?php foreach ($errors as $message): ?>
                        <div><?= e($message) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="form-row">
                <div class="field">
                    <label class="field__label" for="last_name">Фамилия</label>
                    <input class="field__input" type="text" id="last_name" name="last_name"
                           value="<?= e($old['last_name']) ?>" placeholder="Иванов" required>
                    <div class="field__error" data-error="last_name"></div>
                </div>
                <div class="field">
                    <label class="field__label" for="first_name">Имя</label>
                    <input class="field__input" type="text" id="first_name" name="first_name"
                           value="<?= e($old['first_name']) ?>" placeholder="Иван" required>
                    <div class="field__error" data-error="first_name"></div>
                </div>
            </div>

            <div class="field">
                <label class="field__label" for="middle_name">Отчество <span class="u-text-muted-2">(необязательно)</span></label>
                <input class="field__input" type="text" id="middle_name" name="middle_name"
                       value="<?= e($old['middle_name']) ?>" placeholder="Иванович">
                <div class="field__error" data-error="middle_name"></div>
            </div>

            <div class="form-row">
                <div class="field">
                    <label class="field__label" for="login">Логин</label>
                    <input class="field__input" type="text" id="login" name="login"
                           value="<?= e($old['login']) ?>" placeholder="ivanov_2026" required>
                    <div class="field__error" data-error="login"></div>
                </div>
                <div class="field">
                    <label class="field__label" for="email">E-mail</label>
                    <input class="field__input" type="email" id="email" name="email"
                           value="<?= e($old['email']) ?>" placeholder="ivan@mail.ru" required>
                    <div class="field__error" data-error="email"></div>
                </div>
            </div>

            <div class="form-row">
                <div class="field">
                    <label class="field__label" for="password">Пароль</label>
                    <input class="field__input" type="password" id="password" name="password"
                           placeholder="Минимум 8 символов" required>
                    <div class="field__error" data-error="password"></div>
                </div>
                <div class="field">
                    <label class="field__label" for="password_repeat">Повторите пароль</label>
                    <input class="field__input" type="password" id="password_repeat" name="password_repeat"
                           placeholder="Ещё раз" required>
                    <div class="field__error" data-error="password_repeat"></div>
                </div>
            </div>

            <div class="field">
                <label class="checkbox">
                    <input type="checkbox" id="consent" name="consent" value="1">
                    <span>Я согласен с <a class="u-text-accent" href="#">правилами регистрации</a>
                          и обработкой персональных данных</span>
                </label>
                <div class="field__error" data-error="consent"></div>
            </div>

            <div class="alert alert--error js-form-error u-d-none"></div>

            <button class="btn btn--primary btn--block" type="submit">Создать аккаунт</button>

            <p class="auth-switch">Уже зарегистрированы? <a href="<?= url('login.php') ?>">Войти</a></p>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
