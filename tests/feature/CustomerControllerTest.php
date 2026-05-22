<?php

declare(strict_types=1);

# Importa as classes PSR-7 do Slim para simular requisições HTTP
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

# Instancia o controller antes de cada teste
beforeEach(function () {
    $this->controller = new app\controller\Customer();
    $this->responseFactory = new ResponseFactory();
});

# Função auxiliar que cria uma requisição POST com body simulado
function createPostRequest(array $body): \Psr\Http\Message\ServerRequestInterface
{
    $request = (new RequestFactory())->createRequest('POST', '/cliente/update');

    return $request
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody($body);
}

# Teste 1: update sem ID deve retornar 403 — protege contra atualização cega
test('update sem ID retorna 403 com mensagem de erro', function () {
    $request = createPostRequest([
        'nomeExibicao' => 'Cliente Teste',
        'dataRegistro' => '17/03/2025',
        'ativo' => 'true',
        # Propositalmente sem 'id'
    ]);

    $response = $this->responseFactory->createResponse();
    $result = $this->controller->update($request, $response);

    # Lê o corpo da resposta JSON
    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);

    # Status HTTP deve ser 403
    expect($result->getStatusCode())->toBe(403);

    # JSON deve indicar falha
    expect($json['status'])->toBeFalse();

    # Mensagem deve pedir o ID
    expect($json['msg'])->toContain('ID');
});

# Teste 2: delete sem ID deve retornar 403 — protege contra exclusão acidental
test('delete sem ID retorna 403 com mensagem de erro', function () {
    $request = createPostRequest([
        # Propositalmente sem 'id'
    ]);

    $response = $this->responseFactory->createResponse();
    $result = $this->controller->delete($request, $response);

    # Lê o corpo da resposta JSON
    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);

    # Status HTTP deve ser 403
    expect($result->getStatusCode())->toBe(403);

    # JSON deve indicar falha
    expect($json['status'])->toBeFalse();

    # Mensagem deve pedir o código do cliente
    expect($json['msg'])->toContain('cliente');
});
