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

                // 7. installment_sale — uma linha por parcela
                for ($p = 1; $p <= $totalParcelas; $p++) {
                    $valorEsta      = $valorParcelaCentavos + ($p === $totalParcelas ? $resto : 0);
                    $diasOffset     = $intervalo * ($p - 1);
                    $dataVencimento = date('Y-m-d', strtotime("+{$diasOffset} days"));

                    $conn->insert('installment_sale', [
                        'id_payment'      => $ptId,
                        'id_sale'         => $saleId,
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
                    'pedido_id' => $pedidoId,
                    'sale_id'   => $saleId,
                ];
            });

            return $this->json($response, [
                'status'  => true,
                'msg'     => 'Pedido virtual enviado para a cozinha!',
                'id'      => $ids['pedido_id'],
                'sale_id' => $ids['sale_id'],
                'total'   => $total,
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
                    nome,
                    quantidade,
                    preco,
                    subtotal,
                    status
                ")
                ->from('order_item')
                ->where('order_id = :id')
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

                // ── 7. installment_sale — uma linha por parcela ───────────────
                for ($p = 1; $p <= $totalParcelas; $p++) {
                    $valorEsta = $valorParcelaCentavos + ($p === $totalParcelas ? $resto : 0);

                    $diasOffset     = $intervalo * ($p - 1);
                    $dataVencimento = date('Y-m-d', strtotime("+{$diasOffset} days"));

                    $conn->insert('installment_sale', [
                        'id_payment'      => $ptId,
                        'id_sale'         => $saleId,
                        'id_installment'  => $installmentIds[$p],
                        'total_parcelas'  => $totalParcelas,
                        'numero_parcela'  => $p,
                        'valor_parcela'   => $valorEsta,
                        'valor_total'     => $valorCentavos,
                        'status'          => 'aberto',
                        'data_vencimento' => $dataVencimento,
                    ]);
                }

                // ── 8. mesa → ocupada ─────────────────────────────────────────
                $conn->update('mesa', [
                    'status'        => 'ocupada',
                    'atualizado_em' => date('Y-m-d H:i:s'),
                ], ['id' => $idMesa]);

                return [
                    'pedido_id' => $pedidoId,
                    'sale_id'   => $saleId,
                    'pt_id'     => $ptId,
                ];
            });

            return $this->json($response, [
                'status'  => true,
                'msg'     => 'Pedido enviado para a cozinha!',
                'id'      => $ids['pedido_id'],
                'sale_id' => $ids['sale_id'],
                'mesa'    => $idMesa,
                'total'   => $total,
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

            $qbItem = \App\Database\DB::select('id, order_id, status')->from('order_item');
            $item   = $qbItem
                ->where('id = ' . $qbItem->createPositionalParameter((int) $orderItemId, \Doctrine\DBAL\ParameterType::INTEGER))
                ->fetchAssociative();

            if (!$item) {
                return $this->json($response, ['status' => false, 'msg' => 'Item não encontrado.'], 404);
            }

            if ($item['status'] === 'cancelado') {
                return $this->json($response, ['status' => false, 'msg' => 'Item já está cancelado.'], 422);
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
                $conn->update('order_item', ['status' => 'cancelado'], ['id' => (int) $orderItemId]);

                $totalAtivo = (float) \App\Database\DB::select('COALESCE(SUM(subtotal), 0)')
                    ->from('order_item')
                    ->where('order_id = :id')
                    ->andWhere("status = 'ativo'")
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

                $totalAtivo = (float) \App\Database\DB::select('COALESCE(SUM(subtotal), 0)')
                    ->from('order_item')
                    ->where('order_id = :id')
                    ->andWhere("status = 'ativo'")
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
            $itens = \App\Database\DB::select('id, nome, preco, quantidade, subtotal, status')
                ->from('order_item')
                ->where('order_id = :id')
                ->setParameter('id', (int) $id, \Doctrine\DBAL\ParameterType::INTEGER)
                ->orderBy('id', 'ASC')
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
                    "<td>
                    <a class='btn btn-sm btn-warning' href='/pedido/detalhes/{$value['id']}'><i class='fa-solid fa-pen-to-square'></i> Ver</a>
                    <button type='button' class='btn btn-sm btn-danger' onclick='ShowModal({$value['id']});'><i class='fa-solid fa-trash'></i> Cancelar</button>
                    
                    <a class='btn btn-sm btn-primary' target='_blank' href='/pedido/imprimir/{$value['id']}'><i class='fa-solid fa-print'></i> Imprimir</a>
                </td>",
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

    public function imprimir($request, $response, $args)
{
    $id = $args['id'] ?? null;

    if (is_null($id) || $id === '') {
        return $this->json($response, ['status' => false, 'msg' => 'Informe o código do Pedido', 'id' => 0], 403);
    }

    try {
        // ── Dados da empresa (cabeçalho do comprovante) ──────────────────
        $empresa = \App\Database\DB::select('nome, razao_social, cnpj, telefone, email')
            ->from('company')
            ->where('ativo = true')
            ->orderBy('id', 'ASC')
            ->setMaxResults(1)
            ->fetchAssociative();

        // ── Pedido + mesa + cliente + forma de pagamento ─────────────────
        $qb = \App\Database\DB::select("
            o.id,
            o.total,
            o.status,
            o.observacao,
            o.id_mesa,
            o.endereco_logradouro,
            o.endereco_numero,
            o.endereco_complemento,
            o.endereco_bairro,
            o.endereco_cidade,
            o.endereco_cep,
            o.endereco_referencia,
            m.numero  AS mesa_numero,
            TRIM(COALESCE(c.nome_fantasia,'') || ' ' || COALESCE(c.sobrenome_razao,'')) AS nome_cliente,
            pt.titulo AS forma_pagamento,
            to_char(o.criado_em,     'DD/MM/YYYY HH24:MI:SS') AS criado_em,
            to_char(o.atualizado_em, 'DD/MM/YYYY HH24:MI:SS') AS atualizado_em
        ")
        ->from('"order"', 'o')
        ->leftJoin('o', 'mesa', 'm', 'm.id = o.id_mesa')
        ->leftJoin('o', 'customer', 'c', 'c.id = o.id_cliente')
        ->leftJoin('o', 'payment_terms', 'pt', 'pt.id = o.payment_terms_id');

        $pedido = $qb
            ->where('o.id = ' . $qb->createPositionalParameter((int) $id, \Doctrine\DBAL\ParameterType::INTEGER))
            ->fetchAssociative();

        if (!$pedido) {
            return $this->json($response, ['status' => false, 'msg' => 'Pedido não encontrado', 'id' => 0], 404);
        }

        // ── Itens do pedido (apenas os ativos — cancelados não entram) ───
        $itens = \App\Database\DB::select('nome, quantidade, preco, subtotal')
            ->from('order_item')
            ->where('order_id = :id')
            ->andWhere("status = 'ativo'")
            ->setParameter('id', (int) $id, \Doctrine\DBAL\ParameterType::INTEGER)
            ->orderBy('id', 'ASC')
            ->fetchAllAssociative();

        // ── Monta o PDF ────────────────────────────────────────────────────
        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 20);

        $t = fn(string $s) => iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);

        // ── Cabeçalho (dados da empresa) ──────────────────────────────────
        $nomeEmpresa = $empresa['nome'] ?? 'Restaurante';
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 8, $t($nomeEmpresa), 0, 1, 'C');

        $linhaContato = array_filter([
            !empty($empresa['cnpj'])     ? 'CNPJ: ' . $empresa['cnpj']         : null,
            !empty($empresa['telefone']) ? 'Tel.: ' . $empresa['telefone']     : null,
        ]);
        if ($linhaContato) {
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 5, $t(implode('  •  ', $linhaContato)), 0, 1, 'C');
        }
        $pdf->Ln(3);

        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell(0, 8, $t('Comprovante de Pedido'), 0, 1, 'C');

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.4);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(6);

        // ── Dados do pedido ────────────────────────────────────────────────
        $statusLabel = match ($pedido['status']) {
            'pendente'   => 'Pendente',
            'em_preparo' => 'Em preparo',
            'pronto'     => 'Pronto',
            'entregue'   => 'Entregue',
            'pago'       => 'Pago',
            'cancelado'  => 'Cancelado',
            default      => ucfirst((string) $pedido['status']),
        };

        $nomeCliente = trim((string) ($pedido['nome_cliente'] ?? ''));
        $localAtendimento = $pedido['mesa_numero']
            ? 'Mesa ' . $pedido['mesa_numero']
            : 'Pedido Virtual (Delivery/Retirada)';

        $pdf->SetFont('Arial', '', 10);
        $linhas = [
            ['Pedido:', '#' . $pedido['id']],
            ['Atendimento:', $localAtendimento],
            ['Cliente:', $nomeCliente !== '' ? $nomeCliente : 'Não informado'],
            ['Data:', $pedido['criado_em'] ?? '-'],
            ['Forma de pagamento:', $pedido['forma_pagamento'] ?? '-'],
            ['Status:', $statusLabel],
        ];
        foreach ($linhas as [$label, $valor]) {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(50, 6, $t($label), 0, 0);
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(0, 6, $t((string) $valor), 0, 1);
        }

        // ── Endereço de entrega (só aparece se houver) ────────────────────
        $enderecoTexto = trim(implode(' — ', array_filter([
            $pedido['endereco_logradouro'] ?? null,
            $pedido['endereco_numero'] ?? null,
            $pedido['endereco_bairro'] ?? null,
            $pedido['endereco_cidade'] ?? null,
            !empty($pedido['endereco_cep']) ? 'CEP ' . $pedido['endereco_cep'] : null,
        ], static fn ($v) => $v !== null && trim((string) $v) !== '')));

        if ($enderecoTexto !== '') {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(50, 6, $t('Endereço:'), 0, 0);
            $pdf->SetFont('Arial', '', 10);
            $pdf->MultiCell(0, 6, $t($enderecoTexto));
        }

        if (!empty($pedido['observacao'])) {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(50, 6, $t('Observação:'), 0, 0);
            $pdf->SetFont('Arial', '', 10);
            $pdf->MultiCell(0, 6, $t((string) $pedido['observacao']));
        }

        $pdf->Ln(4);

        // ── Tabela de itens ────────────────────────────────────────────────
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->Cell(85, 8, $t('Item'), 1, 0, 'L', true);
        $pdf->Cell(20, 8, $t('Qtd'), 1, 0, 'C', true);
        $pdf->Cell(35, 8, $t('Unitário'), 1, 0, 'R', true);
        $pdf->Cell(37, 8, $t('Subtotal'), 1, 1, 'R', true);

        $pdf->SetFont('Arial', '', 10);
        foreach ($itens as $item) {
            $pdf->Cell(85, 7, $t((string) $item['nome']), 1, 0, 'L');
            $pdf->Cell(20, 7, (string) $item['quantidade'], 1, 0, 'C');
            $pdf->Cell(35, 7, 'R$ ' . number_format((float) $item['preco'], 2, ',', '.'), 1, 0, 'R');
            $pdf->Cell(37, 7, 'R$ ' . number_format((float) $item['subtotal'], 2, ',', '.'), 1, 1, 'R');
        }

        if (empty($itens)) {
            $pdf->SetFont('Arial', 'I', 10);
            $pdf->Cell(177, 8, $t('Nenhum item ativo neste pedido.'), 1, 1, 'C');
        }

        // ── Total ─────────────────────────────────────────────────────────
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->Cell(140, 9, $t('TOTAL'), 1, 0, 'R', true);
        $pdf->Cell(37, 9, 'R$ ' . number_format((float) $pedido['total'], 2, ',', '.'), 1, 1, 'R', true);

        $pdf->Ln(10);

        // ── Rodapé ────────────────────────────────────────────────────────
        $pdf->SetFont('Arial', 'I', 9);
        $pdf->Cell(0, 6, $t('Obrigado pela preferência!'), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(0, 5, $t('Comprovante gerado em ' . date('d/m/Y H:i:s')), 0, 1, 'C');

        $pdfContent = $pdf->Output('S', 'pedido_' . $id . '.pdf');

        $response->getBody()->write($pdfContent);

        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'inline; filename="pedido_' . $id . '.pdf"')
            ->withStatus(200);
    } catch (\Exception $e) {
        return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
    }
}
}