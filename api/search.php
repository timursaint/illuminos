<?php
/**
 * api/search.php — живой поиск по каталогу для шапки сайта.
 * Возвращает JSON: {items:[{url,title,meta,poster}]}
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/catalog.php';

$query = trim((string)($_GET['q'] ?? ''));

if (mb_strlen($query) < 2) {
    json_out(['items' => []]);
}

$stmt = db()->prepare(
    'SELECT id, title, type, year, rating, poster, slug
       FROM titles
      WHERE title LIKE :q1 OR original_title LIKE :q2 OR cast_list LIKE :q3 OR director LIKE :q4
      ORDER BY rating DESC
      LIMIT 7'
);
$like = '%' . $query . '%';
$stmt->execute(['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like]);
$rows = $stmt->fetchAll();

$items = [];
foreach ($rows as $row) {
    $items[] = [
        'url'    => url('title.php?slug=' . urlencode($row['slug'])),
        'title'  => $row['title'],
        'poster' => poster_src($row),
        'meta'   => trim(
            ($row['type'] === 'series' ? 'Сериал' : 'Фильм') . ' · ' . $row['year'] . ' · ★ ' .
            number_format((float)$row['rating'], 1, '.', '')
        ),
    ];
}

json_out(['items' => $items]);
