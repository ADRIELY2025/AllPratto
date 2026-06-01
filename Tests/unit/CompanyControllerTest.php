<?php

declare(strict_types=1);

use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

// ── insert ────────────────────────────────────────────────────────────────────
test('company insert com dados válidos retorna 201 com status true', function () {

    $request = (new RequestFactory())
        ->createRequest('POST', '/company')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([
            'nomeExibicao'      => 'Empresa Teste ',
            'nomeLegal'         => 'Empresa Teste  ',
            'cnpj'              => '12.345.678/0001-99',
            'inscricaoEstadual' => '123456789',
            'telefone'          => '(68) 3212-0000',
            'email'             => 'contato@empresateste.com',
            'endereco'          => 'Rua Floriano Peixoto',
            'numero'            => '1500',
            'bairro'            => 'Centro',
            'cidade'            => 'Rio Branco',
            'estado'            => 'AC',
            'cep'               => '69900-000',
            'ativo'             => 'true',
        ]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new app\controller\Company())->insert($request, $response);

    $result->getBody()->rewind();

    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(201);

    expect($json['status'])->toBeTrue();

    expect($json['msg'])->toContain('Salvo com sucesso');
});