<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\DB;

final class Cardapio extends Base
{
    // ──────────────────────────────────────────────────────────────
    // GET /cardapio?mesa=N  →  renderiza a view
    // ──────────────────────────────────────────────────────────────
    public function index($request, $response)
    {
        $params     = $request->getQueryParams();
        $mesa       = isset($params['mesa']) ? (int) $params['mesa'] : null;
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

    // ──────────────────────────────────────────────────────────────
    // GET /cardapio/itens  →  JSON com itens agrupados por categoria
    // ──────────────────────────────────────────────────────────────
    public function getItens($request, $response)
    {
        $rows = DB::select(
                'id',
                'descricao   AS nome',
                'valor_venda AS preco',
                'categoria',
                'emoji',
                'tempo_preparo AS tempo',
                'destaque',
                'imagem_url  AS imagemUrl',
            )
            ->from('product')
            ->where('ativo = TRUE')
            ->andWhere('categoria IS NOT NULL')   // só produtos com categoria definida aparecem no cardápio
            ->orderBy('categoria', 'ASC')
            ->addOrderBy('descricao', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        // Agrupa por categoria
        $agrupado = [];
        foreach ($rows as $row) {
            $row['preco']    = (float)  $row['preco'];
            $row['destaque'] = (bool)   $row['destaque'];
            $agrupado[$row['categoria']][] = $row;
        }

        return $this->json($response, [
            'sucesso' => true,
            'dados'   => $agrupado,
        ], 200);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /cardapio/pedido  →  salva order + order_items no banco
    // O trigger trg_order_item_to_kitchen popula kitchen automaticamente
    // ──────────────────────────────────────────────────────────────
    public function salvarPedido($request, $response)
    {
        $body = $request->getParsedBody();

        if (empty($body['mesa']) || empty($body['itens']) || empty($body['pagamento'])) {
            return $this->json($response, [
                'sucesso' => false,
                'erro'    => 'Dados incompletos',
            ], 400);
        }

        $mesa      = (int)    $body['mesa'];
        $pagamento = (string) $body['pagamento'];
        $itens     = (array)  $body['itens'];

        // ── Valida e calcula total ────────────────────────────────
        $itensSanitizados = [];
        $total            = 0.0;

        foreach ($itens as $itemReq) {
            $id  = (int) ($itemReq['id']         ?? 0);
            $qty = max(1, (int) ($itemReq['quantidade'] ?? 1));

            // Busca preço real no banco — nunca confia no preço vindo do front
            $produto = DB::select('id', 'descricao AS nome', 'valor_venda AS preco')
                ->from('product')
                ->where('id = :id')
                ->andWhere('ativo = TRUE')
                ->setParameter('id', $id)
                ->executeQuery()
                ->fetchAssociative();

            if (!$produto) continue;

            $subtotal = (float) $produto['preco'] * $qty;
            $total   += $subtotal;

            $itensSanitizados[] = [
                'product_id' => $produto['id'],
                'nome'       => $produto['nome'],
                'preco'      => (float) $produto['preco'],
                'quantidade' => $qty,
                'subtotal'   => $subtotal,
            ];
        }

        if (empty($itensSanitizados)) {
            return $this->json($response, [
                'sucesso' => false,
                'erro'    => 'Nenhum item válido no pedido',
            ], 422);
        }

        // ── Persiste em transação ─────────────────────────────────
        $conn = DB::connection();

        try {
            $conn->beginTransaction();

            // 1. Insere o pedido
            $conn->insert('"order"', [
                'mesa'      => $mesa,
                'pagamento' => $pagamento,
                'total'     => $total,
                'status'    => 'pendente',
            ]);
            $orderId = (int) $conn->lastInsertId();

            // 2. Insere os itens
            // O trigger fn_order_item_to_kitchen dispara aqui
            // e popula a tabela kitchen automaticamente
            foreach ($itensSanitizados as $item) {
                $conn->insert('order_item', [
                    'order_id'   => $orderId,
                    'product_id' => $item['product_id'],
                    'nome'       => $item['nome'],
                    'preco'      => $item['preco'],
                    'quantidade' => $item['quantidade'],
                    'subtotal'   => $item['subtotal'],
                ]);
            }

            $conn->commit();

        } catch (\Throwable $e) {
            $conn->rollBack();
            return $this->json($response, [
                'sucesso' => false,
                'erro'    => 'Erro ao salvar pedido. Tente novamente.',
            ], 500);
        }

        return $this->json($response, [
            'sucesso'   => true,
            'pedido_id' => $orderId,
            'mesa'      => $mesa,
            'total'     => $total,
            'mensagem'  => 'Pedido enviado para a cozinha!',
        ], 200);
    }
}