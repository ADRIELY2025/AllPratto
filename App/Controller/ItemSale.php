<?php

declare(strict_types=1);

namespace App\Controller;

final class ItemSale extends Base
{
    /**
     * Lista os itens de uma venda específica.
     * GET /sale/{id}/itens
     */
    public function findBySale($request, $response, $args)
    {
        $idVenda = $args['id'] ?? null;

        if (is_null($idVenda)) {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o ID da venda.'], 403);
        }

        try {
            $items = \App\Database\DB::select("
                i.id,
                i.id_venda,
                i.id_produto,
                i.nome,
                i.descricao,
                i.quantidade,
                i.total_bruto,
                i.unitario_bruto,
                i.total_liquido,
                i.unitario_liquido,
                i.desconto,
                i.acrescimo,
                p.nome          AS nome_produto,
                p.codigo_barra
            ")
            ->from('item_sale', 'i')
            ->leftJoin('i', 'product', 'p', 'i.id_produto = p.id')
            ->where('i.id_venda = :id_venda')
            ->setParameter('id_venda', (int) $idVenda, \Doctrine\DBAL\ParameterType::INTEGER)
            ->orderBy('i.id', 'ASC')
            ->fetchAllAssociative();

            return $this->json($response, ['status' => true, 'data' => $items], 200);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Insere um item em uma venda.
     * POST /sale/item/insert
     */
    public function insert($request, $response)
    {
        $form    = $request->getParsedBody();
        $idVenda = $form['id_venda'] ?? null;

        if (is_null($idVenda) || $idVenda === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o ID da venda.', 'id' => 0], 403);
        }

        $idProduto = isset($form['id_produto']) && $form['id_produto'] !== ''
            ? (int) $form['id_produto']
            : null;

        $data = [
            'id_venda'         => (int) $idVenda,
            'id_produto'       => $idProduto,
            'nome'             => $form['nome']             ?? null,
            'descricao'        => $form['descricao']        ?? null,
            'quantidade'       => $this->toDecimal($form['quantidade']       ?? 0),
            'total_bruto'      => $this->toDecimal($form['total_bruto']      ?? 0),
            'unitario_bruto'   => $this->toDecimal($form['unitario_bruto']   ?? 0),
            'total_liquido'    => $this->toDecimal($form['total_liquido']    ?? 0),
            'unitario_liquido' => $this->toDecimal($form['unitario_liquido'] ?? 0),
            'desconto'         => $this->toDecimal($form['desconto']         ?? 0),
            'acrescimo'        => $this->toDecimal($form['acrescimo']        ?? 0),
        ];

        try {
            $conn = \App\Database\DB::connection();
            $conn->insert('item_sale', $data);
            $id = (int) $conn->lastInsertId();

            if (!$id) {
                return $this->json($response, ['status' => false, 'msg' => 'Não foi possível inserir o item.', 'id' => 0], 500);
            }

            return $this->json($response, ['status' => true, 'msg' => 'Item inserido com sucesso!', 'id' => $id], 201);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    /**
     * Remove um item da venda.
     * POST /sale/item/delete
     */
    public function delete($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o ID do item.', 'id' => 0], 403);
        }

        try {
            $deleted = \App\Database\DB::connection()->delete('item_sale', ['id' => (int) $id]);

            if (!$deleted) {
                return $this->json($response, ['status' => false, 'msg' => 'Nenhum item removido.', 'id' => $id], 403);
            }

            return $this->json($response, ['status' => true, 'msg' => 'Item removido com sucesso!', 'id' => $id]);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    // ──────────────────────────────────────────────
    //  Helper privado
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