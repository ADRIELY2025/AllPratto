<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';


// Carrega as variáveis do .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$app = AppFactory::create();

$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();

$debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

$app->addErrorMiddleware($debug, $debug, $debug);

require __DIR__ . '/helpers/settings.php';
require __DIR__ . '/routes/routes.php';

return $app;
