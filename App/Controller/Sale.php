<?php

declare(strict_types=1);

namespace App\Controller;

final class Sale extends Base
{
    // ──────────────────────────────────────────────
    //  Páginas HTML
    // ──────────────────────────────────────────────

    public function list($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('list-sale'), [
                'titulo' => 'Lista de vendas',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function details($request, $response, $args)
    {
        $id     = $args['id'] ?? null;
        $action = ($id === null) ? 'c' : 'e';
        $sale   = [];

        if (!is_null($id)) {
            $qb   = \App\Database\DB::select('s.*, c.nome_fantasia AS nome_cliente')
                ->from('sale', 's')
                ->leftJoin('s', 'customer', 'c', 's.id_cliente = c.id');
            $sale = $qb
                ->where('s.id = ' . $qb->createPositionalParameter((int) $id, \Doctrine\DBAL\ParameterType::INTEGER))
                ->fetchAssociative();
        }

        return $this->getTwig()
            ->render($response, $this->setView('sale'), [
                'titulo' => 'Venda',
                'id'     => $id,
                'action' => $action,
                'sale'   => $sale,
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    // ──────────────────────────────────────────────
    //  CRUD – Venda
    // ──────────────────────────────────────────────

    public function insert($request, $response)
    {
        $form = $request->getParsedBody();

        $idCliente = isset($form['id_cliente']) && $form['id_cliente'] !== ''
            ? (int) $form['id_cliente']
            : null;

        // estado_venda usa o ENUM stock_movement_venda (PRE_VENDA | ORCAMENTO | VENDA).
        // Uma venda recém-criada (ainda em edição/carrinho) começa sempre como PRE_VENDA.
        $estadoVenda = in_array($form['estado_venda'] ?? null, ['PRE_VENDA', 'ORCAMENTO', 'VENDA'], true)
            ? $form['estado_venda']
            : 'PRE_VENDA';

        $data = [
            'id_cliente'    => $idCliente,
            'total_bruto'   => $this->toDecimal($form['total_bruto']   ?? 0),
            'total_liquido' => $this->toDecimal($form['total_liquido'] ?? 0),
            'desconto'      => $this->toDecimal($form['desconto']      ?? 0),
            'acrescimo'     => $this->toDecimal($form['acrescimo']     ?? 0),
            'observacao'    => $form['observacao'] ?? null,
            'estado_venda'  => $estadoVenda,
        ];

        try {
            $conn = \App\Database\DB::connection();
            $conn->insert('sale', $data);
            $id = (int) $conn->lastInsertId();

            if (!$id) {
                return $this->json($response, ['status' => false, 'msg' => 'Não foi possível obter o ID da venda.', 'id' => 0], 500);
            }

            return $this->json($response, ['status' => true, 'msg' => 'Venda criada com sucesso!', 'id' => $id], 201);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function update($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Por favor informe o ID da venda.', 'id' => 0], 403);
        }

        // ATENÇÃO: monta o array só com os campos que vieram na requisição.
        // O modal de finalização (fsmConclude) não envia "id_cliente", por exemplo —
        // se enviássemos sempre todos os campos com fallback para null/0, esse update
        // apagaria o cliente já vinculado à venda. Update parcial = sem efeitos colaterais.
        $data = [];

        if (isset($form['id_cliente']) && $form['id_cliente'] !== '') {
            $data['id_cliente'] = (int) $form['id_cliente'];
        }
        if (isset($form['total_bruto'])) {
            $data['total_bruto'] = $this->toDecimal($form['total_bruto']);
        }
        if (isset($form['total_liquido'])) {
            $data['total_liquido'] = $this->toDecimal($form['total_liquido']);
        }
        if (isset($form['desconto'])) {
            $data['desconto'] = $this->toDecimal($form['desconto']);
        }
        if (isset($form['acrescimo'])) {
            $data['acrescimo'] = $this->toDecimal($form['acrescimo']);
        }
        if (isset($form['observacao'])) {
            $data['observacao'] = $form['observacao'];
        }
        // estado_venda usa o ENUM stock_movement_venda — só aceita os valores válidos.
        // É essa transição (qualquer estado -> 'VENDA') que dispara, via trigger no banco,
        // a baixa automática de estoque referente a todos os itens já lançados na venda.
        if (isset($form['estado_venda']) && in_array($form['estado_venda'], ['PRE_VENDA', 'ORCAMENTO', 'VENDA'], true)) {
            $data['estado_venda'] = $form['estado_venda'];
        }

        $data['atualizado_em'] = date('Y-m-d H:i:s');

        try {
            $updated = \App\Database\DB::connection()->update('sale', $data, ['id' => (int) $id]);

            if (!$updated) {
                return $this->json($response, ['status' => false, 'msg' => 'Nenhum registro alterado.', 'id' => 0], 403);
            }

            return $this->json($response, ['status' => true, 'msg' => 'Venda alterada com sucesso!', 'id' => $id], 200);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function delete($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o código da venda.', 'id' => 0], 403);
        }

        try {
            $deleted = \App\Database\DB::connection()->delete('sale', ['id' => (int) $id]);

            if (!$deleted) {
                return $this->json($response, ['status' => false, 'msg' => 'Nenhum registro removido.', 'id' => $id], 403);
            }

            return $this->json($response, ['status' => true, 'msg' => 'Venda removida com sucesso!', 'id' => $id]);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    // ──────────────────────────────────────────────
    //  Buscas auxiliares (selects/ajax)
    // ──────────────────────────────────────────────

    /**
     * Retorna formas de pagamento com suas parcelas para o modal de finalização.
     * GET /sale/payment-terms
     */
    public function findPaymentTerms($request, $response)
    {
        try {
            $terms = \App\Database\DB::select('pt.id, pt.titulo, pt.codigo, pt.atalho')
                ->from('payment_terms', 'pt')
                ->orderBy('pt.titulo', 'ASC')
                ->fetchAllAssociative();

            return $this->json($response, ['status' => true, 'data' => $terms], 200);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * Retorna as parcelas de uma forma de pagamento.
     * GET /sale/installments/{id}
     */
    public function findInstallments($request, $response, $args)
    {
        $idPayment = $args['id'] ?? null;

        if (is_null($idPayment)) {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o ID da forma de pagamento.'], 403);
        }

        try {
            $installments = \App\Database\DB::select('id, parcela, intervalo, alterar_vencimento_conta')
                ->from('installment')
                ->where('id_pagamento = :id')
                ->setParameter('id', (int) $idPayment, \Doctrine\DBAL\ParameterType::INTEGER)
                ->orderBy('parcela', 'ASC')
                ->fetchAllAssociative();

            return $this->json($response, ['status' => true, 'data' => $installments], 200);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * Busca produtos para o Select2 (ajax).
     * POST /sale/find-product
     */
    public function findProduct($request, $response)
    {
        $form   = $request->getParsedBody();
        $term   = $form['term'] ?? '';
        $limit  = (int) ($form['limit']  ?? 50);
        $offset = (int) ($form['offset'] ?? 0);

        try {
            $query = \App\Database\DB::select('id, nome, codigo_barra, preco_venda')
                ->from('product')
                ->where('excluido = false AND ativo = true');

            if ($term !== '') {
                $query->andWhere('(nome ILIKE :term OR codigo_barra ILIKE :term)')
                      ->setParameter('term', '%' . $term . '%');
            }

            $products = $query->orderBy('nome', 'ASC')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->fetchAllAssociative();

            return $this->json($response, ['status' => true, 'data' => $products], 200);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * Retorna um produto pelo ID (para preencher preço unitário).
     * GET /sale/find-product/{id}
     */
    public function findProductById($request, $response, $args)
    {
        $id = $args['id'] ?? null;

        if (is_null($id)) {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o ID do produto.'], 403);
        }

        try {
            $qb      = \App\Database\DB::select('id, nome, codigo_barra, preco_venda, descricao, unidade')
                ->from('product');
            $product = $qb->where('id = ' . $qb->createPositionalParameter((int) $id, \Doctrine\DBAL\ParameterType::INTEGER))
                ->fetchAssociative();

            if (!$product) {
                return $this->json($response, ['status' => false, 'msg' => 'Produto não encontrado.'], 404);
            }

            return $this->json($response, $product, 200);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * Busca clientes para o Select2 (ajax).
     * POST /sale/find-customer
     */
    public function findCustomer($request, $response)
    {
        $form   = $request->getParsedBody();
        $term   = $form['term'] ?? '';
        $limit  = (int) ($form['limit']  ?? 50);
        $offset = (int) ($form['offset'] ?? 0);

        try {
            $query = \App\Database\DB::select('id, nome_fantasia, sobrenome_razao, cpf_cnpj')
                ->from('customer')
                ->where('ativo = true');

            if ($term !== '') {
                $query->andWhere('(nome_fantasia ILIKE :term OR cpf_cnpj ILIKE :term)')
                      ->setParameter('term', '%' . $term . '%');
            }

            $customers = $query->orderBy('nome_fantasia', 'ASC')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->fetchAllAssociative();

            // Mapeia para formato compatível com o front-end
            $data = array_map(fn($c) => [
                'id'       => $c['id'],
                'nome'     => trim($c['nome_fantasia'] . ' ' . ($c['sobrenome_razao'] ?? '')),
                'cpf'      => $c['cpf_cnpj'],
            ], $customers);

            return $this->json($response, ['status' => true, 'data' => $data], 200);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    // ──────────────────────────────────────────────
    //  DataTable – listagem de vendas
    // ──────────────────────────────────────────────

    public function listingdata($request, $response)
    {
        $form   = $request->getParsedBody();
        $term   = $form['search']['value'] ?? null;
        $start  = (int) ($form['start']  ?? 0);
        $length = (int) ($form['length'] ?? 10);

        $columns = [
            0 => 's.id',
            1 => 'c.nome_fantasia',
            2 => 's.total_bruto',
            3 => 's.total_liquido',
            4 => 's.desconto',
            5 => 's.acrescimo',
            6 => 's.estado_venda',
            7 => 's.criado_em',
        ];

        $posField   = (isset($form['order'][0]['column']) && isset($columns[(int) $form['order'][0]['column']]))
            ? (int) $form['order'][0]['column']
            : 0;
        $orderType  = strtoupper($form['order'][0]['dir'] ?? 'DESC');
        $orderType  = in_array($orderType, ['ASC', 'DESC'], true) ? $orderType : 'DESC';
        $orderField = $columns[$posField];

        try {
            $totalRecords = (int) \App\Database\DB::select('COUNT(*)')
                ->from('sale')
                ->fetchOne();

            $query = \App\Database\DB::select("
                s.id,
                c.nome_fantasia                                                AS nome_cliente,
                s.total_bruto,
                s.total_liquido,
                s.desconto,
                s.acrescimo,
                s.observacao,
                s.estado_venda,
                to_char(s.criado_em,     'DD/MM/YYYY HH24:MI:SS')            AS criado_em,
                to_char(s.atualizado_em, 'DD/MM/YYYY HH24:MI:SS')            AS atualizado_em
            ")
            ->from('sale', 's')
            ->leftJoin('s', 'customer', 'c', 's.id_cliente = c.id');

            if (!is_null($term) && $term !== '') {
                $query->setParameter('term', '%' . $term . '%');
                $query->where('CAST(s.id AS TEXT) ILIKE :term')
                    ->orWhere('c.nome_fantasia ILIKE :term')
                    ->orWhere('CAST(s.total_bruto AS TEXT) ILIKE :term')
                    ->orWhere('CAST(s.total_liquido AS TEXT) ILIKE :term')
                    ->orWhere('CAST(s.estado_venda AS TEXT) ILIKE :term')
                    ->orWhere("TO_CHAR(s.criado_em, 'DD/MM/YYYY HH24:MI:SS') ILIKE :term");
            }

            $filteredRecords = (int) (clone $query)->select('COUNT(*)')->fetchOne();

            $sales = $query
                ->orderBy($orderField, $orderType)
                ->setFirstResult($start)
                ->setMaxResults($length)
                ->fetchAllAssociative();

            // estado_venda é o ENUM stock_movement_venda do banco — os rótulos abaixo
            // precisam refletir exatamente os labels válidos (PRE_VENDA | ORCAMENTO | VENDA).
            $estadoLabel = [
                'PRE_VENDA' => '<span class="badge bg-warning text-dark">Em edição</span>',
                'ORCAMENTO' => '<span class="badge bg-info text-dark">Orçamento</span>',
                'VENDA'     => '<span class="badge bg-success">Finalizada</span>',
            ];

            $rows = [];
            foreach ($sales as $key => $value) {
                $estado = $value['estado_venda'] ?? 'PRE_VENDA';
                $rows[$key] = [
                    $value['id'],
                    $value['nome_cliente'] ?? '<span class="text-muted">Sem cliente</span>',
                    'R$ ' . number_format((float) $value['total_bruto'],   2, ',', '.'),
                    'R$ ' . number_format((float) $value['total_liquido'], 2, ',', '.'),
                    number_format((float) $value['desconto'],  2, ',', '.') . '%',
                    number_format((float) $value['acrescimo'], 2, ',', '.') . '%',
                    $value['observacao'] ?? '-',
                    $estadoLabel[$estado] ?? $estado,
                    $value['criado_em'],
                    "<td>
                        <a class='btn btn-sm btn-warning' href='/sale/detalhes/{$value['id']}'>
                            <i class='fa-solid fa-pen-to-square'></i> Editar
                        </a>
                        <button type='button' class='btn btn-sm btn-danger' onclick='ShowModal({$value['id']});'>
                            <i class='fa-solid fa-trash'></i> Excluir
                        </button>
                    </td>",
                ];
            }

            return $this->json($response, [
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data'            => $rows,
            ], 200);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    // ──────────────────────────────────────────────
    //  Helpers privados
    // ──────────────────────────────────────────────

    private function toDecimal(mixed $value): float
    {
        $str = (string) $value;
        if (str_contains($str, ',')) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
        }
        return (float) $str;
    }
}