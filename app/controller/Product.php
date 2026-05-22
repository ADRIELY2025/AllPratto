<?php

declare(strict_types=1);

namespace app\controller;

final class Product extends Base
{
    public function list($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('list-product'), [
                'titulo' => 'Lista de produtos',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function details($request, $response, $args)
    {
        $id = $args['id'] ?? null;
        $action = ($id === null) ? 'c' : 'e';
        $product = [];

        if (!is_null($id)) {
            $qb = \app\database\DB::select('*')->from('product');

            $product = $qb
                ->where('id = ' . $qb->createPositionalParameter($id, \Doctrine\DBAL\ParameterType::INTEGER))
                ->fetchAssociative();
        }

        return $this->getTwig()
            ->render($response, $this->setView('product'), [
                'titulo' => 'Detalhes do produto',
                'id' => $id,
                'action' => $action,
                'product' => $product
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function insert($request, $response)
    {
        $form = $request->getParsedBody();

        $FieldsAndValues = [
            'descricao' => $form['descricao'] ?? '',
            'codigo_barras' => $form['codigoBarras'] ?? '',
            'sku' => $form['sku'] ?? '',
            'valor_custo' => str_replace(',', '.', $form['valorCusto'] ?? 0),
            'valor_venda' => str_replace(',', '.', $form['valorVenda'] ?? 0),
            'estoque' => (int) ($form['estoque'] ?? 0),
            'ativo' => ($form['ativo'] === 'true') ? true : false
        ];

        try {
            $IsInserted = \app\database\DB::connection()->insert('product', $FieldsAndValues);

            if (!$IsInserted) {
                return $this->json($response, [
                    'status' => false,
                    'msg' => 'Restrição: ' . $IsInserted,
                    'id' => 0
                ], 500);
            }

            $id = \app\database\DB::select('id')
                ->from('product')
                ->orderBy('id', 'DESC')
                ->setMaxResults(1)
                ->fetchAssociative();

            return $this->json($response, [
                'status' => true,
                'msg' => 'Salvo com sucesso!',
                'id' => $id['id']
            ], 201);
        } catch (\Exception $e) {
            return $this->json($response, [
                'status' => false,
                'msg' => 'Restrição: ' . $e->getMessage(),
                'id' => 0
            ], 500);
        }
    }

    public function update($request, $response)
    {
        $form = $request->getParsedBody();

        $id = $form['id'] ?? null;

        if (is_null($id)) {
            return $this->json($response, [
                'status' => false,
                'msg' => 'Por favor informe o ID do registro',
                'id' => 0
            ], 403);
        }

        $FieldsAndValues = [
            'descricao' => $form['descricao'] ?? null,
            'codigo_barras' => $form['codigoBarras'] ?? null,
            'sku' => $form['sku'] ?? null,
            'valor_custo' => str_replace(',', '.', $form['valorCusto'] ?? 0),
            'valor_venda' => str_replace(',', '.', $form['valorVenda'] ?? 0),
            'estoque' => (int) ($form['estoque'] ?? 0),
            'ativo' => ($form['ativo'] === 'true') ? true : false
        ];

        try {
            $IsUpdated = \app\database\DB::connection()->update(
                'product',
                $FieldsAndValues,
                ['id' => $id]
            );

            if (!$IsUpdated) {
                return $this->json($response, [
                    'status' => false,
                    'msg' => 'Restrição: ' . $IsUpdated,
                    'id' => 0
                ], 403);
            }

            return $this->json($response, [
                'status' => true,
                'msg' => 'Alterado com sucesso!',
                'id' => $id
            ], 201);
        } catch (\Exception $e) {
            return $this->json($response, [
                'status' => false,
                'msg' => 'Restrição: ' . $e->getMessage(),
                'id' => 0
            ], 500);
        }
    }

    public function delete($request, $response)
    {
        $form = $request->getParsedBody();

        $id = $form['id'] ?? null;

        if (is_null($id) || $id === '') {
            return $this->json($response, [
                'status' => false,
                'msg' => 'Informe o código do produto',
                'id' => 0
            ], 403);
        }

        try {
            $IsDeleted = \app\database\DB::connection()->delete('product', ['id' => $id]);

            if (!$IsDeleted) {
                return $this->json($response, [
                    'status' => false,
                    'msg' => 'Restrição: ' . $IsDeleted,
                    'id' => $id
                ], 403);
            }

            return $this->json($response, [
                'status' => true,
                'msg' => 'Removido com sucesso!',
                'id' => $id
            ]);
        } catch (\Exception $e) {
            return $this->json($response, [
                'status' => false,
                'msg' => 'Restrição: ' . $e->getMessage(),
                'id' => 0
            ], 500);
        }
    }

    public function listingdata($request, $response)
    {
        $form = $request->getParsedBody();

        $term   = $form['search']['value'] ?? null;
        $start  = (int) ($form['start'] ?? 0);
        $length = (int) ($form['length'] ?? 10);

        $columns = [
            0 => 'id',
            1 => 'descricao',
            2 => 'codigo_barras',
            3 => 'sku',
            4 => 'valor_custo',
            5 => 'valor_venda',
            6 => 'estoque',
            7 => 'criado_em',
            8 => 'atualizado_em',
        ];

        $posField = (
            isset($form['order'][0]['column']) &&
            isset($columns[(int) $form['order'][0]['column']])
        )
            ? (int) $form['order'][0]['column']
            : 0;

        $orderType = strtoupper($form['order'][0]['dir'] ?? 'DESC');
        $orderType = in_array($orderType, ['ASC', 'DESC'], true)
            ? $orderType
            : 'DESC';

        $orderField = $columns[$posField];

        try {
            $totalRecords = (int) \app\database\DB::select('COUNT(*)')
                ->from('product')
                ->fetchOne();

            $query = \app\database\DB::select('*')->from('product');

            if (!is_null($term) && $term !== '') {
                $query->setParameter('term', '%' . $term . '%');

                $query->where('CAST(id AS TEXT) ILIKE :term')
                    ->orWhere('descricao ILIKE :term')
                    ->orWhere('codigo_barras ILIKE :term')
                    ->orWhere('sku ILIKE :term')
                    ->orWhere("CAST(valor_custo AS TEXT) ILIKE :term")
                    ->orWhere("CAST(valor_venda AS TEXT) ILIKE :term")
                    ->orWhere("CAST(estoque AS TEXT) ILIKE :term")
                    ->orWhere("TO_CHAR(criado_em, 'DD/MM/YYYY HH24:MI:SS') ILIKE :term")
                    ->orWhere("TO_CHAR(atualizado_em, 'DD/MM/YYYY HH24:MI:SS') ILIKE :term");
            }

            $filteredRecords = (int) (clone $query)
                ->select('COUNT(*)')
                ->fetchOne();

            $products = $query
                ->orderBy($orderField, $orderType)
                ->setFirstResult($start)
                ->setMaxResults($length)
                ->fetchAllAssociative();

            $rows = [];

            foreach ($products as $key => $value) {
                $rows[$key] = [
                    $value['id'],
                    $value['descricao'],
                    $value['codigo_barras'],
                    $value['sku'],
                    'R$ ' . number_format((float) $value['valor_custo'], 2, ',', '.'),
                    'R$ ' . number_format((float) $value['valor_venda'], 2, ',', '.'),
                    $value['estoque'],
                    ($value['ativo'] === true) ? 'Ativo' : 'Inativo',
                    (new \DateTime($value['criado_em']))->format('d/m/Y H:i:s'),
                    (new \DateTime($value['atualizado_em']))->format('d/m/Y H:i:s'),
                    "<td>
                        <a class='btn btn-sm btn-warning' href='/product/detalhes/" . $value['id'] . "'>
                            <i class='fa-solid fa-pen-to-square'></i> Editar
                        </a>

                        <button
                            type='button'
                            class='btn btn-sm btn-danger'
                            onclick='ShowModal(" . $value['id'] . ");'>
                            <i class='fa-solid fa-trash'></i> Excluir
                        </button>
                    </td>",
                ];
            }

            return $this->json($response, [
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $rows,
            ], 200);
        } catch (\Exception $e) {
            return $this->json($response, [
                'status' => false,
                'msg' => 'Restrição: ' . $e->getMessage(),
                'id' => 0,
            ], 500);
        }
    }
}