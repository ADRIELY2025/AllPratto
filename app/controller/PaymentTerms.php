<?php

declare(strict_types=1);

namespace app\controller;

use app\database\DB;

final class PaymentTerms extends Base
{
    public function form($request, $response, $args)
    {
        $id = $args['id'] ?? null;
        $paymentTerms = [
            'codigo' => '',
            'titulo' => '',
        ];
        $installments = [];

        if (!is_null($id)) {
            $paymentTerms = DB::select('*')
                ->from('payment_terms')
                ->where('id = :id')
                ->setParameter('id', $id)
                ->fetchAssociative() ?: $paymentTerms;

            $installments = DB::select('*')
                ->from('installment')
                ->where('id_pagamento = :id')
                ->setParameter('id', $id)
                ->orderBy('parcela', 'ASC')
                ->fetchAllAssociative();
        }

        return $this->getTwig()
            ->render($response, $this->setView('paymentterms'), [
                'titulo' => 'Condição de Pagamento',
                'id' => $id,
                'paymentTerms' => $paymentTerms,
                'installments' => $installments,
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function insert($request, $response)
    {
        $form = $request->getParsedBody();
        if (empty($form['codigo']) || empty($form['titulo'])) {
            return $this->json($response, [
                'status' => false,
                'msg' => 'Preencha corretamente os dados para salvar',
                'id' => null,
                'data' => [],
            ], 400);
        }

        $clean = $this->sanitize($form);

        try {
            DB::connection()->insert('payment_terms', $clean);
            $id = (int) DB::connection()->fetchOne("SELECT currval(pg_get_serial_sequence('payment_terms', 'id'))");

            return $this->json($response, [
                'status' => true,
                'msg' => 'Salvo com sucesso!',
                'id' => $id,
                'data' => [$clean],
            ], 201);
        } catch (\Exception $e) {
            return $this->json($response, [
                'status' => false,
                'msg' => 'Erro: ' . $e->getMessage(),
                'id' => null,
                'data' => [],
            ], 500);
        }
    }

    public function update($request, $response)
    {
        $form = $request->getParsedBody();
        $id = $form['id'] ?? null;

        if (empty($id)) {
            return $this->json($response, [
                'status' => false,
                'msg' => 'ID é obrigatório',
                'id' => null,
                'data' => [],
            ], 400);
        }

        try {
            $clean = $this->sanitize($form);
            $affectedRows = DB::connection()->update('payment_terms', $clean, ['id' => $id]);

            return $this->json($response, [
                'status' => $affectedRows > 0,
                'msg' => $affectedRows > 0 ? 'Atualizado com sucesso!' : 'Registro não encontrado',
                'id' => $id,
                'data' => [],
            ], $affectedRows > 0 ? 200 : 404);
        } catch (\Exception $e) {
            return $this->json($response, [
                'status' => false,
                'msg' => 'Erro: ' . $e->getMessage(),
                'id' => null,
                'data' => [],
            ], 500);
        }
    }

    public function delete($request, $response)
    {
        $form = $request->getParsedBody();
        $id = $form['id'] ?? null;

        if (empty($id)) {
            return $this->json($response, [
                'status' => false,
                'msg' => 'ID é obrigatório',
            ], 400);
        }

        try {
            $affectedRows = DB::connection()->delete('payment_terms', ['id' => $id]);

            return $this->json($response, [
                'status' => $affectedRows > 0,
                'msg' => $affectedRows > 0 ? 'Deletado com sucesso!' : 'Registro não encontrado',
            ], $affectedRows > 0 ? 200 : 404);
        } catch (\Exception $e) {
            return $this->json($response, [
                'status' => false,
                'msg' => 'Erro: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function find($request, $response)
    {
        $form = $request->getParsedBody();
        $term = $form['term'] ?? '';
        $limit = (int) ($form['limit'] ?? 10);
        $offset = (int) ($form['offset'] ?? 0);

        try {
            $query = DB::select('*')->from('payment_terms');

            if ($term !== '') {
                $query->setParameter('term', '%' . $term . '%');
                $query->where('codigo ILIKE :term')
                    ->orWhere('titulo ILIKE :term');
            }

            $rows = $query
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->fetchAllAssociative();

            return $this->json($response, [
                'status' => true,
                'msg' => 'Encontrado!',
                'data' => $rows,
            ], 200);
        } catch (\Exception $e) {
            return $this->json($response, [
                'status' => false,
                'msg' => 'Erro: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    public function findById($request, $response, $args)
    {
        $id = $args['id'] ?? null;

        try {
            $result = DB::select('*')
                ->from('payment_terms')
                ->where('id = :id')
                ->setParameter('id', $id)
                ->fetchAssociative();

            return $this->json($response, [
                'status' => true,
                'msg' => 'Encontrado!',
                'id' => $id,
                'data' => $result ? [$result] : [],
            ], 200);
        } catch (\Exception $e) {
            return $this->json($response, [
                'status' => false,
                'msg' => 'Erro: ' . $e->getMessage(),
                'id' => null,
                'data' => [],
            ], 500);
        }
    }

    public function findAll($request, $response)
    {
        try {
            $rows = DB::select('*')
                ->from('payment_terms')
                ->orderBy('id', 'ASC')
                ->fetchAllAssociative();

            return $this->json($response, [
                'status' => true,
                'data' => $rows,
            ], 200);
        } catch (\Exception $e) {
            return $this->json($response, [
                'status' => false,
                'msg' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    public function findInstallments($request, $response, $args)
    {
        $idPagamento = $args['id_pagamento'] ?? null;

        try {
            $rows = DB::select('*')
                ->from('installment')
                ->where('id_pagamento = :id_pagamento')
                ->setParameter('id_pagamento', $idPagamento)
                ->orderBy('parcela', 'ASC')
                ->fetchAllAssociative();

            return $this->json($response, [
                'status' => true,
                'data' => $rows,
            ], 200);
        } catch (\Exception $e) {
            return $this->json($response, [
                'status' => false,
                'msg' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    private function sanitize(array $data): array
    {
        $ignore = ['id', 'acao', 'parcela', 'intervalo', 'id_parcelamento'];
        $clean = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $ignore, true)) {
                continue;
            }
            if ($value === '' || $value === null || $value === []) {
                continue;
            }
            if ($value === 'true') {
                $clean[$key] = true;
                continue;
            }
            if ($value === 'false') {
                $clean[$key] = false;
                continue;
            }
            $clean[$key] = $value;
        }

        return $clean;
    }
}
