<?php

declare(strict_types=1);

use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

// ── insert ────────────────────────────────────────────────────────────────────
test('customer insert com dados válidos retorna 201 com status true', function () {

    $request = (new RequestFactory())
        ->createRequest('POST', '/customer')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([
            'nomeExibicao'       => 'Felipe',
            'nomeLegal'          => 'Jesus',
            'numeroDocumento'    => '999.999.999-99',
            'registroSecundario' => '98765',
            'dataRegistro'       => '01/01/1990',
            'ativo'              => 'true',
        ]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new app\controller\Customer())->insert($request, $response);

    $result->getBody()->rewind();

    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(201);

    expect($json['status'])->toBeTrue();

    expect($json['msg'])->toContain('Salvo com sucesso');
});