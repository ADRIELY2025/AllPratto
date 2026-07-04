<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Converte warnings/notices do PHP em exceções, para que nunca "vazem" como
// HTML solto (<br />, <b>Warning</b>...) misturado nas respostas JSON da API.
// Sem isso, um simples warning (ex: falha ao criar uma pasta) corrompe o
// corpo da resposta e o front-end recebe "Unexpected token '<' is not valid JSON".
set_error_handler(function (int $severity, string $message, string $file, int $line) {
    if (!(error_reporting() & $severity)) {
        return false; // erro suprimido com @, respeita o comportamento nativo
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

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