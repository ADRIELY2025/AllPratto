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

    // ──────────────────────────────────────────
    //  Criar pedido (chamado pelo cardápio digital)
    //  Body JSON: { mesa: 3, itens: [{id,nome,preco,quantidade}], pagamento: 'dinheiro' }
    // ──────────────────────────────────────────
    public function insert($request, $response)
    {
        $form = $request->getParsedBody();

        $idMesa    = isset($form['mesa'])   ? (int) $form['mesa']   : null;
        $itens     = $form['itens']         ?? [];
        $pagamento = $form['pagamento']     ?? null;
        $observacao = $form['observacao']   ?? null;

        if (!$idMesa || empty($itens)) {
            return $this->json($response, ['status' => false, 'msg' => 'Mesa e itens são obrigatórios.', 'id' => 0], 400);
        }

        // Verifica se a mesa existe
        $qbMesa = \App\Database\DB::select('id, status')->from('mesa');
        $mesa   = $qbMesa
            ->where('id = ' . $qbMesa->createPositionalParameter($idMesa, \Doctrine\DBAL\ParameterType::INTEGER))
            ->fetchAssociative();

        if (!$mesa) {
            return $this->json($response, ['status' => false, 'msg' => 'Mesa não encontrada.', 'id' => 0], 404);
        }

        // Calcula o total somando os itens recebidos
        $total = 0;
        foreach ($itens as $item) {
            $qty    = max(1, (int) ($item['quantidade'] ?? 1));
            $preco  = (float) ($item['preco'] ?? 0);
            $total += $preco * $qty;
        }

        try {
            $conn = \App\Database\DB::connection();

            // 1. Insere o pedido
            $conn->insert('"order"', [
                'id_mesa'          => $idMesa,
                'id_cliente'       => isset($form['id_cliente']) && $form['id_cliente'] !== '' ? (int) $form['id_cliente'] : null,
                'payment_terms_id' => null,
                'total'            => $total,
                'status'           => 'pendente',
                'observacao'       => $observacao,
            ]);

            $pedidoId = (int) $conn->lastInsertId();

            if (!$pedidoId) {
                return $this->json($response, ['status' => false, 'msg' => 'Não foi possível criar o pedido.', 'id' => 0], 500);
            }

            // 2. Insere cada item do pedido
            foreach ($itens as $item) {
                $qty  = max(1, (int) ($item['quantidade'] ?? 1));
                $preco = (float) ($item['preco'] ?? 0);

                $conn->insert('order_item', [
                    'order_id'   => $pedidoId,
                    'product_id' => isset($item['id']) && $item['id'] !== '' ? (int) $item['id'] : null,
                    'nome'       => $item['nome']    ?? 'Item sem nome',
                    'preco'      => $preco,
                    'quantidade' => $qty,
                    'subtotal'   => $preco * $qty,
                ]);
            }

            // 3. Marca a mesa como ocupada
            $conn->update('mesa', [
                'status'        => 'ocupada',
                'atualizado_em' => date('Y-m-d H:i:s'),
            ], ['id' => $idMesa]);

            return $this->json($response, [
                'status'    => true,
                'msg'       => 'Pedido enviado para a cozinha!',
                'id'        => $pedidoId,
                'mesa'      => $idMesa,
                'total'     => $total,
            ], 201);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
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