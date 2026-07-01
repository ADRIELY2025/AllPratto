<?php

declare(strict_types=1);

use App\Middleware\Middleware;
use App\Controller\Login;
use App\Controller\Cardapio;
use App\Controller\Customer;
use App\Controller\Users;
use App\Controller\Supplier;
use App\Controller\Company;
use App\Controller\Product;
use App\Controller\PaymentTerms;
use App\Controller\Mesa;
use App\Controller\Pedido;
use App\Controller\Sale;
use App\Controller\ItemSale;
use App\Controller\Installment;
use App\Controller\Purchase;
use App\Controller\Stock;

$app->post('/',     App\Controller\Home::class . ':home')->add(Middleware::web());
$app->get('/home',  App\Controller\Home::class . ':home')->add(Middleware::web());

$app->get('/', function ($request, $response) {
    return $response->withHeader('Location', '/login')->withStatus(302);
});

$app->get('/login',     Login::class . ':login')->add(Middleware::web());
$app->post('/login',    Login::class . ':authenticate')->add(Middleware::web());
$app->get('/logout',    Login::class . ':logout')->add(Middleware::web());
$app->post('/cadastro', Login::class . ':preRegister')->add(Middleware::web());

$app->group('/authentication', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->post('/google',      Login::class . ':google');
    $group->post('/auth',        Login::class . ':authenticate');
    $group->post('/preregister', Login::class . ':preRegister');
});

// ══════════════════════════════════════════════
//  Cardápio Digital (público — sem middleware)
// ══════════════════════════════════════════════
$app->group('/cardapio', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/mesa/{id}', Cardapio::class . ':index');
    $group->get('/itens',     Cardapio::class . ':getItens');
    $group->post('/pedido',   Cardapio::class . ':salvarPedido');
    $group->post('/identificar', Cardapio::class . ':identificarCliente');
});

$app->get('/cardapio', Cardapio::class . ':index')->add(Middleware::web());

// ══════════════════════════════════════════════
//  Clientes
// ══════════════════════════════════════════════
$app->group('/cliente', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/lista',         Customer::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}', Customer::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',      Customer::class . ':details')->add(Middleware::web());
    $group->post('/insert',       Customer::class . ':insert')->add(Middleware::api());
    $group->get('/enderecos/{id}', Customer::class . ':enderecos')->add(Middleware::api());
    $group->post('/endereco/insert', Customer::class . ':enderecoInsert')->add(Middleware::api());
    $group->post('/update',       Customer::class . ':update')->add(Middleware::api());
    $group->post('/delete',       Customer::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata',  Customer::class . ':listingdata')->add(Middleware::api());
});

// ══════════════════════════════════════════════
//  Usuários
// ══════════════════════════════════════════════
$app->group('/users', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/lista',         Users::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}', Users::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',      Users::class . ':details')->add(Middleware::web());
    $group->post('/insert',       Users::class . ':insert')->add(Middleware::api());
    $group->post('/update',       Users::class . ':update')->add(Middleware::api());
    $group->post('/delete',       Users::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata',  Users::class . ':listingdata')->add(Middleware::api());
});

// ══════════════════════════════════════════════
//  Fornecedores
// ══════════════════════════════════════════════
$app->group('/supplier', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/lista',         Supplier::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}', Supplier::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',      Supplier::class . ':details')->add(Middleware::web());
    $group->post('/insert',       Supplier::class . ':insert')->add(Middleware::api());
    $group->post('/update',       Supplier::class . ':update')->add(Middleware::api());
    $group->post('/delete',       Supplier::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata',  Supplier::class . ':listingdata')->add(Middleware::api());
});

// ══════════════════════════════════════════════
//  Empresas
// ══════════════════════════════════════════════
$app->group('/company', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/lista',         Company::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}', Company::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',      Company::class . ':details')->add(Middleware::web());
    $group->post('/insert',       Company::class . ':insert')->add(Middleware::api());
    $group->post('/update',       Company::class . ':update')->add(Middleware::api());
    $group->post('/delete',       Company::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata',  Company::class . ':listingdata')->add(Middleware::api());
});

// ══════════════════════════════════════════════
//  Produtos
// ══════════════════════════════════════════════
$app->group('/product', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/lista',           Product::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}',   Product::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',        Product::class . ':details')->add(Middleware::web());
    $group->get('/get-imagem/{id}', Product::class . ':getImagem'); // público — cardápio
    $group->post('/insert',         Product::class . ':insert')->add(Middleware::api());
    $group->post('/update',         Product::class . ':update')->add(Middleware::api());
    $group->post('/delete',         Product::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata',    Product::class . ':listingdata')->add(Middleware::api());
    $group->post('/upload-imagem',  Product::class . ':uploadImagem')->add(Middleware::api());
});

// ══════════════════════════════════════════════
//  Mesas
// ══════════════════════════════════════════════
$app->group('/mesa', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/lista',          Mesa::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}',  Mesa::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',       Mesa::class . ':details')->add(Middleware::web());
    $group->post('/insert',        Mesa::class . ':insert')->add(Middleware::api());
    $group->post('/update',        Mesa::class . ':update')->add(Middleware::api());
    $group->post('/update-status', Mesa::class . ':updateStatus')->add(Middleware::api());
    $group->post('/delete',        Mesa::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata',   Mesa::class . ':listingdata')->add(Middleware::api());
});

