<?php

declare(strict_types=1);

namespace app\controller;

final class Customer extends Base
{
    public function list($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('list-customer'), [
                'titulo' => 'Lista de clientes',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function details($request, $response, $args)
    {
        $id = $args['id'] ?? null;
        $action = ($id === null) ? 'c' : 'e';
        $customer = [];
        if (!is_null($id)) {
            $qb = \app\database\DB::select('*')->from('customer');

            $customer = $qb
                ->where('id = ' . $qb->createPositionalParameter($id, \Doctrine\DBAL\ParameterType::INTEGER))
                ->fetchAssociative();
        }
        return $this->getTwig()
            ->render($response, $this->setView('customer'), [
                'titulo' => 'Detalhes do cliente',
                'id' => $id,
                'action' => $action,
                'customer' => $customer
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function insert($request, $response)
    {
        $form = $request->getParsedBody();
        $FieldsAndValues = [
            'nome_fantasia' => $form['nomeExibicao'],
            'sobrenome_razao' => $form['nomeLegal'] ?? '',
            'cpf_cnpj' => $form['numeroDocumento'] ?? '',
            'inscricao_estadual' => $form['registroSecundario'] ?? '',
            'nascimento_fundacao' => $this->convertBrDateToDatabaseFormat($form['dataRegistro']),
            'ativo' => ($form['ativo'] === 'true') ? true : false
        ];
        try {
            $IsInserted = \app\database\DB::connection()->insert('customer', $FieldsAndValues);
            if (!$IsInserted) {
                return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $IsInserted, 'id' => 0], 500);
            }
            $id = \app\database\DB::select('id')->from('customer')->fetchAssociative();

            return $this->json($response, ['status' => true, 'msg' => 'Salvo com sucesso!', 'id' => $id['id']], 201);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
    public function update($request, $response)
    {
        $form = $request->getParsedBody();
        $id = $form['id'] ?? null;
        if (is_null($id)) {
            return $this->json($response, ['status' => false, 'msg' => 'Por favor informe o ID do registro', 'id' => 0], 403);
        }
        $FieldsAndValues = [
            'nome_fantasia' => $form['nomeExibicao'] ?? null,
            'sobrenome_razao' => $form['nomeLegal'] ?? null,
            'cpf_cnpj' => $form['numeroDocumento'] ?? null,
            'inscricao_estadual' => $form['registroSecundario'] ?? null,
            'nascimento_fundacao' => $this->convertBrDateToDatabaseFormat($form['dataRegistro']),
            'ativo' => ($form['ativo'] === 'true') ? true : false
        ];
        try {
            $IsUpdated = \app\database\DB::connection()->update('customer', $FieldsAndValues, ['id' => $id]);
            if (!$IsUpdated) {
                return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $IsUpdated, 'id' => 0], 403);
            }
            return $this->json($response, ['status' => true, 'msg' => 'Alterado com sucesso!', 'id' => $id], 201);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
    public function delete($request, $response)
    {
        $form = $request->getParsedBody();
        $id = $form['id'] ?? null;
        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o código do cliente', 'id' => 0], 403);
        }
        try {
            $IsDeleted = \app\database\DB::connection()->delete('customer', ['id' => $id]);
            if (!$IsDeleted) {
                return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $IsDeleted, 'id' => $id], 403);
            }
            return $this->json($response, ['status' => true, 'msg' => 'Removido com sucesso!', 'id' => $id]);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
    public function listingdata($request, $response)
    {
        $form = $request->getParsedBody();

        $term   = $form['search']['value'] ?? null;
        $start  = (int) ($form['start']  ?? 0);
        $length = (int) ($form['length'] ?? 10);

        # Whitelist de colunas — proteção contra SQL injection no orderBy
        $columns = [
            0 => 'id',
            1 => 'nome_fantasia',
            2 => 'cpf_cnpj',
            3 => 'inscricao_estadual',
            4 => 'nascimento_fundacao',
            5 => 'criado_em',
            6 => 'atualizado_em',
        ];

        $posField = (isset($form['order'][0]['column']) && isset($columns[(int) $form['order'][0]['column']]))
            ? (int) $form['order'][0]['column']
            : 0;

        # Validação da direção evita SQL injection no ORDER BY
        $orderType  = strtoupper($form['order'][0]['dir'] ?? 'DESC');
        $orderType  = in_array($orderType, ['ASC', 'DESC'], true) ? $orderType : 'DESC';
        $orderField = $columns[$posField];

        try {
            # Total geral DataTables: recordsTotal
            $totalRecords = (int) \app\database\DB::select('COUNT(*)')
                ->from('customer')
                ->fetchOne();

            # Query principal com WHERE opcional
            $query = \app\database\DB::select("
                id,
                nome_fantasia,
                sobrenome_razao,
                cpf_cnpj,
                to_char(nascimento_fundacao, 'DD/MM/YYYY')        AS nascimento_fundacao,
                ativo,
                to_char(criado_em,           'DD/MM/YYYY HH24:MI:SS') AS criado_em,
                to_char(atualizado_em,       'DD/MM/YYYY HH24:MI:SS') AS atualizado_em
            ")->from('customer');

            if (!is_null($term) && $term !== '') {
                $query->setParameter('term', '%' . $term . '%');

                $query->where('CAST(id AS TEXT) ILIKE :term')
                    ->orWhere('nome_fantasia ILIKE :term')
                    ->orWhere('sobrenome_razao ILIKE :term')
                    ->orWhere('cpf_cnpj ILIKE :term')
                    ->orWhere('inscricao_estadual ILIKE :term')
                    ->orWhere("TO_CHAR(nascimento_fundacao, 'DD/MM/YYYY') ILIKE :term")
                    ->orWhere("TO_CHAR(criado_em, 'DD/MM/YYYY HH24:MI:SS') ILIKE :term")
                    ->orWhere("TO_CHAR(atualizado_em, 'DD/MM/YYYY HH24:MI:SS') ILIKE :term");
            }

            # Total com filtro aplicado — clona o query e troca o SELECT por COUNT
            $filteredRecords = (int) (clone $query)
                ->select('COUNT(*)')
                ->fetchOne();

            # Resultados paginados e ordenados
            $customers = $query
                ->orderBy($orderField, $orderType)
                ->setFirstResult($start)
                ->setMaxResults($length)
                ->fetchAllAssociative();

            # Formatação para o DataTables
            $rows = [];
            foreach ($customers as $key => $value) {
                $cpfCnpj        = $value['cpf_cnpj']         ?? '';
                $nomeFantasia   = $value['nome_fantasia']    ?? '';
                $sobrenomeRazao = $value['sobrenome_razao']  ?? '';

                $nomeCompleto = (strlen($cpfCnpj) <= 14)
                    ? trim($nomeFantasia . ' ' . $sobrenomeRazao)
                    : $nomeFantasia;

                $rows[$key] = [
                    $value['id'],
                    $nomeCompleto,
                    $cpfCnpj,
                    $value['nascimento_fundacao'],
                    ($value['ativo'] === true) ? 'Ativo' : 'Inativo',
                    $value['criado_em'],
                    $value['atualizado_em'],
                    "<td>
                    <a class='btn btn-sm btn-warning' href='/cliente/detalhes/" . $value['id'] . "'> <i class='fa-solid fa-pen-to-square'></i> Editar</a>
                    <button type='button' class='btn btn-sm btn-danger' onclick='ShowModal(" . $value['id'] . ");'> <i class='fa-solid fa-trash'></i> Excluir</button>
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
                'msg'    => 'Restrição: ' . $e->getMessage(),
                'id'     => 0,
            ], 500);
        }
    }
}