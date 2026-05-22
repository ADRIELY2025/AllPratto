<?php

declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

if (!defined('DIR_VIEWS')) {
    define('DIR_VIEWS', __DIR__ . '/App/views/');
}

if (!defined('EXT_VIEWS')) {
    define('EXT_VIEWS', '.php');
}
