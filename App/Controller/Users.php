<?php

declare(strict_types=1);

namespace App\Controller;

use App\Trait\PasswordGenerator;

final class Users extends Base
{
    use PasswordGenerator;

    public function list($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('list-users'), [
                'titulo' => 'Lista de usuários',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function details($request, $response, $args)
    {
        $id = $args['id'] ?? null;
        $action = ($id === null) ? 'c' : 'e';
        $user = [];

        if (!is_null($id)) {
            $qb = \App\Database\DB::select('*')->from('users');

            $user = $qb
                ->where('id = ' . $qb->createPositionalParameter($id, \Doctrine\DBAL\ParameterType::INTEGER))
                ->fetchAssociative();
        }

        return $this->getTwig()
            ->render($response, $this->setView('users'), [
                'titulo' => 'Detalhes do usuário',
                'id' => $id,
                'action' => $action,
                'user' => $user
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function insert($request, $response)
    {
        $form = $request->getParsedBody();

        $FieldsAndValues = [
            'nome' => $form['nome'] ?? '',
            'cpf' => preg_replace('/\D/', '', $form['cpf'] ?? ''),
            'rg' => $form['rg'] ?? '',
            'senha' => password_hash($form['senha'], PASSWORD_DEFAULT),
            'ativo' => ($form['ativo'] === 'true') ? true : false
        ];

        try {
            $IsInserted = \App\Database\DB::connection()->insert('users', $FieldsAndValues);

            if (!$IsInserted) {
                return $this->json($response, [
                    'status' => false,
                    'msg' => 'Restrição: ' . $IsInserted,
                    'id' => 0
                ], 500);
            }

            $id = \App\Database\DB::select('id')
                ->from('users')
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
            'nome' => $form['nome'] ?? null,
            'cpf' => preg_replace('/\D/', '', $form['cpf'] ?? ''),
            'rg' => $form['rg'] ?? null,
            'ativo' => ($form['ativo'] === 'true') ? true : false
        ];

        if (!empty($form['senha'])) {
            $FieldsAndValues['senha'] = password_hash($form['senha'], PASSWORD_DEFAULT);
        }

        try {
            $IsUpdated = \App\Database\DB::connection()->update(
                'users',
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
                'msg' => 'Informe o código do usuário',
                'id' => 0
            ], 403);
        }

        try {
            $IsDeleted = \App\Database\DB::connection()->delete('users', ['id' => $id]);

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

    // -------------------------------------------------------------------------
    // Reseta a senha de um usuário (ação do administrador, a partir da lista
    // ou da tela de cadastro/edição). Gera uma nova senha aleatória, grava o
    // hash no banco e devolve a senha em texto puro (uma única vez) para o
    // administrador copiar e repassar ao usuário.
    // -------------------------------------------------------------------------
    public function resetPassword($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (is_null($id) || $id === '') {
            return $this->json($response, [
                'status' => false,
                'msg'    => 'Informe o código do usuário',
            ], 403);
        }

        try {
            $qb = \App\Database\DB::select('id', 'nome')->from('users');
            $qb->where('id = ' . $qb->createPositionalParameter($id, \Doctrine\DBAL\ParameterType::INTEGER));
            $user = $qb->fetchAssociative();

            if (!$user) {
                return $this->json($response, [
                    'status' => false,
                    'msg'    => 'Usuário não encontrado.',
                ], 404);
            }

            $novaSenha = $this->gerarSenhaSegura();

            $IsUpdated = \App\Database\DB::connection()->update(
                'users',
                [
                    'senha'         => password_hash($novaSenha, PASSWORD_DEFAULT),
                    'atualizado_em' => date('Y-m-d H:i:s'),
                ],
                ['id' => $user['id']]
            );

            if (!$IsUpdated) {
                return $this->json($response, [
                    'status' => false,
                    'msg'    => 'Não foi possível resetar a senha deste usuário.',
                ], 500);
            }

            return $this->json($response, [
                'status' => true,
                'msg'    => 'Senha resetada com sucesso! Copie a senha abaixo antes de fechar.',
                'senha'  => $novaSenha,
                'id'     => $user['id'],
            ], 200);
        } catch (\Exception $e) {
            error_log('[users][resetPassword] ' . $e->getMessage());
            return $this->json($response, [
                'status' => false,
                'msg'    => 'Restrição: ' . $e->getMessage(),
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
            1 => 'nome',
            2 => 'cpf',
            3 => 'rg',
            4 => 'id',   // email vem de MAX() na view — não pode ordenar, usa id como fallback
            5 => 'ativo',
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
            $totalRecords = (int) \App\Database\DB::select('COUNT(*)')
                ->from('vw_user')
                ->fetchOne();

            $query = \App\Database\DB::select('*')->from('vw_user');

            if (!is_null($term) && $term !== '') {
                $query->setParameter('term', '%' . $term . '%');

                $query->where('CAST(id AS TEXT) ILIKE :term')
                    ->orWhere('nome ILIKE :term')
                    ->orWhere('email ILIKE :term')
                    ->orWhere("TO_CHAR(criado_em, 'DD/MM/YYYY HH24:MI:SS') ILIKE :term")
                    ->orWhere("TO_CHAR(atualizado_em, 'DD/MM/YYYY HH24:MI:SS') ILIKE :term");
            }

            $filteredRecords = (int) (clone $query)
                ->select('COUNT(*)')
                ->fetchOne();

            $users = $query
                ->orderBy($orderField, $orderType)
                ->setFirstResult($start)
                ->setMaxResults($length)
                ->fetchAllAssociative();

            $rows = [];

            foreach ($users as $key => $value) {
                $rows[$key] = [
                    $value['id'],
                    $value['nome'],
                    $value['cpf'] ?? '',
                    $value['rg'] ?? '',
                    $value['email'] ?? '',
                    ((isset($value['ativo']) && ($value['ativo'] === true || $value['ativo'] == 1)) ? 'Ativo' : 'Inativo'),
                    (new \DateTime($value['criado_em']))->format('d/m/Y H:i:s'),
                    (new \DateTime($value['atualizado_em']))->format('d/m/Y H:i:s'),
                    "<td>
                        <a class='btn btn-sm btn-warning' href='/users/detalhes/" . $value['id'] . "'>
                            <i class='fa-solid fa-pen-to-square'></i> Editar
                        </a>

                        <button
                            type='button'
                            class='btn btn-sm btn-info'
                            onclick='ShowResetPasswordModal(" . $value['id'] . ");'>
                            <i class='fa-solid fa-key'></i> Resetar senha
                        </button>

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