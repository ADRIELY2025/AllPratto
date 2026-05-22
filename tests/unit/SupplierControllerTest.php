<?php

declare(strict_types=1);

use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

// ── insert ────────────────────────────────────────────────────────────────────
test('supplier insert com dados válidos retorna 201 com status true', function () {

    $request = (new RequestFactory())
        ->createRequest('POST', '/supplier')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([
            'nomeExibicao'       => 'Fornecedor Havan ',
            'nomeLegal'          => 'Fornecedor Havan Ro',
            'numeroDocumento'    => '12.345.678/0001-99',
            'registroSecundario' => '987654321',
            'telefone'           => '(68) 6545-7777',
            'email'              => 'contato@fornecedorabc.com',
            'ativo'              => 'true',
        ]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new app\controller\Supplier())->insert($request, $response);

    $result->getBody()->rewind();

    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(201);

    expect($json['status'])->toBeTrue();

    expect($json['msg'])->toContain('Salvo com sucesso');
});