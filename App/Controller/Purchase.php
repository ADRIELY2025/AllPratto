<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\DB;
use Doctrine\DBAL\ParameterType;

final class Purchase extends Base
{
    // ──────────────────────────────────────────────
    //  Página HTML — lista de compras
    // ──────────────────────────────────────────────

    public function list($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('list-purchase'), [
                'titulo' => 'Compras',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    // ──────────────────────────────────────────────
    //  Página HTML — detalhe de uma compra
    // ──────────────────────────────────────────────

    public function details($request, $response, $args)
    {
        $id       = $args['id'] ?? null;
        $purchase = [];
        $itens    = [];

        if (!is_null($id)) {
            $purchase = DB::select('p.*, s.nome_fantasia AS nome_fornecedor')
                ->from('purchase', 'p')
                ->leftJoin('p', 'supplier', 's', 'p.id_fornecedor = s.id')
                ->where('p.id = :id')
                ->setParameter('id', (int) $id, ParameterType::INTEGER)
                ->fetchAssociative();

            if ($purchase) {
                $itens = DB::select('ip.*, pr.descricao AS nome_produto')
                    ->from('item_purchase', 'ip')
                    ->leftJoin('ip', 'product', 'pr', 'ip.id_produto = pr.id')
                    ->where('ip.id_compra = :id_compra')
                    ->setParameter('id_compra', (int) $id, ParameterType::INTEGER)
                    ->orderBy('ip.id', 'ASC')
                    ->fetchAllAssociative();
            }
        }

        return $this->getTwig()
            ->render($response, $this->setView('purchase'), [
                'titulo'   => 'Detalhe da Compra',
                'id'       => $id,
                'purchase' => $purchase,
                'itens'    => $itens,
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    // ──────────────────────────────────────────────
    //  API — listingdata (DataTables server-side)
    // ──────────────────────────────────────────────

    public function listingdata($request, $response)
    {
        $form   = $request->getParsedBody();
        $term   = $form['search']['value'] ?? null;
        $start  = (int) ($form['start']  ?? 0);
        $length = (int) ($form['length'] ?? 10);

        $columns = [
            0 => 'p.id',
            1 => 'p.observacao',
            2 => 's.nome_fantasia',
            3 => 'p.total_bruto',
            4 => 'p.total_liquido',
            5 => 'p.desconto',
            6 => 'p.acrescimo',
            7 => 'p.estado_compra',
            8 => 'p.data_cadastro',
        ];

        $posField   = (isset($form['order'][0]['column']) && isset($columns[(int) $form['order'][0]['column']]))
            ? (int) $form['order'][0]['column']
            : 0;
        $orderType  = strtoupper($form['order'][0]['dir'] ?? 'DESC');
        $orderType  = in_array($orderType, ['ASC', 'DESC'], true) ? $orderType : 'DESC';
        $orderField = $columns[$posField];

        try {
            $totalRecords = (int) DB::select('COUNT(*)')->from('purchase')->fetchOne();

            $query = DB::select("
                p.id,
                p.observacao,
                p.total_bruto,
                p.total_liquido,
                p.desconto,
                p.acrescimo,
                p.estado_compra,
                to_char(p.data_cadastro, 'DD/MM/YYYY HH24:MI:SS') AS data_cadastro,
                s.nome_fantasia AS nome_fornecedor
            ")
            ->from('purchase', 'p')
            ->leftJoin('p', 'supplier', 's', 'p.id_fornecedor = s.id');

            if (!is_null($term) && $term !== '') {
                $query->setParameter('term', '%' . $term . '%');
                $query->where('CAST(p.id AS TEXT) ILIKE :term')
                    ->orWhere('p.observacao ILIKE :term')
                    ->orWhere('CAST(p.estado_compra AS TEXT) ILIKE :term')
                    ->orWhere("TO_CHAR(p.data_cadastro, 'DD/MM/YYYY HH24:MI:SS') ILIKE :term");
            }

            $filteredRecords = (int) (clone $query)->select('COUNT(*)')->fetchOne();

            $rows = $query
                ->orderBy($orderField, $orderType)
                ->setFirstResult($start)
                ->setMaxResults($length)
                ->fetchAllAssociative();

            // Formata valores monetários e monta coluna de ação
            foreach ($rows as &$row) {
                $row['total_bruto']   = $row['total_bruto']   !== null
                    ? 'R$ ' . number_format((float) $row['total_bruto'],   2, ',', '.')
                    : '—';
                $row['total_liquido'] = $row['total_liquido'] !== null
                    ? 'R$ ' . number_format((float) $row['total_liquido'], 2, ',', '.')
                    : '—';
                $row['desconto']      = $row['desconto']      !== null
                    ? 'R$ ' . number_format((float) $row['desconto'],      2, ',', '.')
                    : '—';
                $row['acrescimo']     = $row['acrescimo']     !== null
                    ? 'R$ ' . number_format((float) $row['acrescimo'],     2, ',', '.')
                    : '—';
                $row['nome_fornecedor'] = $row['nome_fornecedor'] ?? '—';

                $statusMap = [
                    'EM_ANDAMENTO' => '<span class="badge bg-warning text-dark">Em andamento</span>',
                    'FINALIZADO'   => '<span class="badge bg-success">Finalizado</span>',
                    'CANCELADO'    => '<span class="badge bg-danger">Cancelado</span>',
                ];
                $row['estado_compra'] = $statusMap[$row['estado_compra']]
                    ?? '<span class="badge bg-secondary">' . ($row['estado_compra'] ?? '—') . '</span>';

                $id = $row['id'];
                $row['acao'] = '<button class="btn btn-sm btn-danger" onclick="ShowModal(' . $id . ')">'
                    . '<i class="fa-solid fa-trash"></i></button>';
            }

            return $this->json($response, [
                'draw'            => (int) ($form['draw'] ?? 1),
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data'            => $rows,
            ], 200);

        } catch (\Exception $e) {
            return $this->json($response, [
                'draw'            => (int) ($form['draw'] ?? 1),
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => $e->getMessage(),
            ], 500);
        }
    }

    // ──────────────────────────────────────────────
    //  API — delete
    // ──────────────────────────────────────────────

    public function delete($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (is_null($id) || $id === '') {
            return $this->json($response, [
                'status' => false,
                'msg'    => 'Informe o ID da compra.',
            ], 403);
        }

        try {
            DB::connection()->delete('purchase', ['id' => (int) $id]);

            return $this->json($response, [
                'status' => true,
                'msg'    => 'Compra excluída com sucesso.',
            ], 200);
        } catch (\Exception $e) {
            return $this->json($response, [
                'status' => false,
                'msg'    => 'Erro: ' . $e->getMessage(),
            ], 500);
        }
    }
}