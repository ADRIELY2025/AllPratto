<?php

declare(strict_types=1);

namespace app\controller;

final class Company extends Base
{
    public function list($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('list-company'), [
                'titulo' => 'Lista de empresas',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function details($request, $response, $args)
    {
        $id = $args['id'] ?? null;
        $action = ($id === null) ? 'c' : 'e';
        $company = [];

        if (!is_null($id)) {
            $qb = \app\database\DB::select('*')->from('company');

            $company = $qb
                ->where('id = ' . $qb->createPositionalParameter($id, \Doctrine\DBAL\ParameterType::INTEGER))
                ->fetchAssociative();
        }

        return $this->getTwig()
            ->render($response, $this->setView('company'), [
                'titulo' => 'Detalhes da empresa',
                'id' => $id,
                'action' => $action,
                'company' => $company
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function insert($request, $response)
    {
        $form = $request->getParsedBody();

        $FieldsAndValues = [
            'nome_fantasia' => $form['nomeExibicao'] ?? '',
            'razao_social' => $form['nomeLegal'] ?? '',
            'cnpj' => $form['cnpj'] ?? '',
            'inscricao_estadual' => $form['inscricaoEstadual'] ?? '',
            'telefone' => $form['telefone'] ?? '',
            'email' => $form['email'] ?? '',
            'endereco' => $form['endereco'] ?? '',
            'numero' => $form['numero'] ?? '',
            'bairro' => $form['bairro'] ?? '',
            'cidade' => $form['cidade'] ?? '',
            'estado' => $form['estado'] ?? '',
            'cep' => $form['cep'] ?? '',
            'ativo' => ($form['ativo'] === 'true') ? true : false
        ];

        try {
            $IsInserted = \app\database\DB::connection()->insert('company', $FieldsAndValues);

            if (!$IsInserted) {
                return $this->json($response, [
                    'status' => false,
                    'msg' => 'Restrição: ' . $IsInserted,
                    'id' => 0
                ], 500);
            }

            $id = \app\database\DB::select('id')
                ->from('company')
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
            'cnpj' => $form['cnpj'] ?? null,
            'inscricao_estadual' => $form['inscricaoEstadual'] ?? null,
            'telefone' => $form['telefone'] ?? null,
            'email' => $form['email'] ?? null,
            'endereco' => $form['endereco'] ?? null,
            'numero' => $form['numero'] ?? null,
            'bairro' => $form['bairro'] ?? null,
            'cidade' => $form['cidade'] ?? null,
            'estado' => $form['estado'] ?? null,
            'cep' => $form['cep'] ?? null,
            'ativo' => ($form['ativo'] === 'true') ? true : false
        ];

        try {
            $IsUpdated = \app\database\DB::connection()->update(
                'company',
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
                'msg' => 'Informe o código da empresa',
                'id' => 0
            ], 403);
        }

        try {
            $IsDeleted = \app\database\DB::connection()->delete('company', ['id' => $id]);

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
            0  => 'id',
            1  => 'nome_fantasia',
            2  => 'razao_social',
            3  => 'cnpj',
            4  => 'telefone',
            5  => 'email',
            6  => 'cidade',
            7  => 'estado',
            8  => 'criado_em',
            9  => 'atualizado_em',
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
                ->from('company')
                ->fetchOne();

            $query = \app\database\DB::select('*')->from('company');

            if (!is_null($term) && $term !== '') {
                $query->setParameter('term', '%' . $term . '%');

                $query->where('CAST(id AS TEXT) ILIKE :term')
                    ->orWhere('nome_fantasia ILIKE :term')
                    ->orWhere('razao_social ILIKE :term')
                    ->orWhere('cnpj ILIKE :term')
                    ->orWhere('telefone ILIKE :term')
                    ->orWhere('email ILIKE :term')
                    ->orWhere('cidade ILIKE :term')
                    ->orWhere('estado ILIKE :term')
                    ->orWhere("TO_CHAR(criado_em, 'DD/MM/YYYY HH24:MI:SS') ILIKE :term")
                    ->orWhere("TO_CHAR(atualizado_em, 'DD/MM/YYYY HH24:MI:SS') ILIKE :term");
            }

            $filteredRecords = (int) (clone $query)
                ->select('COUNT(*)')
                ->fetchOne();

            $companies = $query
                ->orderBy($orderField, $orderType)
                ->setFirstResult($start)
                ->setMaxResults($length)
                ->fetchAllAssociative();

            $rows = [];

            foreach ($companies as $key => $value) {
                $rows[$key] = [
                    $value['id'],
                    $value['nome_fantasia'],
                    $value['razao_social'],
                    $value['cnpj'],
                    $value['telefone'],
                    $value['email'],
                    $value['cidade'],
                    $value['estado'],
                    ($value['ativo'] === true) ? 'Ativo' : 'Inativo',
                    (new \DateTime($value['criado_em']))->format('d/m/Y H:i:s'),
                    (new \DateTime($value['atualizado_em']))->format('d/m/Y H:i:s'),
                    "<td>
                        <a class='btn btn-sm btn-warning' href='/company/detalhes/" . $value['id'] . "'>
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