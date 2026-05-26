<?php

declare(strict_types=1);

namespace app\controller;

use app\database\DB;

final class Sale extends Base
{
    public function list($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('listasale'), [
                'titulo' => 'Vendas',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function details($request, $response, $args)
    {
        $id = $args['id'] ?? null;

        return $this->getTwig()
            ->render($response, $this->setView('sale'), [
                'titulo' => 'Venda',
                'saleId' => $id,
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function find($request, $response)
    {
        $form = $request->getParsedBody();
        $term = $form['term'] ?? '';
        $limit = (int) ($form['limit'] ?? 10);
        $offset = (int) ($form['offset'] ?? 0);
        $orderType = strtolower($form['orderType'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $draw = (int) ($form['draw'] ?? 1);

        try {
            $queryBase = DB::select('sale.*', 'customer.nome AS nome_cliente')
                ->from('sale')
                ->leftJoin('customer', 'sale.id_cliente', 'customer.id');

            $total = (int) DB::select('COUNT(id) AS count')
                ->from('sale')
                ->fetchOne();

            if ($term !== '') {
                $queryBase->setParameter('term', '%' . $term . '%');
                $queryBase->where('CAST(sale.id AS TEXT) ILIKE :term')
                    ->orWhere('sale.observacao ILIKE :term')
                    ->orWhere('customer.nome ILIKE :term');
            }

            $filteredCountQuery = DB::select('COUNT(sale.id) AS count')
                ->from('sale')
                ->leftJoin('customer', 'sale.id_cliente', 'customer.id');

            if ($term !== '') {
                $filteredCountQuery->setParameter('term', '%' . $term . '%');
                $filteredCountQuery->where('CAST(sale.id AS TEXT) ILIKE :term')
                    ->orWhere('customer.nome ILIKE :term');
            }

            $filtered = (int) $filteredCountQuery->fetchOne();

            $rows = $queryBase
                ->orderBy('sale.id', $orderType)
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->fetchAllAssociative();

            return $this->json($response, [
                'draw' => $draw,
                'recordsTotal' => $total,
                'recordsFiltered' => $filtered,
                'data' => $rows,
            ], 200);
        } catch (\Exception $e) {
            return $this->json($response, [
                'status' => false,
                'msg' => 'Erro: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    public function findById($request, $response, $args)
    {
        $id = $args['id'] ?? null;

        if (empty($id)) {
            return $this->json($response, [
                'status' => false,
                'msg' => 'ID é obrigatório',
                'data' => [],
            ], 400);
        }

        try {
            $row = DB::select('*')
                ->from('sale')
                ->where('id = :id')
                ->setParameter('id', $id)
                ->fetchAssociative();

            return $this->json($response, [
                'status' => true,
                'data' => $row ? [$row] : [],
            ], 200);
        } catch (\Exception $e) {
            return $this->json($response, [
                'status' => false,
                'msg' => 'Erro: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    public function insert($request, $response)
    {
        $form = $request->getParsedBody();
        if (empty($form['id_cliente'])) {
            return $this->json($response, [
                'status' => false,
                'msg' => 'O cliente é obrigatório para criar a venda.',
                'id' => 0,
            ], 400);
        }

        try {
            DB::connection()->insert('sale', $form);
            $id = (int) DB::connection()->fetchOne("SELECT currval(pg_get_serial_sequence('sale', 'id'))");

            return $this->json($response, [
                'status' => true,
                'id' => $id,
            ], 201);
        } catch (\Exception $e) {
            return $this->json($response, [
                'status' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update($request, $response)
    {
        $form = $request->getParsedBody();
        $id = $form['id'] ?? null;

        if (empty($id)) {
            return $this->json($response, [
                'status' => false,
                'error' => 'ID é obrigatório',
            ], 400);
        }

        try {
            DB::connection()->update('sale', $form, ['id' => $id]);
            return $this->json($response, ['status' => true], 200);
        } catch (\Exception $e) {
            return $this->json($response, [
                'status' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function delete($request, $response)
    {
        $form = $request->getParsedBody();
        $id = $form['id'] ?? null;

        if (empty($id)) {
            return $this->json($response, [
                'status' => false,
                'error' => 'ID é obrigatório',
            ], 400);
        }

        try {
            DB::connection()->delete('sale', ['id' => $id]);
            return $this->json($response, ['status' => true], 200);
        } catch (\Exception $e) {
            return $this->json($response, [
                'status' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function insertItem($request, $response)
    {
        $form = $request->getParsedBody();
        $id = $form['id'] ?? null;
        $idProduto = $form['id_produto'] ?? null;
        $quantidade = isset($form['quantidade']) ? (float) $form['quantidade'] : 1;
        $precoUnitario = isset($form['preco_unitario']) ? (float) $form['preco_unitario'] : 0;

        if (empty($id)) {
            return $this->json($response, [
                'status' => false,
                'msg' => 'Restrição: O ID da venda é obrigatório!',
                'id' => 0,
            ], 400);
        }

        if (empty($idProduto)) {
            return $this->json($response, [
                'status' => false,
                'msg' => 'Restrição: O ID do produto é obrigatório!',
                'id' => 0,
            ], 400);
        }

        try {
            $produto = DB::select('*')
                ->from('product')
                ->where('id = :id')
                ->setParameter('id', $idProduto)
                ->fetchAssociative();

            if (!$produto) {
                return $this->json($response, [
                    'status' => false,
                    'msg' => 'Restrição: Nenhum produto localizado!',
                    'id' => 0,
                ], 404);
            }

            $precoUnitario = $precoUnitario > 0 ? $precoUnitario : (float) ($produto['preco_venda'] ?? 0);
            $totalBruto = $quantidade * $precoUnitario;
            $totalLiquido = $totalBruto;

            DB::connection()->insert('item_sale', [
                'id_venda' => $id,
                'id_produto' => $idProduto,
                'quantidade' => $quantidade,
                'unitario_bruto' => $precoUnitario,
                'unitario_liquido' => $precoUnitario,
                'total_bruto' => $totalBruto,
                'total_liquido' => $totalLiquido,
                'desconto' => 0,
                'acrescimo' => 0,
                'nome' => $produto['nome'] ?? '',
            ]);

            $saleTotals = DB::select('SUM(total_bruto) AS total_bruto', 'SUM(total_liquido) AS total_liquido')
                ->from('item_sale')
                ->where('id_venda = :id_venda')
                ->setParameter('id_venda', $id)
                ->fetchAssociative();

            DB::connection()->update('sale', [
                'total_bruto' => (float) ($saleTotals['total_bruto'] ?? 0),
                'total_liquido' => (float) ($saleTotals['total_liquido'] ?? 0),
            ], ['id' => $id]);

            return $this->json($response, [
                'status' => true,
                'msg' => 'Item inserido com sucesso!',
                'id' => 0,
            ], 201);
        } catch (\Exception $e) {
            return $this->json($response, [
                'status' => false,
                'msg' => 'Restrição: ' . $e->getMessage(),
                'id' => 0,
            ], 500);
        }
    }

    public function insertInstallmentSale($request, $response)
    {
        $form = $request->getParsedBody();

        try {
            DB::connection()->insert('installment_sale_purchase', [
                'id_sale' => $form['id_sale'],
                'id_purchase' => $form['id_purchase'] ?? 0,
                'id_installment' => $form['id_installment'] ?? 0,
                'id_payment_terms' => $form['id_payment_terms'],
                'total_parcelas' => $form['total_parcelas'],
                'numero_parcela' => $form['numero_parcela'],
                'valor_parcela' => $form['valor_parcela'],
                'valor_total' => $form['valor_total'],
                'status' => $form['status'] ?? 'aberto',
                'data_vencimento' => $form['data_vencimento'],
            ]);

            return $this->json($response, ['status' => true], 201);
        } catch (\Exception $e) {
            return $this->json($response, [
                'status' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
