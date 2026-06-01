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
            'descricao'   => 'Caneta Azul BIC',
            'codigoBarras' => '7891234560001',
            'sku'          => 'CANETA-AZUL-001',
            'valorCusto'   => '1,50',
            'valorVenda'   => '3,00',
            'estoque'      => '100',
            'ativo'        => 'true',
        ]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new app\controller\Product())->insert($request, $response);

    $result->getBody()->rewind();

    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(201);

    expect($json['status'])->toBeTrue();

    expect($json['msg'])->toContain('Salvo com sucesso');
});