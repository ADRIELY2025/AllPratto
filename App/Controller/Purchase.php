<?php

declare(strict_types=1);

namespace App\Controller;

final class Purchase extends Base
{
    public function list($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('list-purchase'), [
                'titulo' => 'Lista de Compras',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function listingdata($request, $response)
    {
        $form   = $request->getParsedBody();
        $term   = $form['search']['value'] ?? null;
        $start  = (int) ($form['start']  ?? 0);
        $length = (int) ($form['length'] ?? 10);

        $columns = [
            0 => 'p.id',
            1 => 's.nome_fantasia',
            2 => 'p.total_bruto',
            3 => 'p.total_liquido',
            4 => 'p.estado_compra',
            5 => 'p.data_cadastro',
        ];

        $posField   = (isset($form['order'][0]['column']) && isset($columns[(int) $form['order'][0]['column']]))
            ? (int) $form['order'][0]['column']
            : 0;
        $orderType  = strtoupper($form['order'][0]['dir'] ?? 'DESC');
        $orderType  = in_array($orderType, ['ASC', 'DESC'], true) ? $orderType : 'DESC';
        $orderField = $columns[$posField];

        try {
            $totalRecords = (int) \App\Database\DB::select('COUNT(*)')
                ->from('purchase')
                ->fetchOne();

            $query = \App\Database\DB::select("
                p.id,
                s.nome_fantasia                                             AS nome_fornecedor,
                p.total_bruto,
                p.total_liquido,
                p.desconto,
                p.acrescimo,
                p.observacao,
                p.estado_compra,
                to_char(p.data_cadastro,    'DD/MM/YYYY HH24:MI:SS')       AS criado_em,
                to_char(p.data_atualizacao, 'DD/MM/YYYY HH24:MI:SS')       AS atualizado_em
            ")
            ->from('purchase', 'p')
            ->leftJoin('p', 'supplier', 's', 'p.id_fornecedor = s.id');

            if (!is_null($term) && $term !== '') {
                $query->setParameter('term', '%' . $term . '%');
                $query->where('CAST(p.id AS TEXT) ILIKE :term')
                    ->orWhere('s.nome_fantasia ILIKE :term')
                    ->orWhere('CAST(p.estado_compra AS TEXT) ILIKE :term')
                    ->orWhere("TO_CHAR(p.data_cadastro, 'DD/MM/YYYY HH24:MI:SS') ILIKE :term");
            }

            $filteredRecords = (int) (clone $query)->select('COUNT(*)')->fetchOne();

            $purchases = $query
                ->orderBy($orderField, $orderType)
                ->setFirstResult($start)
                ->setMaxResults($length)
                ->fetchAllAssociative();

            $estadoLabel = [
                'EM_ANDAMENTO' => '<span class="badge bg-warning text-dark">Em andamento</span>',
                'RECEBIDO'     => '<span class="badge bg-success">Recebido</span>',
            ];

            $rows = [];
            foreach ($purchases as $key => $value) {
                $estado = $value['estado_compra'] ?? 'EM_ANDAMENTO';
                $rows[$key] = [
                    $value['id'],
                    $value['nome_fornecedor'] ?? '<span class="text-muted">Sem fornecedor</span>',
                    'R$ ' . number_format((float) $value['total_bruto'],   2, ',', '.'),
                    'R$ ' . number_format((float) $value['total_liquido'], 2, ',', '.'),
                    number_format((float) ($value['desconto']  ?? 0), 2, ',', '.') . '%',
                    number_format((float) ($value['acrescimo'] ?? 0), 2, ',', '.') . '%',
                    $value['observacao'] ?? '-',
                    $estadoLabel[$estado] ?? $estado,
                    $value['criado_em'],
                    "<td>
                        <button type='button' class='btn btn-sm btn-info' onclick='gerarPdfCompra({$value['id']})'>
                            <i class='fa-solid fa-file-pdf'></i> PDF
                        </button>
                        <button type='button' class='btn btn-sm btn-danger' onclick='ShowModal({$value['id']})'>
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

    public function pdf($request, $response, $args)
    {
        $id = $args['id'] ?? null;

        if (is_null($id)) {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o ID da compra.'], 403);
        }

        try {
            $qb = \App\Database\DB::select("
                p.id,
                p.observacao,
                p.total_bruto,
                p.total_liquido,
                p.desconto,
                p.acrescimo,
                p.estado_compra,
                s.nome_fantasia                                          AS nome_fornecedor,
                s.cnpj_cpf                                               AS cnpj_fornecedor,
                to_char(p.data_cadastro, 'DD/MM/YYYY HH24:MI:SS')       AS criado_em
            ")
            ->from('purchase', 'p')
            ->leftJoin('p', 'supplier', 's', 'p.id_fornecedor = s.id');

            $purchase = $qb
                ->where('p.id = ' . $qb->createPositionalParameter((int) $id, \Doctrine\DBAL\ParameterType::INTEGER))
                ->fetchAssociative();

            if (!$purchase) {
                return $this->json($response, ['status' => false, 'msg' => 'Compra não encontrada.'], 404);
            }

            $itens = \App\Database\DB::select("
                ip.id,
                ip.nome,
                ip.quantidade,
                ip.preco_unitario,
                ip.total_bruto,
                ip.total_liquido,
                ip.desconto,
                ip.acrescimo
            ")
            ->from('item_purchase', 'ip')
            ->where('ip.id_compra = :id')
            ->setParameter('id', (int) $id, \Doctrine\DBAL\ParameterType::INTEGER)
            ->fetchAllAssociative();

            return $this->json($response, [
                'status'   => true,
                'compra'   => $purchase,
                'itens'    => $itens,
            ], 200);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage()], 500);
        }
    }

    public function delete($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o código da compra.', 'id' => 0], 403);
        }

        try {
            $deleted = \App\Database\DB::connection()->delete('purchase', ['id' => (int) $id]);

            if (!$deleted) {
                return $this->json($response, ['status' => false, 'msg' => 'Nenhum registro removido.', 'id' => $id], 403);
            }

            return $this->json($response, ['status' => true, 'msg' => 'Compra removida com sucesso!', 'id' => $id]);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
}