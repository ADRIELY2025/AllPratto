<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\DB;
use Doctrine\DBAL\ParameterType;

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

    // ─── Mapeamento forma de pagamento → código payment_terms ───────────────
    private array $pagamentoCodigo = [
        'pix'      => '17',
        'credito'  => '03',
        'debito'   => '04',
        'dinheiro' => '01',
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
    // Ao finalizar, cria automaticamente:
    //   sale → item_sale → purchase → item_purchase →
    //   installment_sale_purchase → order → order_item → kitchen (via trigger)
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

        // ── 1. Valida e sanitiza itens ─────────────────────────────────────
        $total            = 0.0;
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
                'emoji'      => $encontrado['emoji'],
            ];
        }

        if (empty($itensSanitizados)) {
            return $this->json($response, [
                'sucesso' => false,
                'erro'    => 'Nenhum item válido encontrado',
            ], 400);
        }

        $conn = DB::connection();

        try {
            $conn->beginTransaction();

            // ── 2. Resolve a mesa (busca id pelo número) ───────────────────
            $mesaRow = $conn->fetchAssociative(
                'SELECT id FROM mesa WHERE numero = ? AND ativo = TRUE LIMIT 1',
                [$mesa]
            );

            if (!$mesaRow) {
                // Mesa não existe no banco — cria automaticamente
                $conn->insert('mesa', [
                    'numero'    => $mesa,
                    'status'    => 'ocupada',
                    'capacidade'=> 4,
                    'ativo'     => true,
                ]);
                $mesaId = (int) $conn->lastInsertId('mesa_id_seq');
            } else {
                $mesaId = (int) $mesaRow['id'];
                // Marca mesa como ocupada
                $conn->update('mesa', ['status' => 'ocupada'], ['id' => $mesaId]);
            }

            // ── 3. Resolve payment_terms ───────────────────────────────────
            $codigoPagamento = $this->pagamentoCodigo[$pagamento] ?? '99';
            $paymentRow = $conn->fetchAssociative(
                'SELECT id FROM payment_terms WHERE codigo = ? LIMIT 1',
                [$codigoPagamento]
            );

            if ($paymentRow) {
                $paymentTermsId = (int) $paymentRow['id'];
            } else {
                // Cria o payment_terms se não existir
                $tituloMap = [
                    'pix'      => 'PIX',
                    'credito'  => 'Cartão de Crédito',
                    'debito'   => 'Cartão de Débito',
                    'dinheiro' => 'Dinheiro',
                ];
                $conn->insert('payment_terms', [
                    'codigo' => $codigoPagamento,
                    'titulo' => $tituloMap[$pagamento] ?? 'Outros',
                    'atalho' => strtoupper(substr($pagamento, 0, 3)),
                ]);
                $paymentTermsId = (int) $conn->lastInsertId('payment_terms_id_seq');

                // Cria ao menos uma parcela vinculada a esse payment_terms
                $conn->insert('installment', [
                    'id_pagamento' => $paymentTermsId,
                    'parcela'      => 1,
                    'intervalo'    => 0,
                ]);
            }

            // Busca a primeira parcela desse payment_terms
            $installmentRow = $conn->fetchAssociative(
                'SELECT id FROM installment WHERE id_pagamento = ? ORDER BY parcela ASC LIMIT 1',
                [$paymentTermsId]
            );
            $installmentId = $installmentRow ? (int) $installmentRow['id'] : null;

            // ── 4. Cria a SALE (venda) já como VENDA para baixar estoque ──
            $conn->insert('sale', [
                'total_bruto'   => $total,
                'total_liquido' => $total,
                'desconto'      => 0,
                'acrescimo'     => 0,
                'observacao'    => "Pedido cardápio digital — Mesa {$mesa}",
                'estado_venda'  => 'VENDA',
            ]);
            $saleId = (int) $conn->lastInsertId('sale_id_seq');

            // ── 5. Cria os ITEM_SALE ───────────────────────────────────────
            foreach ($itensSanitizados as $item) {
                $conn->insert('item_sale', [
                    'id_venda'         => $saleId,
                    'nome'             => $item['nome'],
                    'quantidade'       => $item['quantidade'],
                    'unitario_bruto'   => $item['preco'],
                    'total_bruto'      => $item['subtotal'],
                    'unitario_liquido' => $item['preco'],
                    'total_liquido'    => $item['subtotal'],
                    'desconto'         => 0,
                    'acrescimo'        => 0,
                ]);
            }

            // ── 6. Cria a PURCHASE (compra interna / consumo) ─────────────
            // Representa o consumo interno gerado pela comanda do cardápio.
            $conn->insert('purchase', [
                'total_bruto'    => $total,
                'total_liquido'  => $total,
                'desconto'       => 0,
                'acrescimo'      => 0,
                'observacao'     => "Consumo cardápio — Mesa {$mesa} — Sale #{$saleId}",
                'estado_compra'  => 'FINALIZADO',
            ]);
            $purchaseId = (int) $conn->lastInsertId('purchase_id_seq');

            // ── 7. Cria os ITEM_PURCHASE ───────────────────────────────────
            foreach ($itensSanitizados as $item) {
                $conn->insert('item_purchase', [
                    'id_compra'      => $purchaseId,
                    'nome'           => $item['nome'],
                    'quantidade'     => $item['quantidade'],
                    'preco_unitario' => $item['preco'],
                    'total_bruto'    => $item['subtotal'],
                    'total_liquido'  => $item['subtotal'],
                    'desconto'       => 0,
                    'acrescimo'      => 0,
                ]);
            }

            // ── 8. Cria o PAYMENT (installment_sale_purchase) ─────────────
            if ($installmentId) {
                $conn->insert('installment_sale_purchase', [
                    'id_payment'     => $paymentTermsId,
                    'id_sale'        => $saleId,
                    'id_installment' => $installmentId,
                    'total_parcelas' => 1,
                    'numero_parcela' => 1,
                    'valor_parcela'  => $total,
                    'valor_total'    => $total,
                    'status'         => 'aberto',
                    'data_vencimento'=> date('Y-m-d'),
                ]);
            }

            // ── 9. Cria o ORDER ────────────────────────────────────────────
            $conn->insert('"order"', [
                'id_mesa'          => $mesaId,
                'payment_terms_id' => $paymentTermsId,
                'total'            => $total,
                'status'           => 'pendente',
                'observacao'       => "Cardápio digital — pagamento: {$pagamento}",
            ]);
            $orderId = (int) $conn->lastInsertId('order_id_seq');

            // ── 10. Cria os ORDER_ITEM (trigger dispara kitchen) ───────────
            // O trigger trg_order_item_to_kitchen em order_item cria
            // automaticamente os registros em kitchen.
            foreach ($itensSanitizados as $item) {
                $conn->insert('order_item', [
                    'order_id'   => $orderId,
                    'nome'       => $item['emoji'] . ' ' . $item['nome'],
                    'preco'      => $item['preco'],
                    'quantidade' => $item['quantidade'],
                    'subtotal'   => $item['subtotal'],
                ]);
            }

            $conn->commit();

            return $this->json($response, [
                'sucesso'     => true,
                'pedido_id'   => $orderId,
                'sale_id'     => $saleId,
                'purchase_id' => $purchaseId,
                'mesa'        => $mesa,
                'total'       => $total,
                'mensagem'    => 'Pedido enviado para a cozinha!',
            ], 200);

        } catch (\Throwable $e) {
            $conn->rollBack();

            return $this->json($response, [
                'sucesso' => false,
                'erro'    => 'Erro ao processar pedido: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function buscarItemPorId(int $id): ?array
    {
        foreach ($this->itens as $item) {
            if ($item['id'] === $id) return $item;
        }
        return null;
    }
}
