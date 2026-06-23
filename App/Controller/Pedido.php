<?php

declare(strict_types=1);

namespace App\Controller;

final class Pedido extends Base
{
    public function cozinha($request, $response)
{
    require __DIR__ . '/../View/pages/cozinha.html';
    return $response;
}

public function listarCozinha($request, $response)
{
    try {

        $pedidos = \App\Database\DB::select("
            o.id,
            o.id_mesa,
            o.total,
            o.status,
            o.observacao,
            o.criado_em,
            m.numero AS mesa_numero
        ")
        ->from('"order"', 'o')
        ->leftJoin('o', 'mesa', 'm', 'm.id = o.id_mesa')
        ->where("o.status IN ('pendente','em_preparo')")
        ->orderBy('o.criado_em', 'ASC')
        ->fetchAllAssociative();

        foreach ($pedidos as &$pedido) {

            $itens = \App\Database\DB::select("
                nome,
                quantidade,
                preco,
                subtotal
            ")
            ->from('order_item')
            ->where('order_id = :id')
            ->setParameter('id', $pedido['id'])
            ->fetchAllAssociative();

            $pedido['itens'] = $itens;
        }

        return $this->json(
            $response,
            [
                'status' => true,
                'pedidos' => $pedidos
            ]
        );
    }
    catch(\Exception $e){

        return $this->json(
            $response,
            [
                'status' => false,
                'msg' => $e->getMessage()
            ],
            500
        );
    }
}


    // ──────────────────────────────────────────
    //  Página HTML da lista de pedidos (cozinha / admin)
    // ──────────────────────────────────────────
    public function list($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('list-pedido'), [
                'titulo' => 'Lista de pedidos',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    // ──────────────────────────────────────────
    //  Página HTML de detalhes de um pedido
    // ──────────────────────────────────────────
    public function details($request, $response, $args)
    {
        $id     = $args['id'] ?? null;
        $action = ($id === null) ? 'c' : 'e';
        $pedido = [];

        if (!is_null($id)) {
            $qb     = \App\Database\DB::select('*')->from('"order"');
            $pedido = $qb
                ->where('id = ' . $qb->createPositionalParameter($id, \Doctrine\DBAL\ParameterType::INTEGER))
                ->fetchAssociative();
        }

        return $this->getTwig()
            ->render($response, $this->setView('pedido'), [
                'titulo' => 'Detalhes do pedido',
                'id'     => $id,
                'action' => $action,
                'pedido' => $pedido,
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Criar pedido — fluxo completo em uma única transação
    //
    //  Body JSON: {
    //    mesa:      <id da mesa>,
    //    itens:     [{ id, nome, preco, quantidade }],
    //    pagamento: 'dinheiro' | 'pix' | 'credito' | 'debito' | <qualquer string>,
    //    observacao: '...' (opcional),
    //    id_cliente: <id> (opcional)
    //  }
    //
    //  O que esta transação grava:
    //    1. "order"                  — o pedido em si
    //    2.  order_item              — itens do pedido
    //       → trigger do banco popula kitchen automaticamente ao inserir order_item
    //    3.  sale                    — venda vinculada (estado PRE_VENDA → VENDA ao pagar)
    //    4.  item_sale               — um item_sale por item do carrinho
    //    5.  payment_terms           — busca ou cria o registro da forma de pagamento
    //    6.  installment             — parcela única (à vista) ligada ao payment_terms
    //    7.  purchase                — registro de compra (custo interno da retirada do estoque)
    //    8.  item_purchase           — um item_purchase por item (custo de cada produto)
    //    9.  installment_sale_purchase — vincula sale + purchase + payment_terms + installment
    //   10.  mesa.status             — marca a mesa como 'ocupada'
    // ──────────────────────────────────────────────────────────────────────────
    public function insert($request, $response)
    {
        $form      = $request->getParsedBody();
        $idMesa    = isset($form['mesa'])      ? (int) $form['mesa']    : null;
        $itens     = $form['itens']            ?? [];
        $pagamento = trim((string) ($form['pagamento']  ?? 'dinheiro'));
        $observacao = $form['observacao']      ?? null;
        $idCliente = isset($form['id_cliente']) && $form['id_cliente'] !== ''
            ? (int) $form['id_cliente'] : null;

        // Parcelas e intervalo — só relevantes em crédito/débito; default 1x / 0 dias
        $totalParcelas = isset($form['parcelas'])  && (int) $form['parcelas']  >= 1 ? (int) $form['parcelas']  : 1;
        $intervalo     = isset($form['intervalo']) && (int) $form['intervalo'] >= 0 ? (int) $form['intervalo'] : 0;

        if (!$idMesa || empty($itens)) {
            return $this->json($response, ['status' => false, 'msg' => 'Mesa e itens são obrigatórios.', 'id' => 0], 400);
        }

        // ── Valida a mesa ────────────────────────────────────────────────────
        $qbMesa = \App\Database\DB::select('id, status')->from('mesa');
        $mesa   = $qbMesa
            ->where('id = ' . $qbMesa->createPositionalParameter($idMesa, \Doctrine\DBAL\ParameterType::INTEGER))
            ->fetchAssociative();

        if (!$mesa) {
            return $this->json($response, ['status' => false, 'msg' => 'Mesa não encontrada.', 'id' => 0], 404);
        }

        // ── Calcula totais ───────────────────────────────────────────────────
        $total = 0.0;
        $itensNormalizados = [];
        foreach ($itens as $item) {
            $qty   = max(1, (int)   ($item['quantidade'] ?? 1));
            $preco = max(0, (float) ($item['preco']      ?? 0));
            $sub   = round($preco * $qty, 4);
            $total += $sub;
            $itensNormalizados[] = [
                'id'         => isset($item['id']) && $item['id'] !== '' ? (int) $item['id'] : null,
                'nome'       => $item['nome'] ?? 'Item sem nome',
                'preco'      => $preco,
                'quantidade' => $qty,
                'subtotal'   => $sub,
            ];
        }
        $total = round($total, 4);

        try {
            $conn = \App\Database\DB::connection();

            $ids = $conn->transactional(function (\Doctrine\DBAL\Connection $conn) use (
                $idMesa, $idCliente, $total, $itensNormalizados,
                $pagamento, $observacao, $totalParcelas, $intervalo
            ): array {

                // ── 1. order ────────────────────────────────────────────────
                $conn->insert('"order"', [
                    'id_mesa'          => $idMesa,
                    'id_cliente'       => $idCliente,
                    'payment_terms_id' => null,   // atualizado abaixo após criar payment_terms
                    'total'            => $total,
                    'status'           => 'pendente',
                    'observacao'       => $observacao,
                ]);
                $pedidoId = (int) $conn->lastInsertId();

                // ── 2. order_item (trigger → kitchen automático) ─────────────
                foreach ($itensNormalizados as $item) {
                    $conn->insert('order_item', [
                        'order_id'   => $pedidoId,
                        'product_id' => $item['id'],
                        'nome'       => $item['nome'],
                        'preco'      => $item['preco'],
                        'quantidade' => $item['quantidade'],
                        'subtotal'   => $item['subtotal'],
                    ]);
                }

                // ── 3. payment_terms — busca pelo título; cria se não existir ─
                $ptLabel = $this->normalizarPagamento($pagamento);
                $pt = \App\Database\DB::select('id')
                    ->from('payment_terms')
                    ->where('titulo = :titulo')
                    ->setParameter('titulo', $ptLabel)
                    ->fetchAssociative();

                if (!$pt) {
                    $conn->insert('payment_terms', [
                        'codigo' => strtolower(str_replace(' ', '_', $ptLabel)),
                        'titulo' => $ptLabel,
                        'atalho' => strtoupper(substr($ptLabel, 0, 3)),
                    ]);
                    $ptId = (int) $conn->lastInsertId();
                } else {
                    $ptId = (int) $pt['id'];
                }

                // ── 4. installments — N parcelas com intervalo em dias ───────
                // Cria uma linha de installment para cada parcela se não existir
                $installmentIds = [];
                $valorCentavos  = (int) round($total * 100);
                // Distribui o valor em parcelas (a última absorve o arredondamento)
                $valorParcelaCentavos = (int) floor($valorCentavos / $totalParcelas);
                $resto                = $valorCentavos - ($valorParcelaCentavos * $totalParcelas);

                for ($p = 1; $p <= $totalParcelas; $p++) {
                    $intervaloParc = $intervalo * ($p - 1);

                    $qbInst = \App\Database\DB::select('id')->from('installment');
                    $inst = $qbInst
                        ->where('id_pagamento = ' . $qbInst->createPositionalParameter($ptId, \Doctrine\DBAL\ParameterType::INTEGER))
                        ->andWhere('parcela = '   . $qbInst->createPositionalParameter($p, \Doctrine\DBAL\ParameterType::INTEGER))
                        ->andWhere('intervalo = ' . $qbInst->createPositionalParameter($intervaloParc, \Doctrine\DBAL\ParameterType::INTEGER))
                        ->fetchAssociative();

                    if (!$inst) {
                        $conn->insert('installment', [
                            'id_pagamento' => $ptId,
                            'parcela'      => $p,
                            'intervalo'    => $intervaloParc,
                        ]);
                        $installmentIds[$p] = (int) $conn->lastInsertId();
                    } else {
                        $installmentIds[$p] = (int) $inst['id'];
                    }
                }

                // ── Atualiza order.payment_terms_id ─────────────────────────
                $conn->update('"order"', ['payment_terms_id' => $ptId], ['id' => $pedidoId]);

                // ── 5. sale ─────────────────────────────────────────────────
                $conn->insert('sale', [
                    'id_cliente'    => $idCliente,
                    'total_bruto'   => $total,
                    'total_liquido' => $total,
                    'desconto'      => 0,
                    'acrescimo'     => 0,
                    'observacao'    => $observacao
                        ? "Pedido #{$pedidoId} — Mesa {$idMesa} — {$observacao}"
                        : "Pedido #{$pedidoId} — Mesa {$idMesa}",
                    'estado_venda'  => 'PRE_VENDA',
                ]);
                $saleId = (int) $conn->lastInsertId();

                // ── 6. item_sale ─────────────────────────────────────────────
                foreach ($itensNormalizados as $item) {
                    $conn->insert('item_sale', [
                        'id_venda'         => $saleId,
                        'id_produto'       => $item['id'],
                        'nome'             => $item['nome'],
                        'descricao'        => null,
                        'quantidade'       => $item['quantidade'],
                        'total_bruto'      => $item['subtotal'],
                        'unitario_bruto'   => $item['preco'],
                        'total_liquido'    => $item['subtotal'],
                        'unitario_liquido' => $item['preco'],
                        'desconto'         => 0,
                        'acrescimo'        => 0,
                    ]);
                }

                // ── 7. purchase (custo interno dos itens retirados) ──────────
                $conn->insert('purchase', [
                    'id_fornecedor' => null,
                    'total_bruto'   => $total,
                    'total_liquido' => $total,
                    'desconto'      => 0,
                    'acrescimo'     => 0,
                    'observacao'    => "Saída automática — Pedido #{$pedidoId}",
                ]);
                $purchaseId = (int) $conn->lastInsertId();

                // ── 8. item_purchase ─────────────────────────────────────────
                foreach ($itensNormalizados as $item) {
                    $conn->insert('item_purchase', [
                        'nome'           => $item['nome'],
                        'id_compra'      => $purchaseId,
                        'id_produto'     => $item['id'],
                        'quantidade'     => $item['quantidade'],
                        'total_bruto'    => $item['subtotal'],
                        'total_liquido'  => $item['subtotal'],
                        'preco_unitario' => $item['preco'],
                        'desconto'       => 0,
                        'acrescimo'      => 0,
                    ]);
                }

                // ── 9. installment_sale_purchase — uma linha por parcela ──────
                for ($p = 1; $p <= $totalParcelas; $p++) {
                    // Última parcela absorve centavos de arredondamento
                    $valorEsta = $valorParcelaCentavos + ($p === $totalParcelas ? $resto : 0);

                    // Vencimento: timestamp Unix — parcela 1 = hoje, demais = hoje + intervalo*(p-1)
                    $diasOffset     = $intervalo * ($p - 1);
                    $dataVencimento = (int) strtotime("+{$diasOffset} days", strtotime(date('Y-m-d')));

                    $conn->insert('installment_sale_purchase', [
                        'id_payment'      => $ptId,
                        'id_sale'         => $saleId,
                        'id_purchase'     => $purchaseId,
                        'id_installment'  => $installmentIds[$p],
                        'total_parcelas'  => $totalParcelas,
                        'numero_parcela'  => $p,
                        'valor_parcela'   => $valorEsta,
                        'valor_total'     => $valorCentavos,
                        'status'          => 'aberto',
                        'data_vencimento' => $dataVencimento,
                    ]);
                }

                // ── 10. mesa → ocupada ────────────────────────────────────────
                $conn->update('mesa', [
                    'status'        => 'ocupada',
                    'atualizado_em' => date('Y-m-d H:i:s'),
                ], ['id' => $idMesa]);

                return [
                    'pedido_id'   => $pedidoId,
                    'sale_id'     => $saleId,
                    'purchase_id' => $purchaseId,
                    'pt_id'       => $ptId,
                ];
            });

            return $this->json($response, [
                'status'      => true,
                'msg'         => 'Pedido enviado para a cozinha!',
                'id'          => $ids['pedido_id'],
                'sale_id'     => $ids['sale_id'],
                'purchase_id' => $ids['purchase_id'],
                'mesa'        => $idMesa,
                'total'       => $total,
            ], 201);

        } catch (\Throwable $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    // ──────────────────────────────────────────
    //  Normaliza o valor de pagamento enviado
    //  pelo cardápio para o título em payment_terms
    // ──────────────────────────────────────────
    private function normalizarPagamento(string $pagamento): string
    {
        $mapa = [
            'pix'      => 'PIX',
            'credito'  => 'Cartão de Crédito',
            'debito'   => 'Cartão de Débito',
            'dinheiro' => 'Dinheiro',
            'credit'   => 'Cartão de Crédito',
            'debit'    => 'Cartão de Débito',
            'cash'     => 'Dinheiro',
        ];
        $chave = strtolower(trim($pagamento));
        return $mapa[$chave] ?? ucfirst($pagamento);
    }

    // ──────────────────────────────────────────
    //  Atualizar status do pedido
    //  (cozinha marca: em_preparo → pronto → entregue → pago)
    //  Quando pago/cancelado, a mesa volta a "livre"
    // ──────────────────────────────────────────
    public function updateStatus($request, $response)
    {
        $form   = $request->getParsedBody();
        $id     = $form['id']     ?? null;
        $status = $form['status'] ?? null;

        $statusValidos = ['pendente', 'em_preparo', 'pronto', 'entregue', 'cancelado', 'pago'];

        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o ID do pedido', 'id' => 0], 403);
        }

        if (!in_array($status, $statusValidos, true)) {
            return $this->json($response, ['status' => false, 'msg' => 'Status inválido. Use: ' . implode(', ', $statusValidos), 'id' => 0], 422);
        }

        try {
            $conn = \App\Database\DB::connection();

            // Busca o pedido para saber a mesa
            $qb     = \App\Database\DB::select('id_mesa')->from('"order"');
            $pedido = $qb
                ->where('id = ' . $qb->createPositionalParameter((int) $id, \Doctrine\DBAL\ParameterType::INTEGER))
                ->fetchAssociative();

            if (!$pedido) {
                return $this->json($response, ['status' => false, 'msg' => 'Pedido não encontrado.', 'id' => 0], 404);
            }

            // Atualiza o status do pedido
            $updated = $conn->update('"order"', [
                'status'        => $status,
                'atualizado_em' => date('Y-m-d H:i:s'),
            ], ['id' => (int) $id]);

            if (!$updated) {
                return $this->json($response, ['status' => false, 'msg' => 'Nenhum registro alterado.', 'id' => 0], 403);
            }

            // Se pago ou cancelado → mesa fica livre
            if (in_array($status, ['pago', 'cancelado'], true)) {
                $conn->update('mesa', [
                    'status'        => 'livre',
                    'atualizado_em' => date('Y-m-d H:i:s'),
                ], ['id' => (int) $pedido['id_mesa']]);
            }

            return $this->json($response, ['status' => true, 'msg' => 'Status atualizado!', 'id' => $id], 200);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    // ──────────────────────────────────────────
    //  Retorna itens de um pedido (JSON)
    // ──────────────────────────────────────────
    public function getItens($request, $response, $args)
    {
        $id = $args['id'] ?? null;

        if (is_null($id)) {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o ID do pedido'], 403);
        }

        try {
            $itens = \App\Database\DB::select('*')
                ->from('order_item')
                ->where('order_id = :id')
                ->setParameter('id', (int) $id, \Doctrine\DBAL\ParameterType::INTEGER)
                ->fetchAllAssociative();

            return $this->json($response, ['status' => true, 'itens' => $itens], 200);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage()], 500);
        }
    }

    // ──────────────────────────────────────────
    //  Cancelar pedido
    // ──────────────────────────────────────────
    public function delete($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o código do pedido', 'id' => 0], 403);
        }

        try {
            $conn = \App\Database\DB::connection();

            // Busca a mesa antes de cancelar
            $qb     = \App\Database\DB::select('id_mesa, status')->from('"order"');
            $pedido = $qb
                ->where('id = ' . $qb->createPositionalParameter((int) $id, \Doctrine\DBAL\ParameterType::INTEGER))
                ->fetchAssociative();

            // Marca como cancelado (não remove do banco — boa prática)
            $updated = $conn->update('"order"', [
                'status'        => 'cancelado',
                'atualizado_em' => date('Y-m-d H:i:s'),
            ], ['id' => (int) $id]);

            if (!$updated) {
                return $this->json($response, ['status' => false, 'msg' => 'Nenhum registro alterado.', 'id' => $id], 403);
            }

            // Libera a mesa se o pedido era o único pendente
            if ($pedido) {
                $conn->update('mesa', [
                    'status'        => 'livre',
                    'atualizado_em' => date('Y-m-d H:i:s'),
                ], ['id' => (int) $pedido['id_mesa']]);
            }

            return $this->json($response, ['status' => true, 'msg' => 'Pedido cancelado com sucesso!', 'id' => $id]);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    // ──────────────────────────────────────────
    //  DataTables — lista paginada de pedidos
    // ──────────────────────────────────────────
    public function listingdata($request, $response)
    {
        $form   = $request->getParsedBody();
        $term   = $form['search']['value'] ?? null;
        $start  = (int) ($form['start']  ?? 0);
        $length = (int) ($form['length'] ?? 10);

        $columns = [
            0 => 'o.id',
            1 => 'm.numero',
            2 => 'o.total',
            3 => 'o.status',
            4 => 'o.criado_em',
            5 => 'o.atualizado_em',
        ];

        $posField   = (isset($form['order'][0]['column']) && isset($columns[(int) $form['order'][0]['column']]))
            ? (int) $form['order'][0]['column']
            : 0;
        $orderType  = strtoupper($form['order'][0]['dir'] ?? 'DESC');
        $orderType  = in_array($orderType, ['ASC', 'DESC'], true) ? $orderType : 'DESC';
        $orderField = $columns[$posField];

        try {
            $totalRecords = (int) \App\Database\DB::select('COUNT(*)')
                ->from('"order"')
                ->fetchOne();

            $query = \App\Database\DB::select("
                o.id,
                m.numero                                              AS mesa_numero,
                o.total,
                o.status,
                o.observacao,
                to_char(o.criado_em,     'DD/MM/YYYY HH24:MI:SS')   AS criado_em,
                to_char(o.atualizado_em, 'DD/MM/YYYY HH24:MI:SS')   AS atualizado_em
            ")
            ->from('"order"', 'o')
            ->leftJoin('o', 'mesa', 'm', 'o.id_mesa = m.id');

            if (!is_null($term) && $term !== '') {
                $query->setParameter('term', '%' . $term . '%');
                $query->where('CAST(o.id AS TEXT) ILIKE :term')
                    ->orWhere('CAST(m.numero AS TEXT) ILIKE :term')
                    ->orWhere('o.status ILIKE :term');
            }

            $filteredRecords = (int) (clone $query)->select('COUNT(*)')->fetchOne();

            $pedidos = $query
                ->orderBy($orderField, $orderType)
                ->setFirstResult($start)
                ->setMaxResults($length)
                ->fetchAllAssociative();

            $rows = [];
            foreach ($pedidos as $key => $value) {
                $statusBadge = match ($value['status']) {
                    'pendente'   => "<span class='badge bg-warning text-dark'>Pendente</span>",
                    'em_preparo' => "<span class='badge bg-info text-dark'>Em preparo</span>",
                    'pronto'     => "<span class='badge bg-primary'>Pronto</span>",
                    'entregue'   => "<span class='badge bg-success'>Entregue</span>",
                    'pago'       => "<span class='badge bg-success'>Pago</span>",
                    'cancelado'  => "<span class='badge bg-danger'>Cancelado</span>",
                    default      => $value['status'],
                };

                $rows[$key] = [
                    $value['id'],
                    'Mesa ' . $value['mesa_numero'],
                    'R$ ' . number_format((float) $value['total'], 2, ',', '.'),
                    $statusBadge,
                    $value['criado_em'],
                    $value['atualizado_em'],
                    "<td>
                    <a class='btn btn-sm btn-warning' href='/pedido/detalhes/" . $value['id'] . "'><i class='fa-solid fa-pen-to-square'></i> Ver</a>
                    <button type='button' class='btn btn-sm btn-danger' onclick='ShowModal(" . $value['id'] . ");'><i class='fa-solid fa-trash'></i> Cancelar</button>
                </td>",
                ];
            }

            return $this->json($response, [
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data'            => $rows,
            ], 200);
        } catch (\Exception $e) {
            return $this->json($response, [
                'status' => false,
                'msg'    => 'Erro: ' . $e->getMessage(),
                'id'     => 0,
            ], 500);
        }
    }
}