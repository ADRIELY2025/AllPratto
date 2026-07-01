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

    // ──────────────────────────────────────────────────────────────────────────
    //  Página: formulário de Pedido Virtual (iFood / Telefone)
    // ──────────────────────────────────────────────────────────────────────────
    public function virtual($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('pedido-virtual'), [
                'titulo' => 'Novo Pedido Virtual',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  API: Inserir Pedido Virtual (sem mesa, com cliente + endereço)
    // ──────────────────────────────────────────────────────────────────────────
    public function insertVirtual($request, $response)
    {
        $form = $request->getParsedBody();

        $idCliente = null;
        if (isset($form['id_cliente']) && $form['id_cliente'] !== '') {
            $idCliente = filter_var($form['id_cliente'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        }

        $idPaymentTerms = null;
        if (isset($form['id_payment_terms']) && $form['id_payment_terms'] !== '') {
            $idPaymentTerms = filter_var($form['id_payment_terms'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        }
        $itensJson   = $form['itens'] ?? '[]';
        $pagamento   = trim((string) ($form['pagamento'] ?? 'dinheiro'));
        $observacao  = $form['observacao'] ?? null;
        $tipoEntrega = $form['tipo_entrega'] ?? 'delivery';
        $taxaEntrega = isset($form['taxa_entrega'])
            ? round((float) str_replace(',', '.', (string) $form['taxa_entrega']), 4)
            : 0.0;
        $totalParcelas = isset($form['parcelas']) && (int) $form['parcelas'] >= 1
            ? (int) $form['parcelas']
            : (isset($form['num_parcelas']) && (int) $form['num_parcelas'] >= 1 ? (int) $form['num_parcelas'] : 1);
        $intervalo = isset($form['intervalo']) && (int) $form['intervalo'] >= 0
            ? (int) $form['intervalo']
            : (($pagamento === 'credito' && $totalParcelas > 1) ? 30 : 0);
        $enderecoData = [
            'logradouro'  => trim((string) ($form['endereco_rua'] ?? '')),
            'numero'      => trim((string) ($form['endereco_numero'] ?? '')),
            'complemento' => trim((string) ($form['endereco_complemento'] ?? '')),
            'bairro'      => trim((string) ($form['endereco_bairro'] ?? '')),
            'cidade'      => trim((string) ($form['endereco_cidade'] ?? '')),
            'cep'         => trim((string) ($form['endereco_cep'] ?? '')),
            'referencia'  => trim((string) ($form['endereco_referencia'] ?? '')),
        ];

        if (!$idCliente) {
            return $this->json($response, ['status' => false, 'msg' => 'Cliente é obrigatório para pedido virtual.', 'id' => 0], 400);
        }

        $qbCliente = \App\Database\DB::select('id')->from('customer');
        $cliente   = $qbCliente
            ->where('id = ' . $qbCliente->createPositionalParameter($idCliente, \Doctrine\DBAL\ParameterType::INTEGER))
            ->fetchAssociative();

        if (!$cliente) {
            return $this->json($response, ['status' => false, 'msg' => 'Cliente não encontrado.', 'id' => 0], 404);
        }

        if ($idPaymentTerms) {
            $qbPaymentTerm = \App\Database\DB::select('id')->from('payment_terms');
            $paymentTermExists = $qbPaymentTerm
                ->where('id = ' . $qbPaymentTerm->createPositionalParameter($idPaymentTerms, \Doctrine\DBAL\ParameterType::INTEGER))
                ->fetchOne();

            if (!$paymentTermExists) {
                return $this->json($response, ['status' => false, 'msg' => 'Condição de pagamento não encontrada.', 'id' => 0], 400);
            }
        }

        $itens = json_decode($itensJson, true);
        if (!is_array($itens) || empty($itens)) {
            return $this->json($response, ['status' => false, 'msg' => 'Adicione ao menos um item ao pedido.', 'id' => 0], 400);
        }

        $subtotal          = 0.0;
        $itensNormalizados = [];
        foreach ($itens as $item) {
            $qty   = max(0.01, (float) ($item['quantidade'] ?? 1));
            $preco = max(0,    (float) ($item['preco']      ?? 0));
            $sub   = round($preco * $qty, 4);
            $subtotal += $sub;
            $itensNormalizados[] = [
                'id'         => isset($item['id']) && $item['id'] !== '' ? (int) $item['id'] : null,
                'nome'       => $item['nome'] ?? 'Item sem nome',
                'preco'      => $preco,
                'quantidade' => $qty,
                'subtotal'   => $sub,
            ];
        }
        $subtotal    = round($subtotal, 4);
        $taxaEntrega = round($taxaEntrega, 4);
        $total       = round($subtotal + $taxaEntrega, 4);

        $prefixoObs = '[' . strtoupper($tipoEntrega) . ']';
        if ($taxaEntrega > 0) {
            $prefixoObs .= ' | Taxa: R$ ' . number_format($taxaEntrega, 2, ',', '.');
        }

        $observacaoFinal = $prefixoObs . ($observacao ? ' | ' . $observacao : '');

        if ($tipoEntrega === 'delivery' && $enderecoData['logradouro'] !== '') {
            $enderecoTexto = trim(implode(' — ', array_filter([
                $enderecoData['logradouro'],
                $enderecoData['numero'],
                $enderecoData['complemento'] ? 'Comp. ' . $enderecoData['complemento'] : null,
                $enderecoData['bairro'],
                $enderecoData['cidade'],
                $enderecoData['cep'] ? 'CEP ' . $enderecoData['cep'] : null,
                $enderecoData['referencia'] ? 'Ref.: ' . $enderecoData['referencia'] : null,
            ], static fn ($v) => $v !== null && $v !== '')));
            if ($enderecoTexto !== '') {
                $observacaoFinal = $observacaoFinal . ' | Endereço: ' . $enderecoTexto;
            }
        }

        try {
            $conn = \App\Database\DB::connection();

            $ids = $conn->transactional(function (\Doctrine\DBAL\Connection $conn) use (
                $idCliente, $total, $itensNormalizados, $pagamento, $observacaoFinal,
                $totalParcelas, $intervalo, $tipoEntrega, $enderecoData, $idPaymentTerms
            ): array {

                // 1. order — id_mesa NULL (pedido virtual)
                $conn->insert('"order"', [
                    'id_mesa'          => null,
                    'id_cliente'       => $idCliente,
                    'payment_terms_id' => null,
                    'total'            => $total,
                    'status'           => 'pendente',
                    'observacao'       => $observacaoFinal,
                ]);
                $pedidoId = (int) $conn->lastInsertId();

                // 2. order_item (trigger → kitchen)
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

                // 3. payment_terms
                if ($idPaymentTerms) {
                    $qbPayment = \App\Database\DB::select('id, titulo, codigo')
                        ->from('payment_terms');
                    $pt = $qbPayment
                        ->where('id = ' . $qbPayment->createPositionalParameter($idPaymentTerms, \Doctrine\DBAL\ParameterType::INTEGER))
                        ->fetchAssociative();

                    if (!$pt) {
                        throw new \RuntimeException('Condição de pagamento inválida.');
                    }
                } else {
                    $ptLabel = $this->normalizarPagamento($pagamento);
                    $qbPayment = \App\Database\DB::select('id, titulo, codigo')
                        ->from('payment_terms');
                    $pt = $qbPayment
                        ->where('LOWER(titulo) = LOWER(:titulo)')
                        ->setParameter('titulo', $ptLabel)
                        ->fetchAssociative();
                }

                if (!$pt) {
                    $codigo = strtolower(str_replace(' ', '_', $this->normalizarPagamento($pagamento)));
                    $titulo = $this->normalizarPagamento($pagamento);
                    $conn->insert('payment_terms', [
                        'codigo' => $codigo,
                        'titulo' => $titulo,
                        'atalho' => strtoupper(substr($titulo, 0, 3)),
                    ]);
                    $ptId = (int) $conn->lastInsertId();
                } else {
                    $ptId = (int) $pt['id'];
                }

                // 4. installments — N parcelas (crédito) ou 1x à vista
                $installmentIds       = [];
                $valorCentavos        = (int) round($total * 100);
                $valorParcelaCentavos = (int) floor($valorCentavos / $totalParcelas);
                $resto                = $valorCentavos - ($valorParcelaCentavos * $totalParcelas);

                for ($p = 1; $p <= $totalParcelas; $p++) {
                    $intervaloParc = $intervalo * ($p - 1);
                    $qbInst = \App\Database\DB::select('id')->from('installment');
                    $inst   = $qbInst
                        ->where('id_pagamento = ' . $qbInst->createPositionalParameter($ptId, \Doctrine\DBAL\ParameterType::INTEGER))
                        ->andWhere('parcela = '   . $qbInst->createPositionalParameter($p, \Doctrine\DBAL\ParameterType::INTEGER))
                        ->andWhere('intervalo = ' . $qbInst->createPositionalParameter($intervaloParc, \Doctrine\DBAL\ParameterType::INTEGER))
                        ->fetchAssociative();

                    if (!$inst) {
                        $conn->insert('installment', ['id_pagamento' => $ptId, 'parcela' => $p, 'intervalo' => $intervaloParc]);
                        $installmentIds[$p] = (int) $conn->lastInsertId();
                    } else {
                        $installmentIds[$p] = (int) $inst['id'];
                    }
                }

                $conn->update('"order"', ['payment_terms_id' => $ptId], ['id' => $pedidoId]);

                $temEndereco = !empty(array_filter($enderecoData, static fn ($value) => trim((string) $value) !== ''));
                if ($tipoEntrega === 'delivery' && $temEndereco) {
                    $conn->update('"order"', [
                        'endereco_logradouro'  => $enderecoData['logradouro'],
                        'endereco_numero'      => $enderecoData['numero'],
                        'endereco_complemento' => $enderecoData['complemento'],
                        'endereco_bairro'      => $enderecoData['bairro'],
                        'endereco_cidade'      => $enderecoData['cidade'],
                        'endereco_cep'         => $enderecoData['cep'],
                        'endereco_referencia'  => $enderecoData['referencia'],
                    ], ['id' => $pedidoId]);
                }

                // 5. sale
                $conn->insert('sale', [
                    'id_cliente'    => $idCliente,
                    'total_bruto'   => $total,
                    'total_liquido' => $total,
                    'desconto'      => 0,
                    'acrescimo'     => 0,
                    'observacao'    => "Pedido Virtual #{$pedidoId} | {$observacaoFinal}",
                    'estado_venda'  => 'PRE_VENDA',
                ]);
                $saleId = (int) $conn->lastInsertId();

                // 6. item_sale
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

                // 7. purchase
                $conn->insert('purchase', [
                    'id_fornecedor' => null,
                    'total_bruto'   => $total,
                    'total_liquido' => $total,
                    'desconto'      => 0,
                    'acrescimo'     => 0,
                    'observacao'    => "Saída automática — Pedido Virtual #{$pedidoId}",
                ]);
                $purchaseId = (int) $conn->lastInsertId();

                // 8. item_purchase
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

                // 9. installment_sale_purchase — uma linha por parcela
                for ($p = 1; $p <= $totalParcelas; $p++) {
                    $valorEsta      = $valorParcelaCentavos + ($p === $totalParcelas ? $resto : 0);
                    $diasOffset     = $intervalo * ($p - 1);
                    $dataVencimento = date('Y-m-d', strtotime("+{$diasOffset} days"));

                    $conn->insert('installment_sale_purchase', [
                        'id_payment'      => $ptId,
                        'id_sale'         => $saleId,
                        'id_purchase'     => null,
                        'id_installment'  => $installmentIds[$p],
                        'total_parcelas'  => $totalParcelas,
                        'numero_parcela'  => $p,
                        'valor_parcela'   => $valorEsta,
                        'valor_total'     => $valorCentavos,
                        'status'          => 'aberto',
                        'data_vencimento' => $dataVencimento,
                    ]);
                }

                return [
                    'pedido_id'   => $pedidoId,
                    'sale_id'     => $saleId,
                    'purchase_id' => $purchaseId,
                ];
            });

            return $this->json($response, [
                'status'      => true,
                'msg'         => 'Pedido virtual enviado para a cozinha!',
                'id'          => $ids['pedido_id'],
                'sale_id'     => $ids['sale_id'],
                'purchase_id' => $ids['purchase_id'],
                'total'       => $total,
            ], 201);

        } catch (\Throwable $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function listarCozinha($request, $response)
    {
        try {
            $pedidos = \App\Database\DB::select("
                o.id,
                o.id_mesa,
                o.id_cliente,
                o.total,
                o.status,
                o.observacao,
                o.criado_em,
                m.numero AS mesa_numero,
                TRIM(COALESCE(c.nome_fantasia,'') || ' ' || COALESCE(c.sobrenome_razao,'')) AS nome_cliente
            ")
            ->from('"order"', 'o')
            ->leftJoin('o', 'mesa', 'm', 'm.id = o.id_mesa')
            ->leftJoin('o', 'customer', 'c', 'c.id = o.id_cliente')
            ->where("o.status IN ('pendente','em_preparo','pronto')")
            ->orderBy('o.criado_em', 'ASC')
            ->fetchAllAssociative();

            foreach ($pedidos as &$pedido) {
                $itens = \App\Database\DB::select("
                    oi.nome,
                    oi.quantidade,
                    oi.preco,
                    oi.subtotal,
                    CASE WHEN k.status = 'Cancelled' THEN 'cancelado' ELSE 'ativo' END AS status
                ")
                ->from('order_item', 'oi')
                ->leftJoin('oi', 'kitchen', 'k', 'k.order_item_id = oi.id')
                ->where('oi.order_id = :id')
                ->setParameter('id', $pedido['id'])
                ->fetchAllAssociative();

                $pedido['itens'] = $itens;
                // Garante que nome_cliente seja null se vazio
                if (empty(trim($pedido['nome_cliente'] ?? ''))) {
                    $pedido['nome_cliente'] = null;
                }
            }

            return $this->json($response, [
                'status'  => true,
                'pedidos' => $pedidos,
            ]);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    public function list($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('list-pedido'), [
                'titulo' => 'Lista de pedidos',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

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

    public function insert($request, $response)
    {
        $form       = $request->getParsedBody();
        $idMesa     = isset($form['mesa'])       ? (int) $form['mesa']    : null;
        $itens      = $form['itens']             ?? [];
        $pagamento  = trim((string) ($form['pagamento']  ?? 'dinheiro'));
        $observacao = $form['observacao']        ?? null;
        $idCliente  = isset($form['id_cliente']) && $form['id_cliente'] !== ''
            ? (int) $form['id_cliente'] : null;

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
        $total             = 0.0;
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
                $installmentIds       = [];
                $valorCentavos        = (int) round($total * 100);
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
                //
                //  IMPORTANTE: id_purchase fica NULL aqui intencionalmente.
                //  A constraint chk_isp_sale_or_purchase exige que a parcela
                //  pertença a UMA venda OU UMA compra, nunca aos dois.
                //  O purchase acima é um controle de custo/estoque interno —
                //  não é uma obrigação financeira parcelável.
                //
                for ($p = 1; $p <= $totalParcelas; $p++) {
                    $valorEsta = $valorParcelaCentavos + ($p === $totalParcelas ? $resto : 0);

                    $diasOffset     = $intervalo * ($p - 1);
                    $dataVencimento = date('Y-m-d', strtotime("+{$diasOffset} days"));

                    $conn->insert('installment_sale_purchase', [
                        'id_payment'      => $ptId,
                        'id_sale'         => $saleId,
                        'id_purchase'     => null,          // ← NULL: parcela é da venda
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

    public function cancelarItem($request, $response)
    {
        $form        = $request->getParsedBody();
        $orderItemId = $form['order_item_id'] ?? null;

        if (is_null($orderItemId) || $orderItemId === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o item do pedido.'], 403);
        }

        try {
            $conn = \App\Database\DB::connection();

            $qbItem = \App\Database\DB::select('id, order_id')->from('order_item');
            $item   = $qbItem
                ->where('id = ' . $qbItem->createPositionalParameter((int) $orderItemId, \Doctrine\DBAL\ParameterType::INTEGER))
                ->fetchAssociative();

            if (!$item) {
                return $this->json($response, ['status' => false, 'msg' => 'Item não encontrado.'], 404);
            }

            $qbKitchen = \App\Database\DB::select('id, status')->from('kitchen');
            $kitchen   = $qbKitchen
                ->where('order_item_id = ' . $qbKitchen->createPositionalParameter((int) $orderItemId, \Doctrine\DBAL\ParameterType::INTEGER))
                ->fetchAssociative();

            if ($kitchen && $kitchen['status'] === 'Cancelled') {
                return $this->json($response, ['status' => false, 'msg' => 'Item já está cancelado.'], 422);
            }

            if ($kitchen && in_array($kitchen['status'], ['Ready', 'Delivered'], true)) {
                return $this->json($response, [
                    'status' => false,
                    'msg'    => 'Este item já está pronto e não pode mais ser cancelado.',
                ], 422);
            }

            $qbPedido = \App\Database\DB::select('id, status')->from('"order"');
            $pedido   = $qbPedido
                ->where('id = ' . $qbPedido->createPositionalParameter((int) $item['order_id'], \Doctrine\DBAL\ParameterType::INTEGER))
                ->fetchAssociative();

            if (!$pedido) {
                return $this->json($response, ['status' => false, 'msg' => 'Pedido não encontrado.'], 404);
            }

            $statusFinais = ['pronto', 'entregue', 'pago', 'cancelado'];
            if (in_array($pedido['status'], $statusFinais, true)) {
                return $this->json($response, [
                    'status' => false,
                    'msg'    => 'Pedido com status "' . $pedido['status'] . '" não pode mais ser editado.',
                ], 422);
            }

            $conn->transactional(function (\Doctrine\DBAL\Connection $conn) use ($orderItemId, $pedido): void {
                $conn->update('kitchen', [
                    'status'     => 'Cancelled',
                    'updated_at' => date('Y-m-d H:i:s'),
                ], ['order_item_id' => (int) $orderItemId]);

                $totalAtivo = (float) \App\Database\DB::select('COALESCE(SUM(oi.subtotal), 0)')
                    ->from('order_item', 'oi')
                    ->leftJoin('oi', 'kitchen', 'k', 'k.order_item_id = oi.id')
                    ->where('oi.order_id = :id')
                    ->andWhere("COALESCE(k.status, 'Awaiting') != 'Cancelled'")
                    ->setParameter('id', (int) $pedido['id'])
                    ->fetchOne();

                $conn->update('"order"', [
                    'total'         => $totalAtivo,
                    'atualizado_em' => date('Y-m-d H:i:s'),
                ], ['id' => (int) $pedido['id']]);
            });

            return $this->json($response, ['status' => true, 'msg' => 'Item cancelado. A cozinha foi avisada.']);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage()], 500);
        }
    }

    public function adicionarItem($request, $response)
    {
        $form      = $request->getParsedBody();
        $pedidoId  = $form['order_id']    ?? null;
        $produtoId = $form['product_id']  ?? null;
        $nome      = trim((string) ($form['nome'] ?? ''));
        $preco     = max(0, (float) ($form['preco'] ?? 0));
        $qtd       = max(1, (int) ($form['quantidade'] ?? 1));

        if (is_null($pedidoId) || $pedidoId === '' || $nome === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o pedido e o produto.'], 403);
        }

        try {
            $conn = \App\Database\DB::connection();

            $qbPedido = \App\Database\DB::select('id, status')->from('"order"');
            $pedido   = $qbPedido
                ->where('id = ' . $qbPedido->createPositionalParameter((int) $pedidoId, \Doctrine\DBAL\ParameterType::INTEGER))
                ->fetchAssociative();

            if (!$pedido) {
                return $this->json($response, ['status' => false, 'msg' => 'Pedido não encontrado.'], 404);
            }

            $statusFinais = ['pronto', 'entregue', 'pago', 'cancelado'];
            if (in_array($pedido['status'], $statusFinais, true)) {
                return $this->json($response, [
                    'status' => false,
                    'msg'    => 'Pedido com status "' . $pedido['status'] . '" não pode mais ser editado.',
                ], 422);
            }

            $subtotal = round($preco * $qtd, 4);

            $conn->transactional(function (\Doctrine\DBAL\Connection $conn) use ($pedido, $produtoId, $nome, $preco, $qtd, $subtotal): void {
                $conn->insert('order_item', [
                    'order_id'   => (int) $pedido['id'],
                    'product_id' => $produtoId !== '' && !is_null($produtoId) ? (int) $produtoId : null,
                    'nome'       => $nome,
                    'preco'      => $preco,
                    'quantidade' => $qtd,
                    'subtotal'   => $subtotal,
                ]);

                $totalAtivo = (float) \App\Database\DB::select('COALESCE(SUM(oi.subtotal), 0)')
                    ->from('order_item', 'oi')
                    ->leftJoin('oi', 'kitchen', 'k', 'k.order_item_id = oi.id')
                    ->where('oi.order_id = :id')
                    ->andWhere("COALESCE(k.status, 'Awaiting') != 'Cancelled'")
                    ->setParameter('id', (int) $pedido['id'])
                    ->fetchOne();

                $conn->update('"order"', [
                    'total'         => $totalAtivo,
                    'atualizado_em' => date('Y-m-d H:i:s'),
                ], ['id' => (int) $pedido['id']]);
            });

            return $this->json($response, ['status' => true, 'msg' => 'Item adicionado ao pedido!'], 201);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage()], 500);
        }
    }

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

            $qb     = \App\Database\DB::select('id_mesa')->from('"order"');
            $pedido = $qb
                ->where('id = ' . $qb->createPositionalParameter((int) $id, \Doctrine\DBAL\ParameterType::INTEGER))
                ->fetchAssociative();

            if (!$pedido) {
                return $this->json($response, ['status' => false, 'msg' => 'Pedido não encontrado.', 'id' => 0], 404);
            }

            $updated = $conn->update('"order"', [
                'status'        => $status,
                'atualizado_em' => date('Y-m-d H:i:s'),
            ], ['id' => (int) $id]);

            if (!$updated) {
                return $this->json($response, ['status' => false, 'msg' => 'Nenhum registro alterado.', 'id' => 0], 403);
            }

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

    public function getItens($request, $response, $args)
    {
        $id = $args['id'] ?? null;

        if (is_null($id)) {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o ID do pedido'], 403);
        }

        try {
            $itens = \App\Database\DB::select("
                oi.id,
                oi.nome,
                oi.preco,
                oi.quantidade,
                oi.subtotal,
                CASE WHEN k.status = 'Cancelled' THEN 'cancelado' ELSE 'ativo' END AS status
            ")
                ->from('order_item', 'oi')
                ->leftJoin('oi', 'kitchen', 'k', 'k.order_item_id = oi.id')
                ->where('oi.order_id = :id')
                ->setParameter('id', (int) $id, \Doctrine\DBAL\ParameterType::INTEGER)
                ->orderBy('oi.id', 'ASC')
                ->fetchAllAssociative();

            return $this->json($response, ['status' => true, 'itens' => $itens], 200);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage()], 500);
        }
    }

    public function delete($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o código do pedido', 'id' => 0], 403);
        }

        try {
            $conn = \App\Database\DB::connection();

            $qb     = \App\Database\DB::select('id_mesa, status')->from('"order"');
            $pedido = $qb
                ->where('id = ' . $qb->createPositionalParameter((int) $id, \Doctrine\DBAL\ParameterType::INTEGER))
                ->fetchAssociative();

            // Bloqueia cancelamento de pedidos em status final
            if ($pedido && in_array($pedido['status'], ['pronto', 'pago', 'entregue', 'cancelado'], true)) {
                return $this->json($response, [
                    'status' => false,
                    'msg'    => 'Pedido com status "' . $pedido['status'] . '" não pode ser cancelado.',
                    'id'     => $id,
                ], 422);
            }

            $updated = $conn->update('"order"', [
                'status'        => 'cancelado',
                'atualizado_em' => date('Y-m-d H:i:s'),
            ], ['id' => (int) $id]);

            if (!$updated) {
                return $this->json($response, ['status' => false, 'msg' => 'Nenhum registro alterado.', 'id' => $id], 403);
            }

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

                // Botões bloqueados para status finais
                $statusFinal = in_array($value['status'], ['pronto', 'pago', 'entregue', 'cancelado'], true);

                $btnVer = $statusFinal
                    ? "<a class='btn btn-sm btn-secondary disabled' aria-disabled='true' title='Pedido finalizado'><i class='fa-solid fa-lock'></i> Ver</a>"
                    : "<a class='btn btn-sm btn-warning' href='/pedido/detalhes/{$value['id']}'><i class='fa-solid fa-pen-to-square'></i> Ver</a>";

                $btnCancelar = $statusFinal
                    ? "<button type='button' class='btn btn-sm btn-secondary' disabled title='Pedido finalizado'><i class='fa-solid fa-ban'></i> Cancelar</button>"
                    : "<button type='button' class='btn btn-sm btn-danger' onclick='ShowModal({$value['id']});'><i class='fa-solid fa-trash'></i> Cancelar</button>";

                $rows[$key] = [
                    $value['id'],
                    $value['mesa_numero'] ? 'Mesa ' . $value['mesa_numero'] : '<span class="badge bg-info text-dark"><i class="fa-solid fa-motorcycle me-1"></i> Pedido Virtual</span>',
                    'R$ ' . number_format((float) $value['total'], 2, ',', '.'),
                    $statusBadge,
                    $value['criado_em'],
                    $value['atualizado_em'],
                    "<td class='d-flex gap-1'>{$btnVer} {$btnCancelar}</td>",
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