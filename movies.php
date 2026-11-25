<?php
/**
 * movies.php — страница фильмов.
 * Отличается от общего каталога только предустановленным типом «фильм».
 */
declare(strict_types=1);

$presetType = 'movie';
$active     = 'movies';

require __DIR__ . '/includes/catalog_page.php';
