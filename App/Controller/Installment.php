<?php

declare(strict_types=1);

namespace App\Controller;

final class Installment extends Base
{
    public function insert($request, $response)
    {
        $form = $request->getParsedBody();

        $idPagamento = $form['id'] ?? null;
        $parcela     = isset($form['parcela'])   ? (int) $form['parcela']   : null;
        $intervalo   = isset($form['intervalo'])  ? (int) $form['intervalo'] : null;
        $valorTotal  = isset($form['valor_total']) ? (float) $form['valor_total'] : null;

        if (is_null($idPagamento) || $idPagamento === '') {
            return $this->json($response, [
                'status' => false,
                'msg'    => 'Informe o ID da condição de pagamento.',
                'id'     => 0,
            ], 403);
        }

        $FieldsAndValues = [
            'id_pagamento' => $idPagamento,
            'parcela'      => $parcela,
            'intervalo'    => $intervalo,
        ];

        try {
            \App\Database\DB::connection()->insert('installment', $FieldsAndValues);

            $id = \App\Database\DB::select('id')
                ->from('installment')
                ->orderBy('id', 'DESC')
                ->setMaxResults(1)
                ->fetchAssociative();

            return $this->json($response, [
                'status' => true,
                'msg'    => 'Parcela salva com sucesso!',
                'id'     => $id['id'],
            ], 201);
        } catch (\Exception $e) {
            return $this->json($response, [
                'status' => false,
                'msg'    => 'Erro: ' . $e->getMessage(),
                'id'     => 0,
            ], 500);
        }
    }

    public function list($request, $response)
    {
        $form        = $request->getParsedBody();
        $idPagamento = $form['id_pagamento'] ?? null;

        if (is_null($idPagamento) || $idPagamento === '') {
            return $this->json($response, [
                'status' => false,
                'msg'    => 'Informe o ID da condição de pagamento.',
                'data'   => [],
            ], 403);
        }

        try {
            $qb   = \App\Database\DB::select('*')->from('installment');
            $rows = $qb->where('id_pagamento = ' . $qb->createPositionalParameter((int) $idPagamento, \Doctrine\DBAL\ParameterType::INTEGER))
                ->orderBy('id', 'ASC')
                ->fetchAllAssociative();

            return $this->json($response, [
                'status' => true,
                'msg'    => 'OK',
                'data'   => $rows,
            ], 200);
        } catch (\Exception $e) {
            return $this->json($response, [
                'status' => false,
                'msg'    => 'Erro: ' . $e->getMessage(),
                'data'   => [],
            ], 500);
        }
    }

    public function delete($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (is_null($id) || $id === '') {
            return $this->json($response, [
                'status' => false,
                'msg'    => 'Informe o ID da parcela.',
                'id'     => 0,
            ], 403);
        }

        try {
            \App\Database\DB::connection()->delete('installment', ['id' => $id]);

            return $this->json($response, [
                'status' => true,
                'msg'    => 'Parcela removida com sucesso!',
                'id'     => $id,
            ]);
        } catch (\Exception $e) {
            return $this->json($response, [
                'status' => false,
                'msg'    => 'Erro: ' . $e->getMessage(),
                'id'     => 0,
            ], 500);
        }
    }
}