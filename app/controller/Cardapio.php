<?php

declare(strict_types=1);

namespace App\Controller;

final class Cardapio extends Base
{
    private array $itens = [
        // ── Entradas ──
        ['id'=>1,'nome'=>'Carpaccio de Filé',   'preco'=>42.00,'categoria'=>'Entradas',  'emoji'=>'🥩','descricao'=>'Filé fatiado, alcaparras, parmesão e azeite trufado','tempo'=>'15 min','destaque'=>true],
        ['id'=>2,'nome'=>'Bruschetta Clássica', 'preco'=>28.00,'categoria'=>'Entradas',  'emoji'=>'🍞','descricao'=>'Pão rústico, tomate concassé e manjericão fresco',   'tempo'=>'8 min', 'destaque'=>false],
        ['id'=>3,'nome'=>'Creme de Cogumelos',  'preco'=>34.00,'categoria'=>'Entradas',  'emoji'=>'🍄','descricao'=>'Cogumelos selvagens com creme e croutons dourados',  'tempo'=>'12 min','destaque'=>true],
        ['id'=>4,'nome'=>'Medalhão ao Madeira', 'preco'=>89.00,'categoria'=>'Principais','emoji'=>'🥩','descricao'=>'Filé grelhado, molho madeira e purê de batata trufado','tempo'=>'30 min','destaque'=>true],
        ['id'=>5,'nome'=>'Salmão Grelhado',     'preco'=>79.00,'categoria'=>'Principais','emoji'=>'🐟','descricao'=>'Crosta de ervas finas e risoto de limão siciliano',    'tempo'=>'25 min','destaque'=>false],
        ['id'=>6,'nome'=>'Risoto de Camarão',   'preco'=>82.00,'categoria'=>'Principais','emoji'=>'🍤','descricao'=>'Camarões salteados, risoto cremoso com açafrão',       'tempo'=>'28 min','destaque'=>true],
        ['id'=>7,'nome'=>'Nhoque ao Gorgonzola','preco'=>58.00,'categoria'=>'Principais','emoji'=>'🍝','descricao'=>'Nhoque artesanal, molho gorgonzola e nozes tostadas',  'tempo'=>'20 min','destaque'=>false],
        ['id'=>8, 'nome'=>'Crème Brûlée',       'preco'=>32.00,'categoria'=>'Sobremesas','emoji'=>'🍮','descricao'=>'Clássico francês com baunilha e caramelo crocante',    'tempo'=>'10 min','destaque'=>true],
        ['id'=>9, 'nome'=>'Fondant de Chocolate','preco'=>36.00,'categoria'=>'Sobremesas','emoji'=>'🍫','descricao'=>'Centro derretido com sorvete de baunilha artesanal',  'tempo'=>'15 min','destaque'=>false],
        ['id'=>10,'nome'=>'Panna Cotta',         'preco'=>28.00,'categoria'=>'Sobremesas','emoji'=>'🍨','descricao'=>'Coulis de frutas vermelhas e fio de mel',              'tempo'=>'8 min', 'destaque'=>false],
        ['id'=>11,'nome'=>'Água Mineral',        'preco'=>9.00, 'categoria'=>'Bebidas',  'emoji'=>'💧','descricao'=>'Sem gás ou com gás, 500ml',              'tempo'=>'2 min','destaque'=>false],
        ['id'=>12,'nome'=>'Suco Natural',        'preco'=>16.00,'categoria'=>'Bebidas',  'emoji'=>'🍊','descricao'=>'Fruta do dia espremida na hora, 400ml', 'tempo'=>'5 min','destaque'=>false],
        ['id'=>13,'nome'=>'Espumante Casa',      'preco'=>52.00,'categoria'=>'Bebidas',  'emoji'=>'🥂','descricao'=>'Espumante brut da casa, servido em taça','tempo'=>'3 min','destaque'=>true],
    ];

    // GET /cardapio?mesa=N
    public function index($request, $response)
    {
        $params = $request->getQueryParams();
        $mesa   = isset($params['mesa']) ? (int) $params['mesa'] : null;
        $mesaValida = ($mesa !== null && $mesa >= 1 && $mesa <= 99);

        return $this->getTwig()
            ->render($response, $this->setView('cardapio/index'), [
                'mesa'       => $mesaValida ? $mesa : null,
                'mesaValida' => $mesaValida,
                'nomeLocal'  => 'AllPratto',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    // GET /cardapio/itens → JSON
    public function getItens($request, $response)
    {
        $agrupado = [];
        foreach ($this->itens as $item) {
            $agrupado[$item['categoria']][] = $item;
        }

        return $this->json($response, [
            'sucesso' => true,
            'dados'   => $agrupado,
        ], 200);
    }

    // POST /cardapio/pedido → JSON
    public function salvarPedido($request, $response)
    {
        $body = $request->getParsedBody();

        if (empty($body['mesa']) || empty($body['itens']) || empty($body['pagamento'])) {
            return $this->json($response, [
                'sucesso' => false,
                'erro'    => 'Dados incompletos',
            ], 400);
        }

        $mesa      = (int) $body['mesa'];
        $itens     = $body['itens'];
        $pagamento = $body['pagamento'];

        $total            = 0;
        $itensSanitizados = [];

        foreach ($itens as $itemPedido) {
            $encontrado = $this->buscarItemPorId((int) $itemPedido['id']);
            if (!$encontrado) continue;

            $qty    = max(1, (int) $itemPedido['quantidade']);
            $subtot = $encontrado['preco'] * $qty;
            $total += $subtot;

            $itensSanitizados[] = [
                'id'         => $encontrado['id'],
                'nome'       => $encontrado['nome'],
                'preco'      => $encontrado['preco'],
                'quantidade' => $qty,
                'subtotal'   => $subtot,
            ];
        }

        return $this->json($response, [
            'sucesso'   => true,
            'pedido_id' => rand(1000, 9999),
            'mesa'      => $mesa,
            'total'     => $total,
            'mensagem'  => 'Pedido enviado para a cozinha!',
        ], 200);
    }

    private function buscarItemPorId(int $id): ?array
    {
        foreach ($this->itens as $item) {
            if ($item['id'] === $id) return $item;
        }
        return null;
    }
}