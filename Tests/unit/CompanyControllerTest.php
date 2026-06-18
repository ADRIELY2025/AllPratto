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
        'nome_fantasia'       => 'Test Company',
        'razao_social'        => 'Test Company Ltda',
        'cnpj'                => '12345678901234',
        'inscricao_estadual'  => '123456789012',
        'telefone'            => '11999999999',
        'email'               => 'test@example.com',
        'endereco'            => 'Test Address',
        'numero'              => '123',
        'bairro'              => 'Test Neighborhood',
        'cidade'              => 'Test City',
        'estado'              => 'Test State',
        'cep'                 => 'Test CEP',
        ]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new App\Controller\Company())->insert($request, $response);

    $result->getBody()->rewind();

    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(201);

    expect($json['status'])->toBeTrue();

    expect($json['msg'])->toContain('Salvo com sucesso');
});