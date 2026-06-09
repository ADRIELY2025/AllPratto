<?php

declare(strict_types=1);

use App\Middleware\Middleware;
use App\Middleware\Home;
use App\Controller\Login;
use App\Controller\Cardapio;
use App\Controller\Customer;
use App\Controller\Users;
use App\Controller\Supplier;
use App\Controller\Company;
use App\Controller\Product;

$app->post('/',     App\controller\Home::class . ':home')->add(Middleware::web());
$app->get('/home', App\controller\Home::class . ':home')->add(Middleware::web());

$app->get('/', function ($request, $response) {
    return $response->withHeader('Location', '/login')->withStatus(302);
});

$app->get('/login',    Login::class . ':login')->add(Middleware::web());
$app->post('/login',    Login::class . ':authenticate')->add(Middleware::web());
$app->get('/logout',   Login::class . ':logout')->add(Middleware::web());
$app->post('/cadastro', Login::class . ':preRegister')->add(Middleware::web());

$app->group('/authentication', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->post('/google',      Login::class . ':google');
    $group->post('/auth',        Login::class . ':authenticate');
    $group->post('/preregister', Login::class . ':preRegister');
});

// ══════════════════════════════════════════════
//  Cardápio Digital (público — sem middleware)
// ══════════════════════════════════════════════
$app->get('/cardapio',         Cardapio::class . ':index');
$app->get('/cardapio/itens',   Cardapio::class . ':getItens');
$app->post('/cardapio/pedido',  Cardapio::class . ':salvarPedido');

// ══════════════════════════════════════════════
//  Clientes
// ══════════════════════════════════════════════
$app->group('/cliente', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/lista',          Customer::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}',  Customer::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',       Customer::class . ':details')->add(Middleware::web());
    $group->post('/insert',        Customer::class . ':insert')->add(Middleware::api());
    $group->post('/update',        Customer::class . ':update')->add(Middleware::api());
    $group->post('/delete',        Customer::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata',   Customer::class . ':listingdata')->add(Middleware::api());
});

// ══════════════════════════════════════════════
//  Usuários
// ══════════════════════════════════════════════
$app->group('/users', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/lista',          Users::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}',  Users::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',       Users::class . ':details')->add(Middleware::web());
    $group->post('/insert',        Users::class . ':insert')->add(Middleware::api());
    $group->post('/update',        Users::class . ':update')->add(Middleware::api());
    $group->post('/delete',        Users::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata',   Users::class . ':listingdata')->add(Middleware::api());
});

// ══════════════════════════════════════════════
//  Fornecedores
// ══════════════════════════════════════════════
$app->group('/supplier', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/lista',          Supplier::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}',  Supplier::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',       Supplier::class . ':details')->add(Middleware::web());
    $group->post('/insert',        Supplier::class . ':insert')->add(Middleware::api());
    $group->post('/update',        Supplier::class . ':update')->add(Middleware::api());
    $group->post('/delete',        Supplier::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata',   Supplier::class . ':listingdata')->add(Middleware::api());
});

// ══════════════════════════════════════════════
//  Empresas
// ══════════════════════════════════════════════
$app->group('/company', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/lista',          Company::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}',  Company::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',       Company::class . ':details')->add(Middleware::web());
    $group->post('/insert',        Company::class . ':insert')->add(Middleware::api());
    $group->post('/update',        Company::class . ':update')->add(Middleware::api());
    $group->post('/delete',        Company::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata',   Company::class . ':listingdata')->add(Middleware::api());
});

// ══════════════════════════════════════════════
//  Produtos
// ══════════════════════════════════════════════
$app->group('/product', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/lista',          Product::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}',  Product::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',       Product::class . ':details')->add(Middleware::web());
    $group->post('/insert',        Product::class . ':insert')->add(Middleware::api());
    $group->post('/update',        Product::class . ':update')->add(Middleware::api());
    $group->post('/delete',        Product::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata',   Product::class . ':listingdata')->add(Middleware::api());
});

// ══════════════════════════════════════════════
//  Gráficos da Home (API JSON)
// ══════════════════════════════════════════════
$app->group('/home/grafico', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/mesas/bar',    App\Controller\Home::class . ':graficaMesasBar')  ->add(Middleware::api());
    $group->get('/mesas/pie',    App\Controller\Home::class . ':graficaMesasPie')  ->add(Middleware::api());
    $group->get('/cliente/bar',  App\Controller\Home::class . ':graficaClienteBar')->add(Middleware::api());
    $group->get('/cliente/pie',  App\Controller\Home::class . ':graficaClientePie')->add(Middleware::api());
    $group->get('/produto/bar',  App\Controller\Home::class . ':graficaProdutoBar')->add(Middleware::api());
    $group->get('/produto/pie',  App\Controller\Home::class . ':graficaProdutoPie')->add(Middleware::api());
});

$app->get('/home/resultado-vendas',    App\Controller\Home::class . ':resultadoVendas')  ->add(Middleware::api());
$app->get('/home/resultado-marketing', App\Controller\Home::class . ':resultadoMarketing')->add(Middleware::api());
