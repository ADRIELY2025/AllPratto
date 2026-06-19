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
     *
     * Espelha o comportamento do Sale.insertItem() do projeto Node (sistema_venda_e_estoque):
     *   1. Valida venda e produto.
     *   2. Resolve o preço unitário (o que veio do formulário, com fallback para o
     *      preço de venda cadastrado no produto).
     *   3. Insere o item em item_sale.
     *   4. Soma todos os itens da venda e grava o total atualizado em sale
     *      (total_bruto / total_liquido) — é isso que fazia a venda nunca
     *      bater com os itens lançados.
     *
     * IMPORTANTE: este insert NÃO movimenta estoque. A baixa de estoque só
     * acontece quando a venda é finalizada (estado_venda -> 'VENDA'), via
     * trigger no banco (trg_sale_to_stock_movement em sale).
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

        if (is_null($idProduto)) {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o ID do produto.', 'id' => 0], 403);
        }

        $idVenda = (int) $idVenda;

        try {
            $venda = \App\Database\DB::select('id')
                ->from('sale')
                ->where('id = :id')
                ->setParameter('id', $idVenda, \Doctrine\DBAL\ParameterType::INTEGER)
                ->fetchAssociative();

            if (!$venda) {
                return $this->json($response, ['status' => false, 'msg' => 'Venda não encontrada.', 'id' => 0], 404);
            }

            $produto = \App\Database\DB::select('id, nome, descricao, preco_venda')
                ->from('product')
                ->where('id = :id')
                ->setParameter('id', $idProduto, \Doctrine\DBAL\ParameterType::INTEGER)
                ->fetchAssociative();

            if (!$produto) {
                return $this->json($response, ['status' => false, 'msg' => 'Nenhum produto localizado!', 'id' => 0], 404);
            }

            $quantidade = $this->toDecimal($form['quantidade'] ?? 0);
            if ($quantidade <= 0) {
                $quantidade = 1.0;
            }

            // Preço unitário: usa o que vier do formulário; se não vier (ou vier zerado),
            // cai para o preço de venda cadastrado no produto.
            $unitario = $this->toDecimal($form['unitario_liquido'] ?? $form['unitario_bruto'] ?? 0);
            if ($unitario <= 0) {
                $unitario = (float) $produto['preco_venda'];
            }

            $total = round($quantidade * $unitario, 4);

            $data = [
                'id_venda'         => $idVenda,
                'id_produto'       => $idProduto,
                'nome'             => $form['nome']      ?? $produto['nome'],
                'descricao'        => $form['descricao'] ?? $produto['descricao'],
                'quantidade'       => $quantidade,
                'total_bruto'      => $total,
                'unitario_bruto'   => $unitario,
                'total_liquido'    => $total,
                'unitario_liquido' => $unitario,
                'desconto'         => $this->toDecimal($form['desconto']  ?? 0),
                'acrescimo'        => $this->toDecimal($form['acrescimo'] ?? 0),
            ];

            $conn = \App\Database\DB::connection();
            $id   = $conn->transactional(function ($conn) use ($data, $idVenda) {
                $conn->insert('item_sale', $data);
                $itemId = (int) $conn->lastInsertId();

                $this->recalcularTotaisVenda($idVenda, $conn);

                return $itemId;
            });

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
            $item = \App\Database\DB::select('id_venda')
                ->from('item_sale')
                ->where('id = :id')
                ->setParameter('id', (int) $id, \Doctrine\DBAL\ParameterType::INTEGER)
                ->fetchAssociative();

            if (!$item) {
                return $this->json($response, ['status' => false, 'msg' => 'Item não encontrado.', 'id' => $id], 404);
            }

            $idVenda = (int) $item['id_venda'];
            $conn    = \App\Database\DB::connection();

            $deleted = $conn->transactional(function ($conn) use ($id, $idVenda) {
                $rows = $conn->delete('item_sale', ['id' => (int) $id]);

                $this->recalcularTotaisVenda($idVenda, $conn);

                return $rows;
            });

            if (!$deleted) {
                return $this->json($response, ['status' => false, 'msg' => 'Nenhum item removido.', 'id' => $id], 403);
            }

            return $this->json($response, ['status' => true, 'msg' => 'Item removido com sucesso!', 'id' => $id]);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    // ──────────────────────────────────────────────
    //  Helpers privados
    // ──────────────────────────────────────────────

    /**
     * Soma todos os itens de uma venda e grava o resultado em sale.total_bruto /
     * sale.total_liquido. Chamado sempre após inserir ou remover um item, para que
     * a venda nunca fique "desincronizada" da sua lista de itens.
     */
    private function recalcularTotaisVenda(int $idVenda, \Doctrine\DBAL\Connection $conn): void
    {
        $totais = \App\Database\DB::select(
            'COALESCE(SUM(total_bruto), 0)   AS total_bruto',
            'COALESCE(SUM(total_liquido), 0) AS total_liquido'
        )
            ->from('item_sale')
            ->where('id_venda = :id_venda')
            ->setParameter('id_venda', $idVenda, \Doctrine\DBAL\ParameterType::INTEGER)
            ->fetchAssociative();

        $conn->update('sale', [
            'total_bruto'   => (float) $totais['total_bruto'],
            'total_liquido' => (float) $totais['total_liquido'],
            'atualizado_em' => date('Y-m-d H:i:s'),
        ], ['id' => $idVenda]);
    }

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