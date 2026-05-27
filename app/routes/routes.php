<?php

declare(strict_types=1);

use app\middleware\Middleware;

$app->get('/login', app\controller\Login::class . ':login')->add(Middleware::web());
$app->post('/login', app\controller\Login::class . ':authenticate')->add(Middleware::web());
$app->get('/logout', app\controller\Login::class . ':logout')->add(Middleware::web());
$app->post('/cadastro', app\controller\Login::class . ':preRegister')->add(Middleware::web());


$app->group('/authentication', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->post('/google',      app\controller\Login::class . ':google');
    $group->post('/auth',        app\controller\Login::class . ':authenticate');
    $group->post('/preregister', app\controller\Login::class . ':preRegister');
});

$app->get('/',     app\controller\Home::class . ':home')->add(Middleware::web());
$app->get('/home', app\controller\Home::class . ':home')->add(Middleware::web());
$app->get('/cardapio', app\controller\Cardapio::class . ':list');

$app->group('/cliente', function (\Slim\Routing\RouteCollectorProxy $group) {
    # Páginas HTML protegidas
    $group->get('/lista',         app\controller\Customer::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}', app\controller\Customer::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',      app\controller\Customer::class . ':details')->add(Middleware::web());
    # Endpoints JSON protegidos
    $group->post('/insert',      app\controller\Customer::class . ':insert')->add(Middleware::api());
    $group->post('/update',      app\controller\Customer::class . ':update')->add(Middleware::api());
    $group->post('/delete',      app\controller\Customer::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata', app\controller\Customer::class . ':listingdata')->add(Middleware::api());
});

$app->group('/users', function (\Slim\Routing\RouteCollectorProxy $group) {
    # Páginas HTML protegidas
    $group->get('/lista',         app\controller\Users::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}', app\controller\Users::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',      app\controller\Users::class . ':details')->add(Middleware::web());
    # Endpoints JSON protegidos
    $group->post('/insert',      app\controller\Users::class . ':insert')->add(Middleware::api());
    $group->post('/update',      app\controller\Users::class . ':update')->add(Middleware::api());
    $group->post('/delete',      app\controller\Users::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata', app\controller\Users::class . ':listingdata')->add(Middleware::api());
});

$app->group('/supplier', function (\Slim\Routing\RouteCollectorProxy $group) {
    # Páginas HTML protegidas
    $group->get('/lista',         app\controller\Supplier::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}', app\controller\Supplier::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',      app\controller\Supplier::class . ':details')->add(Middleware::web());
    # Endpoints JSON protegidos
    $group->post('/insert',      app\controller\Supplier::class . ':insert')->add(Middleware::api());
    $group->post('/update',      app\controller\Supplier::class . ':update')->add(Middleware::api());
    $group->post('/delete',      app\controller\Supplier::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata', app\controller\Supplier::class . ':listingdata')->add(Middleware::api());
});

$app->group('/company', function (\Slim\Routing\RouteCollectorProxy $group) {
    # Páginas HTML protegidas
    $group->get('/lista',         app\controller\Company::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}', app\controller\Company::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',      app\controller\Company::class . ':details')->add(Middleware::web());
    # Endpoints JSON protegidos
    $group->post('/insert',      app\controller\Company::class . ':insert')->add(Middleware::api());
    $group->post('/update',      app\controller\Company::class . ':update')->add(Middleware::api());
    $group->post('/delete',      app\controller\Company::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata', app\controller\Company::class . ':listingdata')->add(Middleware::api());
});

$app->group('/product', function (\Slim\Routing\RouteCollectorProxy $group) {
    # Páginas HTML protegidas
    $group->get('/lista',         app\controller\Product::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}', app\controller\Product::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',      app\controller\Product::class . ':details')->add(Middleware::web());
    # Endpoints JSON protegidos
    $group->post('/insert',      app\controller\Product::class . ':insert')->add(Middleware::api());
    $group->post('/update',      app\controller\Product::class . ':update')->add(Middleware::api());
    $group->post('/delete',      app\controller\Product::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata', app\controller\Product::class . ':listingdata')->add(Middleware::api());
});
