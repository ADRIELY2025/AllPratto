<?php

declare(strict_types=1);

use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

// ── insert ────────────────────────────────────────────────────────────────────
test('product insert com dados válidos retorna 201 com status true', function () {

    $request = (new RequestFactory())
        ->createRequest('POST', '/product')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([
            'nome'                 => 'Caneta Azul',          // ✅ nome válido (não branco)
            'codigo_barra'         => '7891234560001',         // ✅ chave correta
            'grupo'                => 'Papelaria',
            'unidade'              => 'UN',
            'preco_compra'         => '1,50',                  // ✅ chave correta
            'total_imposto'        => '0,15',                  // ✅ chave correta
            'margem_lucro'         => '50,00',                 // ✅ chave correta
            'custo_operacional'    => '0,20',                  // ✅ chave correta
            'valor_venda_sugerido' => '2,78',                  // ✅ chave correta
            'preco_venda'          => '3,00',                  // ✅ chave correta
            'tempoPreparo'         => null,
            'descricao'            => 'Caneta esferográfica azul',
            'ativo'                => 'true',
        ]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new App\Controller\Product())->insert($request, $response);

    $result->getBody()->rewind();

    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(201);

    expect($json['status'])->toBeTrue();

    expect($json['msg'])->toBe('Salvo com sucesso!');

    expect($json)->toHaveKey('id');

    expect($json['id'])->toBeGreaterThan(0);
});