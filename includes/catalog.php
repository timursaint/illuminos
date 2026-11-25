<?php
/**
 * includes/catalog.php
 * Работа с каталогом: выборки тайтлов, жанры, поиск с фильтрами,
 * персональные рекомендации и избранное пользователя.
 */
declare(strict_types=1);

/** Все жанры с количеством тайтлов (для меню категорий) */
function genres_all(): array
{
    return db()->query(
        'SELECT g.*, COUNT(tg.title_id) AS titles_count
           FROM genres g
           LEFT JOIN title_genres tg ON tg.genre_id = g.id
          GROUP BY g.id
          ORDER BY g.sort_order, g.name'
    )->fetchAll();
}

/** Жанр по slug */
function genre_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM genres WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

/**
 * Карта «id тайтла → список жанров». Позволяет вывести жанры
 * для целой подборки одним запросом, без N+1.
 * Каждый жанр — массив ['name' => 'Драма', 'slug' => 'drama'].
 */
function title_genres_map(array $ids): array
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) {
        return [];
    }

    $in = implode(',', $ids);
    $rows = db()->query(
        "SELECT tg.title_id, g.name, g.slug
           FROM title_genres tg
           JOIN genres g ON g.id = tg.genre_id
          WHERE tg.title_id IN ({$in})
          ORDER BY g.sort_order"
    )->fetchAll();

    $map = [];
    foreach ($rows as $row) {
        $map[(int)$row['title_id']][] = ['name' => $row['name'], 'slug' => $row['slug']];
    }
    return $map;
}

/** Дописывает в массив тайтлов названия жанров и их slug-и */
function decorate_titles(array $titles): array
{
    $map = title_genres_map(array_column($titles, 'id'));
    foreach ($titles as &$title) {
        $items = $map[(int)$title['id']] ?? [];
        $title['genres']      = array_column($items, 'name');
        $title['genres_full'] = $items;
    }
    return $titles;
}

/**
 * Поиск и фильтрация каталога.
 * $options: q, type, genre, year, sort, page, per_page, is_new, is_popular
 * Возвращает ['items' => [...], 'total' => int, 'pages' => int, 'page' => int].
 */
