<?php
/**
 * catalog.php — общий каталог фильмов и сериалов
 * с поиском, фильтрами по жанру, году и сортировкой.
 */
declare(strict_types=1);

$presetType = null;
$active     = 'catalog';

require __DIR__ . '/includes/catalog_page.php';
