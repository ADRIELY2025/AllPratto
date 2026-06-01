<?php

declare(strict_types=1);

use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

// preRegister usa os nomes de campo com prefixo "cad-" conforme Login.php
// Retorna status HTTP 200 (não 201) com msg 'Usuário cadastrado com sucesso!'
test('preRegister com dados válidos retorna 200 com status true', function () {

    $request = (new RequestFactory())
        ->createRequest('POST', '/authentication/preregister')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([
            'cad-nome'             => 'Wilton',
            'cad-sobrenome'        => 'Will de Paulo',
            'cad-cpf'              => '111.444.777-35',
            'cad-rg'               => '123456789',
            'cad-senha'            => '1234',
            'cad-confirmar-senha'  => '1234',
            'cad-email'            => 'wiltonwilldepaulo@gmail.com',
            'cad-telefone'         => '(69) 9 9906-0839',
        ]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new app\controller\Login())->preRegister($request, $response);

    $result->getBody()->rewind();

    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(200);

    expect($json['msg'])->toContain('Usuário cadastrado com sucesso');

    expect($json['status'])->toBeTrue();
});