function search_titles(array $options = []): array
{
    $where  = [];
    $params = [];

    if (!empty($options['q'])) {
        // Нативные подготовленные запросы не допускают повтор одного имени параметра
        $where[] = '(t.title LIKE :q1 OR t.original_title LIKE :q2 OR t.cast_list LIKE :q3 OR t.director LIKE :q4)';
        $like = '%' . $options['q'] . '%';
        $params['q1'] = $like;
        $params['q2'] = $like;
        $params['q3'] = $like;
        $params['q4'] = $like;
    }
    if (!empty($options['type']) && in_array($options['type'], ['movie', 'series'], true)) {
        $where[] = 't.type = :type';
        $params['type'] = $options['type'];
    }
    if (!empty($options['genre'])) {
        $where[] = 'g.slug = :genre';
        $params['genre'] = $options['genre'];
    }
    if (!empty($options['year'])) {
        $where[] = 't.year = :year';
        $params['year'] = (int)$options['year'];
    }
    if (!empty($options['is_new'])) {
        $where[] = 't.is_new = 1';
    }
    if (!empty($options['is_popular'])) {
        $where[] = 't.is_popular = 1';
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sortMap = [
        'popular' => 't.views DESC, t.rating DESC',
        'rating'  => 't.rating DESC',
        'new'     => 't.year DESC, t.created_at DESC',
        'title'   => 't.title ASC',
    ];
    $order = $sortMap[$options['sort'] ?? 'popular'] ?? $sortMap['popular'];

    $perPage = max(4, min(48, (int)($options['per_page'] ?? 24)));
    $page    = max(1, (int)($options['page'] ?? 1));
    $offset  = ($page - 1) * $perPage;

    $baseFrom = 'FROM titles t
                 LEFT JOIN title_genres tg ON tg.title_id = t.id
                 LEFT JOIN genres g ON g.id = tg.genre_id
                 ' . $whereSql;

    $countStmt = db()->prepare("SELECT COUNT(DISTINCT t.id) {$baseFrom}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $sql = "SELECT DISTINCT t.* {$baseFrom} ORDER BY {$order} LIMIT {$perPage} OFFSET {$offset}";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return [
        'items' => decorate_titles($stmt->fetchAll()),
        'total' => $total,
        'pages' => max(1, (int)ceil($total / $perPage)),
        'page'  => $page,
        'per_page' => $perPage,
    ];
}

/** Подборка для секции главной: новое / популярное / featured */
function home_section(string $kind, int $limit = 10): array
{
    $limit = max(1, $limit);

    $sql = match ($kind) {
        'new'     => "SELECT * FROM titles WHERE is_new = 1 ORDER BY year DESC, created_at DESC LIMIT {$limit}",
        'popular' => "SELECT * FROM titles WHERE is_popular = 1 ORDER BY views DESC LIMIT {$limit}",
        default   => "SELECT * FROM titles WHERE is_popular = 1 OR is_new = 1 ORDER BY rating DESC LIMIT {$limit}",
    };

    return decorate_titles(db()->query($sql)->fetchAll());
}

/** ID избранных тайтлов пользователя (для активного сердечка) */
function favorites_ids(?int $userId = null): array
{
    $userId ??= (int)(current_user()['id'] ?? 0);
    if (!$userId) {
        return [];
    }

    $stmt = db()->prepare('SELECT title_id FROM favorites WHERE user_id = ?');
    $stmt->execute([$userId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Персональные рекомендации: тайтлы жанров, которые пользователь
 * добавлял в избранное или смотрел. Для гостя — лучшее по рейтингу.
 */
function recommendations_for(?int $userId, int $limit = 8): array
{
    $limit = max(1, $limit);

    // Гость: персональных данных нет — показываем популярное (по количеству просмотров)
    if (!$userId) {
        return decorate_titles(
            db()->query("SELECT * FROM titles ORDER BY views DESC, rating DESC LIMIT {$limit}")->fetchAll()
        );
    }

    // Жанры, которые пользователь уже выбрал (избранное + история)
    $stmt = db()->prepare(
        'SELECT DISTINCT tg.genre_id
           FROM title_genres tg
          WHERE tg.title_id IN (
                SELECT title_id FROM favorites WHERE user_id = :fav
                UNION
                SELECT title_id FROM watch_history WHERE user_id = :hist
          )'
    );
    $stmt->execute(['fav' => $userId, 'hist' => $userId]);
    $genreIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    // Пользователь ещё ничего не смотрел и не добавлял в избранное:
    // рекомендаций по вкусам нет — отдаём популярное
    if (!$genreIds) {
        return decorate_titles(
            db()->query("SELECT * FROM titles ORDER BY views DESC, rating DESC LIMIT {$limit}")->fetchAll()
        );
    }

    $in = implode(',', $genreIds);
    $sql = "SELECT t.* FROM titles t
              JOIN title_genres tg ON tg.title_id = t.id
             WHERE tg.genre_id IN ({$in})
               AND t.id NOT IN (SELECT title_id FROM watch_history WHERE user_id = :watched)
               AND t.id NOT IN (SELECT title_id FROM favorites WHERE user_id = :faved)
             GROUP BY t.id
             ORDER BY COUNT(tg.genre_id) DESC, t.rating DESC
             LIMIT {$limit}";

    $stmt = db()->prepare($sql);
    $stmt->execute(['watched' => $userId, 'faved' => $userId]);
    $items = $stmt->fetchAll();

    // Если всё уже просмотрено — добираем популярным, чтобы блок не был пустым
    if (count($items) < $limit) {
        $exclude = array_column($items, 'id') ?: [0];
        $notIn = implode(',', array_map('intval', $exclude));
        $extra = db()->query(
            "SELECT * FROM titles WHERE id NOT IN ({$notIn}) ORDER BY views DESC, rating DESC LIMIT " . ($limit - count($items))
        )->fetchAll();
        $items = array_merge($items, $extra);
    }

    return decorate_titles($items);
}

/** Похожие тайтлы: тот же тип и общие жанры */
function similar_titles(array $title, int $limit = 8): array
{
    $genreIds = array_column(
        db()->query('SELECT genre_id FROM title_genres WHERE title_id = ' . (int)$title['id'])->fetchAll(),
        'genre_id'
    );

    if (!$genreIds) {
        return [];
    }

    $in = implode(',', array_map('intval', $genreIds));
    $stmt = db()->prepare(
        "SELECT t.*, COUNT(tg.genre_id) AS common
           FROM titles t
           JOIN title_genres tg ON tg.title_id = t.id
          WHERE tg.genre_id IN ({$in}) AND t.id <> :id AND t.type = :type
          GROUP BY t.id
          ORDER BY common DESC, t.rating DESC
          LIMIT {$limit}"
    );
    $stmt->execute(['id' => (int)$title['id'], 'type' => $title['type']]);

    return decorate_titles($stmt->fetchAll());
}

/** Одна запись каталога по slug со всеми жанрами */
function find_title(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM titles WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $title = $stmt->fetch();

    if (!$title) {
        return null;
    }

    $map = title_genres_map([(int)$title['id']]);
    $items = $map[(int)$title['id']] ?? [];
    $title['genres']      = array_column($items, 'name');
    $title['genres_full'] = $items;
    return $title;
}

/** Сезоны с сериями для страницы сериала */
function title_seasons(int $titleId): array
{
    $stmt = db()->prepare('SELECT * FROM seasons WHERE title_id = ? ORDER BY number');
    $stmt->execute([$titleId]);
    $seasons = $stmt->fetchAll();

    if (!$seasons) {
        return [];
    }

    $ids = implode(',', array_map('intval', array_column($seasons, 'id')));
    $episodes = db()->query(
        "SELECT * FROM episodes WHERE season_id IN ({$ids}) ORDER BY season_id, number"
    )->fetchAll();

    $grouped = [];
    foreach ($episodes as $episode) {
        $grouped[(int)$episode['season_id']][] = $episode;
    }

    foreach ($seasons as &$season) {
        $season['episodes'] = $grouped[(int)$season['id']] ?? [];
    }

    return $seasons;
}

/** Средняя оценка пользователей и количество отзывов */
function title_user_rating(int $titleId): array
{
    $stmt = db()->prepare('SELECT ROUND(AVG(score), 1) AS avg_score, COUNT(*) AS total FROM reviews WHERE title_id = ?');
    $stmt->execute([$titleId]);
    $row = $stmt->fetch();

    return [
        'avg'   => $row['avg_score'] !== null ? (float)$row['avg_score'] : null,
        'total' => (int)$row['total'],
    ];
}

/** Отзывы тайтла с именами пользователей */
function title_reviews(int $titleId, int $limit = 10): array
{
    $stmt = db()->prepare(
        'SELECT r.*, u.first_name, u.last_name, u.avatar
           FROM reviews r
           JOIN users u ON u.id = r.user_id
          WHERE r.title_id = ?
          ORDER BY r.created_at DESC
          LIMIT ' . (int)$limit
    );
    $stmt->execute([$titleId]);
    return $stmt->fetchAll();
}
