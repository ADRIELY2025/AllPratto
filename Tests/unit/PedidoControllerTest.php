<?php

declare(strict_types=1);

use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;


beforeEach(function () {
    $numeroMesaTeste = random_int(90000, 99999);

    App\Database\DB::connection()->insert('mesa', [
        'numero'     => $numeroMesaTeste,
        'capacidade' => 4,
        'status'     => 'livre',
    ]);

    $mesa = App\Database\DB::select('id')
        ->from('mesa')
        ->where('numero = :numero')
        ->setParameter('numero', $numeroMesaTeste)
        ->fetchAssociative();

    $this->idMesaTeste = (int) $mesa['id'];
});

function createPedidoRequest(string $uri, array $body): \Psr\Http\Message\ServerRequestInterface
{
    return (new RequestFactory())
        ->createRequest('POST', $uri)
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody($body);
}

// ── insert ───────────────────────────────────────────────────────────────────
test('pedido insert com mesa e itens válidos retorna 201 com status true', function () {

    $request = createPedidoRequest('/pedido/insert', [
        'mesa'       => (string) $this->idMesaTeste,
        'itens'      => [
            ['nome' => 'X-Burger', 'preco' => '18,90', 'quantidade' => 2],
            ['nome' => 'Refrigerante Lata', 'preco' => '6,00', 'quantidade' => 1],
        ],
        'pagamento'  => 'pix',
        'observacao' => 'Sem cebola no X-Burger',
    ]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new App\Controller\Pedido())->insert($request, $response);

    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(201);
    expect($json['status'])->toBeTrue();
    expect($json['msg'])->toBe('Pedido enviado para a cozinha!');
    expect($json)->toHaveKey('id');
    expect($json['id'])->toBeGreaterThan(0);

    // Confere o total calculado: (18,90 * 2) + (6,00 * 1) = 43,80
    expect((float) $json['total'])->toBe(43.80);

    // Guarda o ID gerado para os próximos testes deste arquivo
    $this->idPedidoCriado = $json['id'];
});

test('pedido insert sem mesa retorna 400', function () {

    $request = createPedidoRequest('/pedido/insert', [
        'itens' => [
            ['nome' => 'X-Burger', 'preco' => '18,90', 'quantidade' => 1],
        ],
        // Propositalmente sem 'mesa'
    ]);

    $response = (new ResponseFactory())->createResponse();
    $result   = (new App\Controller\Pedido())->insert($request, $response);

    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(400);
    expect($json['status'])->toBeFalse();
    expect($json['msg'])->toContain('Mesa e itens são obrigatórios');
});

test('pedido insert sem itens retorna 400', function () {

    $request = createPedidoRequest('/pedido/insert', [
        'mesa'  => (string) $this->idMesaTeste,
        'itens' => [],
        // Propositalmente sem itens
    ]);

    $response = (new ResponseFactory())->createResponse();
    $result   = (new App\Controller\Pedido())->insert($request, $response);

    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(400);
    expect($json['status'])->toBeFalse();
});

test('pedido insert com mesa inexistente retorna 404', function () {

    $request = createPedidoRequest('/pedido/insert', [
        'mesa'  => '999999', // ID que não existe no banco
        'itens' => [
            ['nome' => 'X-Burger', 'preco' => '18,90', 'quantidade' => 1],
        ],
    ]);

    $response = (new ResponseFactory())->createResponse();
    $result   = (new App\Controller\Pedido())->insert($request, $response);

    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(404);
    expect($json['status'])->toBeFalse();
    expect($json['msg'])->toContain('Mesa não encontrada');
});

// ── getItens ─────────────────────────────────────────────────────────────────
test('pedido getItens retorna os itens cadastrados do pedido', function () {

    // Cria um pedido novo só para este teste, evitando depender da ordem de execução
    $request = createPedidoRequest('/pedido/insert', [
        'mesa'  => (string) $this->idMesaTeste,
        'itens' => [
            ['nome' => 'Suco de Laranja', 'preco' => '8,50', 'quantidade' => 3],
        ],
        'pagamento' => 'dinheiro',
    ]);
    $response = (new ResponseFactory())->createResponse();
    $insertJson = json_decode(
        (new App\Controller\Pedido())->insert($request, $response)->getBody()->__toString(),
        true
    );
    $idPedido = $insertJson['id'];

    $requestItens = (new RequestFactory())->createRequest('GET', "/pedido/itens/{$idPedido}");
    $responseItens = (new ResponseFactory())->createResponse();

    $result = (new App\Controller\Pedido())->getItens($requestItens, $responseItens, ['id' => $idPedido]);

    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(200);
    expect($json['status'])->toBeTrue();

    // CORREÇÃO: o controller devolve a chave 'itens', não 'msg'.
    // (Veja Pedido::getItens -> $this->json($response, ['status' => true, 'itens' => $itens], 200);)
    expect($json)->toHaveKey('itens');
    expect($json['itens'])->toHaveCount(1);
    expect($json['itens'][0]['nome'])->toBe('Suco de Laranja');
});

