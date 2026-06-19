<?php

declare(strict_types=1);

namespace App\Controller;

final class PaymentTerms extends Base
{
    public function list($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('list-paymentterms'), [
                'titulo' => 'Lista de condições de pagamento',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function details($request, $response, $args)
    {
        $id     = $args['id'] ?? null;
        $action = ($id === null) ? 'c' : 'e';
        $paymentTerm = [];

        // id_sale pode vir como query param (ex: /payment/detalhes?id_sale=42)
        $queryParams = $request->getQueryParams();
        $idSale      = $queryParams['id_sale'] ?? null;
        $totalVenda  = null;

        if (!is_null($id)) {
            $qb = \App\Database\DB::select('*')->from('payment_terms');
            $paymentTerm = $qb
                ->where('id = ' . $qb->createPositionalParameter($id, \Doctrine\DBAL\ParameterType::INTEGER))
                ->fetchAssociative();
        }

        // Busca o total_liquido da venda para exibir o valor fixo
        if (!is_null($idSale)) {
            $qbSale = \App\Database\DB::select('total_liquido')->from('sale');
            $sale   = $qbSale
                ->where('id = ' . $qbSale->createPositionalParameter((int) $idSale, \Doctrine\DBAL\ParameterType::INTEGER))
                ->fetchAssociative();
            $totalVenda = $sale['total_liquido'] ?? null;
        }

        return $this->getTwig()
            ->render($response, $this->setView('paymentterms'), [
                'titulo'      => 'Detalhes da condição de pagamento',
                'id'          => $id,
                'action'      => $action,
                'paymentTerm' => $paymentTerm,
                'id_sale'     => $idSale,
                'total_venda' => $totalVenda,
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function insert($request, $response)
    {
        $form = $request->getParsedBody();

        $FieldsAndValues = [
            'codigo' => $form['codigo'] ?? null,
            'titulo' => $form['titulo'] ?? null,
            'atalho' => $form['atalho'] ?? null,
        ];

        try {
            \App\Database\DB::connection()->insert('payment_terms', $FieldsAndValues);

            $id = \App\Database\DB::select('id')
                ->from('payment_terms')
                ->orderBy('id', 'DESC')
                ->setMaxResults(1)
                ->fetchAssociative();

            return $this->json($response, [
                'status' => true,
                'msg'    => 'Salvo com sucesso!',
                'id'     => $id['id'],
            ], 201);
        } catch (\Exception $e) {
            return $this->json($response, [
                'status' => false,
                'msg'    => 'Restrição: ' . $e->getMessage(),
                'id'     => 0,
            ], 500);
        }
    }

    public function update($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (is_null($id)) {
            return $this->json($response, [
                'status' => false,
                'msg'    => 'Por favor informe o ID do registro',
                'id'     => 0,
            ], 403);
        }

        $FieldsAndValues = [
            'codigo' => $form['codigo'] ?? null,
            'titulo' => $form['titulo'] ?? null,
            'atalho' => $form['atalho'] ?? null,
        ];

        try {
            $IsUpdated = \App\Database\DB::connection()->update('payment_terms', $FieldsAndValues, ['id' => $id]);

            if (!$IsUpdated) {
                return $this->json($response, ['status' => false, 'msg' => 'Nenhum registro alterado.', 'id' => 0], 403);
            }

            return $this->json($response, ['status' => true, 'msg' => 'Alterado com sucesso!', 'id' => $id], 201);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function delete($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o código da condição de pagamento.', 'id' => 0], 403);
        }

        try {
            \App\Database\DB::connection()->delete('payment_terms', ['id' => $id]);
            return $this->json($response, ['status' => true, 'msg' => 'Removido com sucesso!', 'id' => $id]);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
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
            1 => 'codigo',
            2 => 'titulo',
            3 => 'atalho',
            4 => 'criado_em',
            5 => 'atualizado_em',
        ];

        $posField  = (isset($form['order'][0]['column']) && isset($columns[(int) $form['order'][0]['column']]))
            ? (int) $form['order'][0]['column'] : 0;
        $orderType = in_array(strtoupper($form['order'][0]['dir'] ?? 'DESC'), ['ASC', 'DESC'], true)
            ? strtoupper($form['order'][0]['dir']) : 'DESC';
        $orderField = $columns[$posField];

        // Mapa código → label
        $formas = [
            '01' => 'Dinheiro', '02' => 'Cheque', '03' => 'Cartão de Crédito',
            '04' => 'Cartão de Débito', '15' => 'Boleto Bancário', '17' => 'PIX', '99' => 'Outros',
        ];

        try {
            $totalRecords = (int) \App\Database\DB::select('COUNT(*)')->from('payment_terms')->fetchOne();

            $query = \App\Database\DB::select('*')->from('payment_terms');

            if (!is_null($term) && $term !== '') {
                $query->setParameter('term', '%' . $term . '%')
                    ->where('CAST(id AS TEXT) ILIKE :term')
                    ->orWhere('codigo ILIKE :term')
                    ->orWhere('titulo ILIKE :term')
                    ->orWhere('atalho ILIKE :term')
                    ->orWhere("TO_CHAR(criado_em, 'DD/MM/YYYY HH24:MI:SS') ILIKE :term");
            }

            $filteredRecords = (int) (clone $query)->select('COUNT(*)')->fetchOne();

            $items = $query->orderBy($orderField, $orderType)
                ->setFirstResult($start)->setMaxResults($length)
                ->fetchAllAssociative();

            $rows = [];
            foreach ($items as $key => $value) {
                $rows[$key] = [
                    $value['id'],
                    $formas[$value['codigo']] ?? $value['codigo'],
                    $value['titulo'],
                    $value['atalho'],
                    (new \DateTime($value['criado_em']))->format('d/m/Y H:i:s'),
                    (new \DateTime($value['atualizado_em']))->format('d/m/Y H:i:s'),
                    "<td>
                        <a class='btn btn-sm btn-warning' href='/payment/detalhes/{$value['id']}'>
                            <i class='fa-solid fa-pen-to-square'></i> Editar
                        </a>
                        <button type='button' class='btn btn-sm btn-danger' onclick='ShowModal({$value['id']});'>
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
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
}