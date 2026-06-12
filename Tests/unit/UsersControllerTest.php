<?php

declare(strict_types=1);

use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

// ── insert ────────────────────────────────────────────────────────────────────
// O controller Users::insert() insere: nome, senha, ativo
// A tabela 'users' não possui campo email; contatos ficam em vw_user/contact.
test('users insert com dados válidos retorna 201 com status true', function () {

    $request = (new RequestFactory())
        ->createRequest('POST', '/users')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([
            'nome'  => 'uriel ',
            'senha' => '123456',
            'ativo' => 'true',
        ]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new App\Controller\Users())->insert($request, $response);

    $result->getBody()->rewind();

    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(201);

    expect($json['status'])->toBeTrue();

    expect($json['msg'])->toContain('Salvo com sucesso');
});

// ── update — sem ID retorna 403 ───────────────────────────────────────────────
test('users update sem id retorna 403 com status false', function () {

    $request = (new RequestFactory())
        ->createRequest('POST', '/users/update')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([
            'nome'  => 'Felipe Atualizado',
            'ativo' => 'true',
        ]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new App\Controller\Users())->update($request, $response);

    $result->getBody()->rewind();

    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(403);

    expect($json['status'])->toBeFalse();

    expect($json['msg'])->toContain('Por favor informe o ID do registro');
});

// ── delete — sem ID retorna 403 ───────────────────────────────────────────────
test('users delete sem id retorna 403 com status false', function () {

    $request = (new RequestFactory())
        ->createRequest('POST', '/users/delete')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new App\Controller\Users())->delete($request, $response);

    $result->getBody()->rewind();

    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(403);

    expect($json['status'])->toBeFalse();

    expect($json['msg'])->toContain('Informe o código do usuário');
});

// ── listingdata — retorna estrutura DataTables ────────────────────────────────
// A query no controller seleciona colunas explícitas sem 'email',
// portanto o retorno não inclui essa chave — o teste não verifica email.
test('users listingdata retorna 200 com estrutura DataTables', function () {

    $request = (new RequestFactory())
        ->createRequest('POST', '/users/listingdata')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([
            'start'  => '0',
            'length' => '10',
            'search' => ['value' => ''],
            'order'  => [['column' => '0', 'dir' => 'desc']],
        ]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new App\Controller\Users())->listingdata($request, $response);

    $result->getBody()->rewind();

    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(200);

    expect($json)->toHaveKey('recordsTotal');

    expect($json)->toHaveKey('recordsFiltered');

    expect($json)->toHaveKey('data');
});