// ── updateStatus ─────────────────────────────────────────────────────────────
test('pedido updateStatus sem id retorna 403', function () {

    $request = createPedidoRequest('/pedido/update-status', [
        'status' => 'pronto',
        // Propositalmente sem 'id'
    ]);

    $response = (new ResponseFactory())->createResponse();
    $result   = (new App\Controller\Pedido())->updateStatus($request, $response);

    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(403);
    expect($json['status'])->toBeFalse();
});

test('pedido updateStatus com status inválido retorna 422', function () {

    $request = createPedidoRequest('/pedido/update-status', [
        'id'     => '1',
        'status' => 'status_que_nao_existe',
    ]);

    $response = (new ResponseFactory())->createResponse();
    $result   = (new App\Controller\Pedido())->updateStatus($request, $response);

    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(422);
    expect($json['status'])->toBeFalse();
    expect($json['msg'])->toContain('Status inválido');
});

test('pedido updateStatus para pago libera a mesa automaticamente', function () {

    // Cria um pedido novo, ocupando a mesa de teste
    $requestInsert = createPedidoRequest('/pedido/insert', [
        'mesa'  => (string) $this->idMesaTeste,
        'itens' => [
            ['nome' => 'Água Mineral', 'preco' => '4,00', 'quantidade' => 1],
        ],
        'pagamento' => 'dinheiro',
    ]);
    $insertJson = json_decode(
        (new App\Controller\Pedido())->insert($requestInsert, (new ResponseFactory())->createResponse())
            ->getBody()->__toString(),
        true
    );
    $idPedido = $insertJson['id'];

    // CORREÇÃO: após o insert, a mesa deve ficar 'ocupada' (regra em Pedido::insert,
    // passo 8: $conn->update('mesa', ['status' => 'ocupada', ...])).
    // A asserção antiga checava 'livre', o que contradizia o próprio comentário do teste.
    $mesaOcupada = App\Database\DB::select('status')
        ->from('mesa')
        ->where('id = :id')
        ->setParameter('id', $this->idMesaTeste)
        ->fetchAssociative();
    expect($mesaOcupada['status'])->toBe('ocupada'); // mesa deve estar ocupada após o insert

    // Marca o pedido como pago
    $requestStatus = createPedidoRequest('/pedido/update-status', [
        'id'     => (string) $idPedido,
        'status' => 'pago',
    ]);
    $result = (new App\Controller\Pedido())->updateStatus($requestStatus, (new ResponseFactory())->createResponse());

    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(200);
    expect($json['status'])->toBeTrue();

    // Regra de negócio: mesa deve voltar a ficar livre quando o pedido é pago
    $mesaLivre = App\Database\DB::select('status')
        ->from('mesa')
        ->where('id = :id')
        ->setParameter('id', $this->idMesaTeste)
        ->fetchAssociative();
    expect($mesaLivre['status'])->toBe('livre');
});

// ── delete (cancelar) ────────────────────────────────────────────────────────
test('pedido delete sem id retorna 403', function () {

    $request = createPedidoRequest('/pedido/delete', [
        // Propositalmente sem 'id'
    ]);

    $response = (new ResponseFactory())->createResponse();
    $result   = (new App\Controller\Pedido())->delete($request, $response);

    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(403);
    expect($json['status'])->toBeFalse();
});

test('pedido delete de um pedido pendente cancela e libera a mesa', function () {

    $requestInsert = createPedidoRequest('/pedido/insert', [
        'mesa'  => (string) $this->idMesaTeste,
        'itens' => [
            ['nome' => 'Pastel de Queijo', 'preco' => '9,00', 'quantidade' => 1],
        ],
        'pagamento' => 'dinheiro',
    ]);
    $insertJson = json_decode(
        (new App\Controller\Pedido())->insert($requestInsert, (new ResponseFactory())->createResponse())
            ->getBody()->__toString(),
        true
    );
    $idPedido = $insertJson['id'];

    $requestDelete = createPedidoRequest('/pedido/delete', ['id' => (string) $idPedido]);
    $result = (new App\Controller\Pedido())->delete($requestDelete, (new ResponseFactory())->createResponse());

    $result->getBody()->rewind();
    $json = json_decode($result->getBody()->getContents(), true);

    // CORREÇÃO: um pedido recém-criado fica com status 'pendente', que É permitido
    // cancelar (veja Pedido::delete: só bloqueia 'pronto', 'pago', 'entregue', 'cancelado').
    // O teste antigo esperava 403 com uma mensagem que não existe no controller
    // ("não é possível cancelar um pedido que já foi pago"), o que contradizia
    // o próprio nome do teste ("cancela e libera a mesa").
    expect($result->getStatusCode())->toBe(200);
    expect($json['status'])->toBeTrue();
    expect($json['msg'])->toBe('Pedido cancelado com sucesso!');

    $mesaLivre = App\Database\DB::select('status')
        ->from('mesa')
        ->where('id = :id')
        ->setParameter('id', $this->idMesaTeste)
        ->fetchAssociative();
    expect($mesaLivre['status'])->toBe('livre');
});