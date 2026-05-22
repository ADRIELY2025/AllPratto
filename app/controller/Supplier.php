<?php

declare(strict_types=1);

namespace app\controller;

final class Supplier extends Base
{
    public function list($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('list-supplier'), [
                'titulo' => 'Lista de fornecedores',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function details($request, $response, $args)
    {
        $id = $args['id'] ?? null;
        $action = ($id === null) ? 'c' : 'e';
        $supplier = [];

        if (!is_null($id)) {
            $qb = \app\database\DB::select('*')->from('supplier');

            $supplier = $qb
                ->where('id = ' . $qb->createPositionalParameter($id, \Doctrine\DBAL\ParameterType::INTEGER))
                ->fetchAssociative();
        }

        return $this->getTwig()
            ->render($response, $this->setView('supplier'), [
                'titulo' => 'Detalhes do fornecedor',
                'id' => $id,
                'action' => $action,
                'supplier' => $supplier
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function insert($request, $response)
    {
        $form = $request->getParsedBody();

        $FieldsAndValues = [
            'nome_fantasia' => $form['nomeExibicao'],
            'razao_social' => $form['nomeLegal'] ?? '',
            'cnpj' => $form['numeroDocumento'] ?? '',
            'inscricao_estadual' => $form['registroSecundario'] ?? '',
            'telefone' => $form['telefone'] ?? '',
            'email' => $form['email'] ?? '',
            'ativo' => ($form['ativo'] === 'true') ? true : false
        ];

        try {
            $IsInserted = \app\database\DB::connection()->insert('supplier', $FieldsAndValues);

            if (!$IsInserted) {
                return $this->json($response, [
                    'status' => false,
                    'msg' => 'Restrição: ' . $IsInserted,
                    'id' => 0
                ], 500);
            }

            $id = \app\database\DB::select('id')
                ->from('supplier')
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
            'nome_fantasia' => $form['nomeExibicao'] ?? null,
            'razao_social' => $form['nomeLegal'] ?? null,
            'cnpj' => $form['numeroDocumento'] ?? null,
            'inscricao_estadual' => $form['registroSecundario'] ?? null,
            'telefone' => $form['telefone'] ?? null,
            'email' => $form['email'] ?? null,
            'ativo' => ($form['ativo'] === 'true') ? true : false
        ];

        try {
            $IsUpdated = \app\database\DB::connection()->update(
                'supplier',
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
                'msg' => 'Informe o código do fornecedor',
                'id' => 0
            ], 403);
        }

        try {
            $IsDeleted = \app\database\DB::connection()->delete('supplier', ['id' => $id]);

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
            1 => 'nome_fantasia',
            2 => 'razao_social',
            3 => 'cnpj',
            4 => 'telefone',
            5 => 'email',
            6 => 'criado_em',
            7 => 'atualizado_em',
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
                ->from('supplier')
                ->fetchOne();

            $query = \app\database\DB::select('*')->from('supplier');

            if (!is_null($term) && $term !== '') {
                $query->setParameter('term', '%' . $term . '%');

                $query->where('CAST(id AS TEXT) ILIKE :term')
                    ->orWhere('nome_fantasia ILIKE :term')
                    ->orWhere('razao_social ILIKE :term')
                    ->orWhere('cnpj ILIKE :term')
                    ->orWhere('telefone ILIKE :term')
                    ->orWhere('email ILIKE :term')
                    ->orWhere("TO_CHAR(criado_em, 'DD/MM/YYYY HH24:MI:SS') ILIKE :term")
                    ->orWhere("TO_CHAR(atualizado_em, 'DD/MM/YYYY HH24:MI:SS') ILIKE :term");
            }

            $filteredRecords = (int) (clone $query)
                ->select('COUNT(*)')
                ->fetchOne();

            $suppliers = $query
                ->orderBy($orderField, $orderType)
                ->setFirstResult($start)
                ->setMaxResults($length)
                ->fetchAllAssociative();

            $rows = [];

            foreach ($suppliers as $key => $value) {
                $rows[$key] = [
                    $value['id'],
                    $value['nome_fantasia'],
                    $value['razao_social'],
                    $value['cnpj'],
                    $value['telefone'],
                    $value['email'],
                    ($value['ativo'] === true) ? 'Ativo' : 'Inativo',
                    (new \DateTime($value['criado_em']))->format('d/m/Y H:i:s'),
                    (new \DateTime($value['atualizado_em']))->format('d/m/Y H:i:s'),
                    "<td>
                        <a class='btn btn-sm btn-warning' href='/supplier/detalhes/" . $value['id'] . "'>
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