// ══════════════════════════════════════════════
//  Pedidos
// ══════════════════════════════════════════════
$app->group('/pedido', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/cozinha/listar', Pedido::class . ':listarCozinha')->add(Middleware::api());
    $group->get('/cozinha',        Pedido::class . ':cozinha')->add(Middleware::web());
    $group->get('/lista',          Pedido::class . ':list')->add(Middleware::web());
    $group->get('/virtual',        Pedido::class . ':virtual')->add(Middleware::web());
    $group->get('/detalhes/{id}',  Pedido::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',       Pedido::class . ':details')->add(Middleware::web());
    $group->get('/itens/{id}',     Pedido::class . ':getItens')->add(Middleware::api());
    $group->post('/insert',        Pedido::class . ':insert')->add(Middleware::api());
    $group->post('/virtual/insert', Pedido::class . ':insertVirtual')->add(Middleware::api());
    $group->post('/update-status', Pedido::class . ':updateStatus')->add(Middleware::api());
    $group->post('/item/cancelar',  Pedido::class . ':cancelarItem')->add(Middleware::api());
    $group->post('/item/adicionar', Pedido::class . ':adicionarItem')->add(Middleware::api());
    $group->post('/delete',        Pedido::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata',   Pedido::class . ':listingdata')->add(Middleware::api());
});

// ══════════════════════════════════════════════
//  Gráficos da Home (API JSON)
// ══════════════════════════════════════════════
$app->group('/home/grafico', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/mesas/bar',   App\Controller\Home::class . ':graficaMesasBar')->add(Middleware::api());
    $group->get('/mesas/pie',   App\Controller\Home::class . ':graficaMesasPie')->add(Middleware::api());
    $group->get('/cliente/bar', App\Controller\Home::class . ':graficaClienteBar')->add(Middleware::api());
    $group->get('/cliente/pie', App\Controller\Home::class . ':graficaClientePie')->add(Middleware::api());
    $group->get('/produto/bar', App\Controller\Home::class . ':graficaProdutoBar')->add(Middleware::api());
    $group->get('/produto/pie', App\Controller\Home::class . ':graficaProdutoPie')->add(Middleware::api());
});

$app->get('/home/resultado-vendas',    App\Controller\Home::class . ':resultadoVendas')->add(Middleware::api());
$app->get('/home/resultado-marketing', App\Controller\Home::class . ':resultadoMarketing')->add(Middleware::api());

// ══════════════════════════════════════════════
//  Compras
// ══════════════════════════════════════════════
$app->group('/compra', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/lista',        Purchase::class . ':list')->add(Middleware::web());
    $group->post('/delete',      Purchase::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata', Purchase::class . ':listingdata')->add(Middleware::api());
    $group->get('/pdf/{id}',     Purchase::class . ':pdf')->add(Middleware::api());
});

// Alias /purchase/* (chamado pelo JS e URL direta)
$app->group('/purchase', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/lista',        Purchase::class . ':list')->add(Middleware::web());
    $group->post('/delete',      Purchase::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata', Purchase::class . ':listingdata')->add(Middleware::api());
    $group->get('/pdf/{id}',     Purchase::class . ':pdf')->add(Middleware::api());
});

// ══════════════════════════════════════════════
//  Formas de Pagamento
// ══════════════════════════════════════════════
$app->group('/payment', function (\Slim\Routing\RouteCollectorProxy $group) {
    // ── Páginas HTML ──────────────────────────
    $group->get('/lista',         PaymentTerms::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}', PaymentTerms::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',      PaymentTerms::class . ':details')->add(Middleware::web());
    // ── CRUD PaymentTerms ─────────────────────
    $group->post('/insert',       PaymentTerms::class . ':insert')->add(Middleware::api());
    $group->post('/update',       PaymentTerms::class . ':update')->add(Middleware::api());
    $group->post('/delete',       PaymentTerms::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata',  PaymentTerms::class . ':listingdata')->add(Middleware::api());
    // ── CRUD Parcelas (Installment) ───────────
    $group->post('/installment/insert', Installment::class . ':insert')->add(Middleware::api());
    $group->post('/installment/list',   Installment::class . ':list')->add(Middleware::api());
    $group->post('/installment/delete', Installment::class . ':delete')->add(Middleware::api());
});

// ══════════════════════════════════════════════
//  Vendas
// ══════════════════════════════════════════════
$app->group('/sale', function (\Slim\Routing\RouteCollectorProxy $group) {
    // ── Páginas HTML ──────────────────────────
    $group->get('/lista',         Sale::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}', Sale::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',      Sale::class . ':details')->add(Middleware::web());
    // ── CRUD Venda ────────────────────────────
    $group->post('/insert',       Sale::class . ':insert')->add(Middleware::api());
    $group->post('/update',       Sale::class . ':update')->add(Middleware::api());
    $group->post('/delete',       Sale::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata',  Sale::class . ':listingdata')->add(Middleware::api());
    // ── Buscas auxiliares (Select2 / ajax) ───
    $group->post('/find-customer',        Sale::class . ':findCustomer')->add(Middleware::api());
    $group->post('/find-product',         Sale::class . ':findProduct')->add(Middleware::api());
    $group->get('/find-product/{id}',     Sale::class . ':findProductById')->add(Middleware::api());
    $group->get('/payment-terms',         Sale::class . ':findPaymentTerms')->add(Middleware::api());
    $group->get('/installments/{id}',     Sale::class . ':findInstallments')->add(Middleware::api());
    // ── CRUD Item da Venda ────────────────────
    $group->post('/item/insert',  ItemSale::class . ':insert')->add(Middleware::api());
    $group->post('/item/delete',  ItemSale::class . ':delete')->add(Middleware::api());
    $group->get('/{id}/itens',    ItemSale::class . ':findBySale')->add(Middleware::api());
});