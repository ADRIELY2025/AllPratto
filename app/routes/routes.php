<?php

declare(strict_types=1);

use App\Middleware\Middleware;

$app->get('/login', App\Controller\Login::class . ':login')->add(Middleware::web());
$app->post('/login', App\Controller\Login::class . ':authenticate')->add(Middleware::web());
$app->get('/logout', App\Controller\Login::class . ':logout')->add(Middleware::web());
$app->post('/cadastro', App\Controller\Login::class . ':preRegister')->add(Middleware::web());


$app->group('/authentication', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->post('/google',      App\Controller\Login::class . ':google');
    $group->post('/auth',        App\Controller\Login::class . ':authenticate');
    $group->post('/preregister', App\Controller\Login::class . ':preRegister');
});

$app->get('/',     App\Controller\Home::class . ':home')->add(Middleware::web());
$app->get('/home', App\Controller\Home::class . ':home')->add(Middleware::web());
$app->get('/cardapio', App\Controller\Cardapio::class . ':home');

$app->group('/cliente', function (\Slim\Routing\RouteCollectorProxy $group) {
    # Páginas HTML protegidas
    $group->get('/lista',         App\Controller\Customer::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}', App\Controller\Customer::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',      App\Controller\Customer::class . ':details')->add(Middleware::web());
    # Endpoints JSON protegidos
    $group->post('/insert',      App\Controller\Customer::class . ':insert')->add(Middleware::api());
    $group->post('/update',      App\Controller\Customer::class . ':update')->add(Middleware::api());
    $group->post('/delete',      App\Controller\Customer::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata', App\Controller\Customer::class . ':listingdata')->add(Middleware::api());
});

$app->group('/users', function (\Slim\Routing\RouteCollectorProxy $group) {
    # Páginas HTML protegidas
    $group->get('/lista',         App\Controller\Users::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}', App\Controller\Users::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',      App\Controller\Users::class . ':details')->add(Middleware::web());
    # Endpoints JSON protegidos
    $group->post('/insert',      App\Controller\Users::class . ':insert')->add(Middleware::api());
    $group->post('/update',      App\Controller\Users::class . ':update')->add(Middleware::api());
    $group->post('/delete',      App\Controller\Users::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata', App\Controller\Users::class . ':listingdata')->add(Middleware::api());
});

$app->group('/supplier', function (\Slim\Routing\RouteCollectorProxy $group) {
    # Páginas HTML protegidas
    $group->get('/lista',         App\Controller\Supplier::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}', App\Controller\Supplier::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',      App\Controller\Supplier::class . ':details')->add(Middleware::web());
    # Endpoints JSON protegidos
    $group->post('/insert',      App\Controller\Supplier::class . ':insert')->add(Middleware::api());
    $group->post('/update',      App\Controller\Supplier::class . ':update')->add(Middleware::api());
    $group->post('/delete',      App\Controller\Supplier::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata', App\Controller\Supplier::class . ':listingdata')->add(Middleware::api());
});

$app->group('/company', function (\Slim\Routing\RouteCollectorProxy $group) {
    # Páginas HTML protegidas
    $group->get('/lista',         App\Controller\Company::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}', App\Controller\Company::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',      App\Controller\Company::class . ':details')->add(Middleware::web());
    # Endpoints JSON protegidos
    $group->post('/insert',      App\Controller\Company::class . ':insert')->add(Middleware::api());
    $group->post('/update',      App\Controller\Company::class . ':update')->add(Middleware::api());
    $group->post('/delete',      App\Controller\Company::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata', App\Controller\Company::class . ':listingdata')->add(Middleware::api());
});

$app->group('/product', function (\Slim\Routing\RouteCollectorProxy $group) {
    # Páginas HTML protegidas
    $group->get('/lista',         App\Controller\Product::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}', App\Controller\Product::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',      App\Controller\Product::class . ':details')->add(Middleware::web());
    # Endpoints JSON protegidos
    $group->post('/insert',      App\Controller\Product::class . ':insert')->add(Middleware::api());
    $group->post('/update',      App\Controller\Product::class . ':update')->add(Middleware::api());
    $group->post('/delete',      App\Controller\Product::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata', App\Controller\Product::class . ':listingdata')->add(Middleware::api());
});