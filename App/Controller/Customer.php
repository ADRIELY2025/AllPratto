<?php

declare(strict_types=1);

namespace App\Controller;

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
        $id     = $args['id'] ?? null;
        $action = ($id === null) ? 'c' : 'e';
        $customer = [];

        if (!is_null($id)) {
            $qb = \App\Database\DB::select('*')->from('customer');
            $customer = $qb
                ->where('id = ' . $qb->createPositionalParameter($id, \Doctrine\DBAL\ParameterType::INTEGER))
                ->fetchAssociative();
        }

        return $this->getTwig()
            ->render($response, $this->setView('customer'), [
                'titulo'   => 'Detalhes do cliente',
                'id'       => $id,
                'action'   => $action,
                'customer' => $customer,
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function insert($request, $response)
    {
        $form = $request->getParsedBody();

        $data = [
            'nome_fantasia'      => $form['nome']      ?? '',
            'sobrenome_razao'    => $form['sobrenomeRazao']          ?? '',
            'cpf_cnpj'           => preg_replace('/\D/', '', $form['cpfcnpj'] ?? ''),
            'rg_ie'              => $form['rgie'] ?? '',
            'nascimento_fundacao'=> $this->convertBrDateToDatabaseFormat($form['dataRegistro'] ?? ''),
            'ativo'              => (($form['ativo'] ?? 'false') === 'true') ? true : false,
        ];

        try {
            $conn = \App\Database\DB::connection();
            $conn->insert('customer', $data);
            $id = (int) $conn->lastInsertId();

            if (!$id) {
                return $this->json($response, ['status' => false, 'msg' => 'Não foi possível obter o ID do registro.', 'id' => 0], 500);
            }

            return $this->json($response, ['status' => true, 'msg' => 'Salvo com sucesso!', 'id' => $id], 201);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function update($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Por favor informe o ID do registro', 'id' => 0], 403);
        }

        $data = [
            'nome_fantasia'      => $form['nome']      ?? null,
            'sobrenome_razao'    => $form['sobrenomeRazao']          ?? null,
            'cpf_cnpj'           => preg_replace('/\D/', '', $form['cpfcnpj'] ?? ''),
            'rg_ie'              => $form['rgie'] ?? null,
            'nascimento_fundacao'=> $this->convertBrDateToDatabaseFormat($form['dataRegistro'] ?? ''),
            'ativo'              => (($form['ativo'] ?? 'false') === 'true') ? true : false,
            'atualizado_em'      => date('Y-m-d H:i:s'),
        ];

        try {
            $updated = \App\Database\DB::connection()->update('customer', $data, ['id' => (int) $id]);

            if (!$updated) {
                return $this->json($response, ['status' => false, 'msg' => 'Nenhum registro alterado.', 'id' => 0], 403);
            }

            return $this->json($response, ['status' => true, 'msg' => 'Alterado com sucesso!', 'id' => $id], 200);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function delete($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o código do cliente', 'id' => 0], 403);
        }

        try {
            $deleted = \App\Database\DB::connection()->delete('customer', ['id' => (int) $id]);

            if (!$deleted) {
                return $this->json($response, ['status' => false, 'msg' => 'Nenhum registro removido.', 'id' => $id], 403);
            }

            return $this->json($response, ['status' => true, 'msg' => 'Removido com sucesso!', 'id' => $id]);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function listingdata($request, $response)
    {
        $form   = $request->getParsedBody();
        $term   = $form['search']['value'] ?? null;
        $start  = (int) ($form['start']  ?? 0);
        $length = (int) ($form['length'] ?? 10);

        $columns = [
            0 => 'id',
            1 => 'nome_fantasia',
            2 => 'cpf_cnpj',
            3 => 'nascimento_fundacao',
            4 => 'ativo',
            5 => 'criado_em',
            6 => 'atualizado_em',
        ];

        $posField   = (isset($form['order'][0]['column']) && isset($columns[(int) $form['order'][0]['column']]))
            ? (int) $form['order'][0]['column']
            : 0;
        $orderType  = strtoupper($form['order'][0]['dir'] ?? 'DESC');
        $orderType  = in_array($orderType, ['ASC', 'DESC'], true) ? $orderType : 'DESC';
        $orderField = $columns[$posField];

        try {
            $totalRecords = (int) \App\Database\DB::select('COUNT(*)')
                ->from('customer')
                ->fetchOne();

            $query = \App\Database\DB::select("
                id,
                nome_fantasia,
                sobrenome_razao,
                cpf_cnpj,
                rg_ie,
                to_char(nascimento_fundacao, 'DD/MM/YYYY')            AS nascimento_fundacao,
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
                    ->orWhere('rg_ie ILIKE :term')
                    ->orWhere("TO_CHAR(nascimento_fundacao, 'DD/MM/YYYY') ILIKE :term")
                    ->orWhere("TO_CHAR(criado_em,           'DD/MM/YYYY HH24:MI:SS') ILIKE :term")
                    ->orWhere("TO_CHAR(atualizado_em,       'DD/MM/YYYY HH24:MI:SS') ILIKE :term");
            }

            $filteredRecords = (int) (clone $query)->select('COUNT(*)')->fetchOne();

            $customers = $query
                ->orderBy($orderField, $orderType)
                ->setFirstResult($start)
                ->setMaxResults($length)
                ->fetchAllAssociative();

            $rows = [];
            foreach ($customers as $key => $value) {
                $cpfCnpj        = $value['cpf_cnpj']        ?? '';
                $nomeFantasia   = $value['nome_fantasia']   ?? '';
                $sobrenomeRazao = $value['sobrenome_razao'] ?? '';

                // CPF tem até 14 chars com máscara; CNPJ tem 18
                $nomeCompleto = (strlen(preg_replace('/\D/', '', $cpfCnpj)) <= 11)
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
                    <a class='btn btn-sm btn-warning' href='/cliente/detalhes/" . $value['id'] . "'><i class='fa-solid fa-pen-to-square'></i> Editar</a>
                    <button type='button' class='btn btn-sm btn-danger' onclick='ShowModal(" . $value['id'] . ");'><i class='fa-solid fa-trash'></i> Excluir</button>
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
                'msg'    => 'Erro: ' . $e->getMessage(),
                'id'     => 0,
            ], 500);
        }
    }
}