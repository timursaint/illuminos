<?php
/**
 * cabinet.php — личный кабинет: профиль, история просмотра,
 * избранное, подписка и оценки пользователя.
 * Доступен только авторизованным (сессия).
 */
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/includes/catalog.php';

require_auth();

$user = current_user();
$tab  = (string)($_GET['tab'] ?? 'overview');

/* ---------------- Обновление профиля и аватара ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_save'])) {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        flash('Сессия устарела. Повторите действие.', 'error');
        redirect(url('cabinet.php'));
    }

    $lastName   = trim((string)($_POST['last_name'] ?? ''));
    $firstName  = trim((string)($_POST['first_name'] ?? ''));
    $middleName = trim((string)($_POST['middle_name'] ?? ''));
    $email      = trim((string)($_POST['email'] ?? ''));

    $errors = [];
    if (mb_strlen($lastName) < 2)  { $errors[] = 'Фамилия — минимум 2 символа.'; }
    if (mb_strlen($firstName) < 2) { $errors[] = 'Имя — минимум 2 символа.'; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Некорректный e-mail.'; }

    if (!$errors) {
        // Проверяем, что e-mail не занят другим пользователем
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $stmt->execute([mb_strtolower($email), $user['id']]);
        if ($stmt->fetch()) {
            $errors[] = 'Этот e-mail уже используется другим аккаунтом.';
        }
    }

    $avatarName = $user['avatar'];

    // Загрузка аватара: проверяем тип и размер файла
    if (!$errors && !empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $info = @getimagesize($_FILES['avatar']['tmp_name']);
        $mime = $info['mime'] ?? '';

        if (!isset($allowed[$mime])) {
            $errors[] = 'Аватар должен быть JPG, PNG или WEBP.';
        } elseif ($_FILES['avatar']['size'] > UPLOAD_MAX_SIZE) {
            $errors[] = 'Размер аватара не должен превышать 5 МБ.';
        } else {
            $dir = __DIR__ . '/uploads/avatars';
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $avatarName = 'user_' . $user['id'] . '_' . time() . '.' . $allowed[$mime];
            move_uploaded_file($_FILES['avatar']['tmp_name'], $dir . '/' . $avatarName);
        }
    }

    if ($errors) {
        foreach ($errors as $error) {
            flash($error, 'error');
        }
    } else {
        $stmt = db()->prepare(
            'UPDATE users SET last_name = ?, first_name = ?, middle_name = ?, email = ?, avatar = ? WHERE id = ?'
        );
        $stmt->execute([$lastName, $firstName, $middleName !== '' ? $middleName : null, mb_strtolower($email), $avatarName, $user['id']]);
        flash('Профиль обновлён.', 'success');
    }

    redirect(url('cabinet.php'));
}

$favoriteIds = favorites_ids();
$subscription = active_subscription();

// --- Статистика кабинета ---
$stats = [
    'watched'   => (int)db()->query('SELECT COUNT(DISTINCT title_id) FROM watch_history WHERE user_id = ' . (int)$user['id'])->fetchColumn(),
    'favorites' => count($favoriteIds),
    'reviews'   => (int)db()->query('SELECT COUNT(*) FROM reviews WHERE user_id = ' . (int)$user['id'])->fetchColumn(),
    'purchases' => count(purchased_ids()),
];

// --- История просмотра: последний просмотр по каждому тайтлу ---
$stmt = db()->prepare(
    'SELECT t.*, h.progress, h.watched_at
       FROM watch_history h
       JOIN titles t ON t.id = h.title_id
       JOIN (
            SELECT title_id, MAX(id) AS last_id
              FROM watch_history
             WHERE user_id = ?
             GROUP BY title_id
       ) latest ON latest.last_id = h.id
      ORDER BY h.watched_at DESC
      LIMIT 30'
);
$stmt->execute([$user['id']]);
$history = decorate_titles($stmt->fetchAll());

// --- Избранное ---
$stmt = db()->prepare(
    'SELECT t.*, f.created_at AS added_at
       FROM favorites f
       JOIN titles t ON t.id = f.title_id
      WHERE f.user_id = ?
      ORDER BY f.created_at DESC'
);
$stmt->execute([$user['id']]);
$favorites = decorate_titles($stmt->fetchAll());

// --- Оценки и отзывы пользователя ---
$stmt = db()->prepare(
    'SELECT r.*, t.title, t.poster, t.slug, t.year, t.type
       FROM reviews r
       JOIN titles t ON t.id = r.title_id
      WHERE r.user_id = ?
      ORDER BY r.created_at DESC'
);
$stmt->execute([$user['id']]);
$myReviews = $stmt->fetchAll();

$page_title = 'Личный кабинет';
$active     = 'cabinet';
require __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="cabinet">
        <!-- ================= Боковое меню ================= -->
        <aside class="cabinet-side">
            <div class="cabinet-user">
                <?php if (!empty($user['avatar'])): ?>
                    <img class="avatar" src="<?= url('uploads/avatars/' . $user['avatar']) ?>" alt="">
                <?php else: ?>
                    <span class="avatar avatar--initials"><?= e(user_initials($user)) ?></span>
                <?php endif; ?>
                <div>
                    <div class="cabinet-user__name"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></div>
                    <div class="cabinet-user__email"><?= e($user['email']) ?></div>
                </div>
            </div>

            <nav class="cabinet-nav" aria-label="Разделы кабинета">
                <a class="<?= $tab === 'overview' ? 'is-active' : '' ?>" href="<?= url('cabinet.php') ?>">
                    <span>Обзор</span>
                </a>
                <a class="<?= $tab === 'history' ? 'is-active' : '' ?>" href="<?= url('cabinet.php?tab=history') ?>">
                    <span>История просмотра</span><span class="count"><?= $stats['watched'] ?></span>
                </a>
                <a class="<?= $tab === 'favorites' ? 'is-active' : '' ?>" href="<?= url('cabinet.php?tab=favorites') ?>">
                    <span>Избранное</span><span class="count"><?= $stats['favorites'] ?></span>
                </a>
                <a class="<?= $tab === 'purchases' ? 'is-active' : '' ?>" href="<?= url('cabinet.php?tab=purchases') ?>">
                    <span>Мои покупки</span><span class="count"><?= $stats['purchases'] ?></span>
                </a>
                <a class="<?= $tab === 'reviews' ? 'is-active' : '' ?>" href="<?= url('cabinet.php?tab=reviews') ?>">
                    <span>Мои оценки</span><span class="count"><?= $stats['reviews'] ?></span>
                </a>
                <a class="<?= $tab === 'subscription' ? 'is-active' : '' ?>" href="<?= url('cabinet.php?tab=subscription') ?>">
                    <span>Подписка</span>
                </a>
                <?php if (is_admin()): ?>
                    <a href="<?= url('admin/index.php') ?>"><span>Админ-панель</span><span class="count">→</span></a>
                <?php endif; ?>
                <a class="u-text-danger-soft" href="<?= url('logout.php') ?>"><span>Выйти</span></a>
            </nav>
        </aside>

        <!-- ================= Контент ================= -->
        <div class="cabinet-main">
            <?php if ($tab === 'overview'): ?>
                <div class="cabinet-head">
                    <div>
                        <h1>Здравствуйте, <?= e($user['first_name']) ?>!</h1>
                        <p>Ваш личный Illuminos: подписка, история и избранное в одном месте.</p>
                    </div>
                    <span class="role-chip role-chip--<?= e($user['role']) ?>"><?= e(role_name($user['role'])) ?></span>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-card__label">Просмотрено тайтлов</div>
                        <div class="stat-card__value"><?= $stats['watched'] ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card__label">В избранном</div>
                        <div class="stat-card__value"><?= $stats['favorites'] ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card__label">Куплено фильмов</div>
                        <div class="stat-card__value"><?= $stats['purchases'] ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card__label">Оценок поставлено</div>
                        <div class="stat-card__value"><?= $stats['reviews'] ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card__label">Подписка</div>
                        <div class="stat-card__value u-fs-19">
                            <?= $subscription ? e($subscription['tariff_name']) : 'не активна' ?>
                            <?php if ($subscription): ?>
                                <small>до <?= e(date('d.m.Y', strtotime($subscription['ends_at']))) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Подписка -->
                <?php if ($subscription): ?>
                    <div class="sub-card">
                        <h2 class="sub-card__title">Подписка «<?= e($subscription['tariff_name']) ?>» активна</h2>
                        <p class="sub-card__meta">
                            Действует до <strong><?= e(date('d.m.Y', strtotime($subscription['ends_at']))) ?></strong> ·
                            качество <?= e($subscription['quality']) ?> ·
                            осталось <?= e(plural((int)ceil((strtotime($subscription['ends_at']) - time()) / 86400), 'день', 'дня', 'дней')) ?>
                        </p>
                        <div class="sub-card__actions">
                            <a class="btn btn--primary" href="<?= url('catalog.php') ?>">Смотреть фильмы</a>
                            <a class="btn btn--outline" href="<?= url('pricing.php') ?>">Продлить подписку</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="sub-card sub-card--empty">
                        <h2 class="sub-card__title">У вас нет активной подписки</h2>
                        <p class="sub-card__meta">Оформите подписку, чтобы смотреть фильмы и сериалы без ограничений.</p>
                        <div class="sub-card__actions">
                            <a class="btn btn--primary" href="<?= url('pricing.php') ?>">Выбрать тариф</a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Профиль -->
                <div class="checkout-card">
                    <h2>Данные профиля</h2>
                    <form method="post" action="<?= url('cabinet.php') ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="profile_save" value="1">

                        <div class="form-grid">
                            <div class="field">
                                <label class="field__label" for="last_name">Фамилия</label>
                                <input class="field__input" id="last_name" name="last_name" value="<?= e($user['last_name']) ?>" required>
                            </div>
                            <div class="field">
                                <label class="field__label" for="first_name">Имя</label>
                                <input class="field__input" id="first_name" name="first_name" value="<?= e($user['first_name']) ?>" required>
                            </div>
                            <div class="field">
                                <label class="field__label" for="middle_name">Отчество</label>
                                <input class="field__input" id="middle_name" name="middle_name" value="<?= e($user['middle_name'] ?? '') ?>">
                            </div>
                            <div class="field">
                                <label class="field__label" for="email">E-mail</label>
                                <input class="field__input" type="email" id="email" name="email" value="<?= e($user['email']) ?>" required>
                            </div>
                            <div class="field">
                                <label class="field__label" for="login">Логин</label>
                                <input class="field__input" id="login" value="<?= e($user['login']) ?>" disabled>
                                <div class="field__hint">Логин изменить нельзя</div>
                            </div>
                            <div class="field">
                                <label class="field__label" for="avatar">Аватар (JPG, PNG, WEBP до 5 МБ)</label>
                                <input class="field__input" type="file" id="avatar" name="avatar" accept="image/*">
                            </div>
                        </div>

                        <div class="form-actions">
                            <button class="btn btn--primary" type="submit">Сохранить</button>
                        </div>
                    </form>
                </div>

            <?php elseif ($tab === 'history'): ?>
                <div class="cabinet-head">
                    <div>
                        <h1>История просмотра</h1>
                        <p>Последние <?= count($history) ?> просмотров. Продолжите с того же места.</p>
                    </div>
                </div>

                <?php if (!$history): ?>
                    <div class="empty-state">
                        <h3>История пуста</h3>
                        <p>Начните смотреть — и здесь появятся ваши фильмы.</p>
                        <a class="btn btn--primary" href="<?= url('catalog.php') ?>">Перейти в каталог</a>
                    </div>
                <?php else: ?>
                    <div class="history-list">
                        <?php foreach ($history as $item): ?>
                            <div class="history-item">
                                <a href="<?= url('title.php?slug=' . urlencode($item['slug'])) ?>">
                                    <img class="history-item__poster" src="<?= poster_src($item) ?>" alt="">
                                </a>
                                <div>
                                    <div class="history-item__title">
                                        <a href="<?= url('title.php?slug=' . urlencode($item['slug'])) ?>"><?= e($item['title']) ?></a>
                                    </div>
                                    <div class="history-item__meta">
                                        <?= $item['type'] === 'series' ? 'Сериал' : 'Фильм' ?> ·
                                        <?= e((string)$item['year']) ?> ·
                                        <?= e(implode(', ', $item['genres'])) ?> ·
                                        <?= e(human_date($item['watched_at'])) ?>
                                    </div>
                                    <div class="progress-bar"><span style="width:<?= (int)$item['progress'] ?>%"></span></div>
                                </div>
                                <a class="btn btn--outline btn--sm" href="<?= url('title.php?slug=' . urlencode($item['slug'])) ?>">
                                    Продолжить
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($tab === 'favorites'): ?>
                <div class="cabinet-head">
                    <div>
                        <h1>Избранное</h1>
                        <p><?= plural(count($favorites), 'тайтл', 'тайтла', 'тайтлов') ?> сохранено</p>
                    </div>
                </div>

                <?php if (!$favorites): ?>
                    <div class="empty-state">
                        <h3>Пока пусто</h3>
                        <p>Нажимайте ♥ на карточках фильмов, чтобы сохранить их здесь.</p>
                        <a class="btn btn--primary" href="<?= url('catalog.php') ?>">Перейти в каталог</a>
                    </div>
                <?php else: ?>
                    <div class="cards-grid">
                        <?php foreach ($favorites as $title): ?>
                            <?php $is_favorite = true; ?>
                            <?php include __DIR__ . '/includes/card.php'; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($tab === 'purchases'): ?>
                <div class="cabinet-head">
                    <div>
                        <h1>Мои покупки</h1>
                        <p>Купленные фильмы доступны без подписки навсегда</p>
                    </div>
                    <a class="btn btn--outline btn--sm" href="<?= url('catalog.php') ?>">Купить ещё</a>
                </div>

                <?php
                $stmt = db()->prepare(
                    'SELECT t.*, p.purchased_at
                       FROM purchases p
                       JOIN titles t ON t.id = p.title_id
                      WHERE p.user_id = ?
                      ORDER BY p.purchased_at DESC'
                );
                $stmt->execute([(int)$user['id']]);
                $purchasedTitles = $stmt->fetchAll();
                ?>

                <?php if (!$purchasedTitles): ?>
                    <div class="empty-state">
                        <h3>Покупок пока нет</h3>
                        <p>Фильмы с пометкой «купить навсегда» можно приобрести без подписки.</p>
                        <a class="btn btn--primary" href="<?= url('catalog.php') ?>">Перейти в каталог</a>
                    </div>
                <?php else: ?>
                    <div class="cards-grid">
                        <?php foreach ($purchasedTitles as $purchasedTitle): ?>
                            <?php
                            $title = $purchasedTitle;
                            $is_favorite = in_array((int)$title['id'], $favoriteIds, true);
                            include __DIR__ . '/includes/card.php';
                            ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($tab === 'reviews'): ?>
                <div class="cabinet-head">
                    <div>
                        <h1>Мои оценки</h1>
                        <p>Все выставленные оценки и отзывы</p>
                    </div>
                </div>

                <?php if (!$myReviews): ?>
                    <div class="empty-state">
                        <h3>Вы ещё не оценивали фильмы</h3>
                        <p>Откройте любой тайтл и поставьте оценку от 1 до 10.</p>
                        <a class="btn btn--primary" href="<?= url('catalog.php') ?>">Перейти в каталог</a>
                    </div>
                <?php else: ?>
                    <div class="history-list">
                        <?php foreach ($myReviews as $review): ?>
                            <div class="history-item">
                                <a href="<?= url('title.php?slug=' . urlencode($review['slug'])) ?>">
                                    <img class="history-item__poster" src="<?= poster_src($review) ?>" alt="">
                                </a>
                                <div>
                                    <div class="history-item__title">
                                        <a href="<?= url('title.php?slug=' . urlencode($review['slug'])) ?>"><?= e($review['title']) ?></a>
                                    </div>
                                    <div class="history-item__meta">
                                        <?= e((string)$review['year']) ?> · <?= e(date('d.m.Y', strtotime($review['created_at']))) ?>
                                    </div>
                                    <?php if ($review['text']): ?>
                                        <p class="u-m-8-0-0 u-text-soft u-fs-14"><?= e($review['text']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <span class="chip chip--rating"><?= (int)$review['score'] ?> / 10</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($tab === 'subscription'): ?>
                <div class="cabinet-head">
                    <div>
                        <h1>Подписка</h1>
                        <p>Тариф, срок действия и способы оплаты</p>
                    </div>
                    <a class="btn btn--primary" href="<?= url('pricing.php') ?>">Тарифы</a>
                </div>

                <?php if ($subscription): ?>
                    <div class="sub-card">
                        <h2 class="sub-card__title">Тариф «<?= e($subscription['tariff_name']) ?>»</h2>
                        <p class="sub-card__meta">
                            Статус: активна · действует с <?= e(date('d.m.Y', strtotime($subscription['starts_at']))) ?>
                            по <?= e(date('d.m.Y', strtotime($subscription['ends_at']))) ?> ·
                            качество <?= e($subscription['quality']) ?>
                        </p>
                        <div class="sub-card__actions">
                            <a class="btn btn--primary" href="<?= url('pricing.php') ?>">Продлить</a>
                            <a class="btn btn--outline" href="<?= url('catalog.php') ?>">Смотреть</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="sub-card sub-card--empty">
                        <h2 class="sub-card__title">Подписка не оформлена</h2>
                        <p class="sub-card__meta">Выберите подходящий тариф — оплата картой, СБП или электронным кошельком.</p>
                        <div class="sub-card__actions">
                            <a class="btn btn--primary" href="<?= url('pricing.php') ?>">Выбрать тариф</a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="checkout-card u-mt-24">
                    <h2>История платежей</h2>
                    <?php
                    $stmtPay = db()->prepare(
                        'SELECT p.*, t.name AS tariff_name,
                                (SELECT COUNT(*) FROM order_items oi WHERE oi.payment_id = p.id) AS items_count
                           FROM payments p
                           LEFT JOIN tariffs t ON t.id = p.tariff_id
                          WHERE p.user_id = ? ORDER BY p.created_at DESC LIMIT 10'
                    );
                    $stmtPay->execute([$user['id']]);
                    $payments = $stmtPay->fetchAll();
                    ?>
                    <?php if (!$payments): ?>
                        <p class="u-text-muted">Платежей пока нет.</p>
                    <?php else: ?>
                        <div class="admin-table-wrap">
                            <table class="admin-table">
                                <thead>
                                    <tr><th>Заказ</th><th>Тип</th><th>Состав</th><th>Сумма</th><th>Дата</th><th>Статус</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?= e($payment['order_number']) ?></td>
                                        <td><?= ($payment['kind'] ?? 'subscription') === 'films' ? 'Фильмы' : 'Подписка' ?></td>
                                        <td>
                                            <?= ($payment['kind'] ?? 'subscription') === 'films'
                                                ? e(plural((int)$payment['items_count'], 'фильм', 'фильма', 'фильмов'))
                                                : e($payment['tariff_name']) ?>
                                        </td>
                                        <td><?= e(price((float)$payment['total'])) ?></td>
                                        <td><?= e(date('d.m.Y', strtotime($payment['created_at']))) ?></td>
                                        <td>
                                            <span class="status-chip status-chip--<?= e($payment['status']) ?>">
                                                <?= e(match ($payment['status']) {
                                                    'paid' => 'Оплачен',
                                                    'pending' => 'Ожидает',
                                                    'cancelled' => 'Отменён',
                                                    default => 'Возврат',
                                                }) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
