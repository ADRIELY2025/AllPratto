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
            'nome'                 => ' ',
            'codigoBarra'          => '7891234560001',
            'grupo'                => 'Papelaria',
            'unidade'              => 'UN',
            'precoCompra'          => '1,50',
            'totalImposto'         => '0,15',
            'margemLucro'          => '50,00',
            'custoOperacional'     => '0,20',
            'valorVendaSugerido'   => '2,78',
            'precoVenda'           => '3,00',
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
