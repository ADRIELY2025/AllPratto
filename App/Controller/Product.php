<?php

declare(strict_types=1);

namespace App\Controller;

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
        $id     = $args['id'] ?? null;
        $action = ($id === null) ? 'c' : 'e';
        $product = [];

        if (!is_null($id)) {
            $qb = \App\Database\DB::select('*')->from('product');
            $product = $qb
                ->where('id = ' . $qb->createPositionalParameter($id, \Doctrine\DBAL\ParameterType::INTEGER))
                ->fetchAssociative();
        }

        return $this->getTwig()
            ->render($response, $this->setView('product'), [
                'titulo'  => 'Detalhes do produto',
                'id'      => $id,
                'action'  => $action,
                'product' => $product,
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function insert($request, $response)
    {
        $form = $request->getParsedBody();
        $imagem = $request->getUploadedFiles()['imagem'] ?? null;
        $data = [
            'nome'                   => $form['nome']                ?? '',
            'codigo_barra'           => $form['codigo_barra']         ?? '',
            'grupo'                  => $form['grupo']               ?? '',
            'unidade'                => $form['unidade']             ?? '',
            'preco_compra'           => $this->toDecimal($form['preco_compra']          ?? 0),
            'total_imposto'          => $this->toDecimal($form['total_imposto']         ?? 0),
            'margem_lucro'           => $this->toDecimal($form['margem_lucro']          ?? 0),
            'custo_operacional'      => $this->toDecimal($form['custo_operacional']     ?? 0),
            'valor_venda_sugerido'   => $this->toDecimal($form['valor_venda_sugerido']   ?? 0),
            'preco_venda'            => $this->toDecimal($form['preco_venda']           ?? 0),
            'tempo_preparo'          => $form['tempoPreparo']        ?? null,
            'descricao'              => $form['descricao']           ?? '',
            'ativo'                  => in_array($form['ativo'] ?? '', ['true', 'on', '1', 1], true) ? 'true' : 'false',
            'excluido'               => 'false',
        ];

        try {
            $conn = \App\Database\DB::connection();
            $conn->insert('product', $data);
            $id = (int) $conn->lastInsertId();

            $path = ROOT . '/storage/produtos/' . $id;

            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }

            move_uploaded_file($imagem->getFilePath(), $path . '/' . $imagem->getClientFilename());

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
            'nome'                   => $form['nome']                ?? null,
            'codigo_barra'           => $form['codigoBarra']         ?? null,
            'grupo'                  => $form['grupo']               ?? null,
            'unidade'                => $form['unidade']             ?? null,
            'imagem_url'             => $form['imagemUrl']           ?? " ",
            'preco_compra'           => $this->toDecimal($form['preco_compra']          ?? 0),
            'total_imposto'          => $this->toDecimal($form['total_imposto']         ?? 0),
            'margem_lucro'           => $this->toDecimal($form['margem_lucro']          ?? 0),
            'custo_operacional'      => $this->toDecimal($form['custo_operacional']     ?? 0),
            'valor_venda_sugerido'   => $this->toDecimal($form['valor_venda_sugerido']   ?? 0),
            'preco_venda'            => $this->toDecimal($form['preco_venda']           ?? 0),
            'tempo_preparo'          => $form['tempoPreparo']        ?? null,
            'descricao'              => $form['descricao']           ?? null,
            'ativo'                  => in_array($form['ativo'] ?? '', ['true', 'on', '1', 1], true),
            'atualizado_em'          => date('Y-m-d H:i:s'),
        ];

        try {
            $updated = \App\Database\DB::connection()->update('product', $data, ['id' => (int) $id]);

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
            return $this->json($response, ['status' => false, 'msg' => 'Informe o código do produto', 'id' => 0], 403);
        }

        try {
            $deleted = \App\Database\DB::connection()->delete('product', ['id' => (int) $id]);

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
            1 => 'nome',
            2 => 'codigo_barra',
            3 => 'grupo',
            4 => 'preco_compra',
            5 => 'preco_venda',
            6 => 'ativo',
            7 => 'criado_em',
            8 => 'atualizado_em',
        ];

        $posField   = (isset($form['order'][0]['column']) && isset($columns[(int) $form['order'][0]['column']]))
            ? (int) $form['order'][0]['column']
            : 0;
        $orderType  = strtoupper($form['order'][0]['dir'] ?? 'DESC');
        $orderType  = in_array($orderType, ['ASC', 'DESC'], true) ? $orderType : 'DESC';
        $orderField = $columns[$posField];

        try {
            $totalRecords = (int) \App\Database\DB::select('COUNT(*)')
                ->from('product')
                ->where('excluido = false')
                ->fetchOne();

            $query = \App\Database\DB::select("
                id,
                nome,
                codigo_barra,
                grupo,
                unidade,
                preco_compra,
                preco_venda,
                ativo,
                to_char(criado_em,     'DD/MM/YYYY HH24:MI:SS') AS criado_em,
                to_char(atualizado_em, 'DD/MM/YYYY HH24:MI:SS') AS atualizado_em
            ")->from('product')->where('excluido = false');

            if (!is_null($term) && $term !== '') {
                $query->setParameter('term', '%' . $term . '%');
                $query->andWhere(
                    $query->expr()->or(
                        'CAST(id AS TEXT) ILIKE :term',
                        'nome ILIKE :term',
                        'codigo_barra ILIKE :term',
                        'grupo ILIKE :term',
                        'unidade ILIKE :term',
                        "CAST(preco_compra AS TEXT) ILIKE :term",
                        "CAST(preco_venda AS TEXT) ILIKE :term",
                        "TO_CHAR(criado_em,     'DD/MM/YYYY HH24:MI:SS') ILIKE :term",
                        "TO_CHAR(atualizado_em, 'DD/MM/YYYY HH24:MI:SS') ILIKE :term"
                    )
                );
            }

            $filteredRecords = (int) (clone $query)->select('COUNT(*)')->fetchOne();

            $products = $query
                ->orderBy($orderField, $orderType)
                ->setFirstResult($start)
                ->setMaxResults($length)
                ->fetchAllAssociative();

            $rows = [];
            foreach ($products as $key => $value) {
                $rows[$key] = [
                    $value['id'],
                    $value['nome'],
                    $value['codigo_barra'] ?? '-',
                    $value['grupo']        ?? '-',
                    'R$ ' . number_format((float) $value['preco_compra'], 2, ',', '.'),
                    'R$ ' . number_format((float) $value['preco_venda'],  2, ',', '.'),
                    ($value['ativo'] === true) ? 'Ativo' : 'Inativo',
                    $value['criado_em'],
                    $value['atualizado_em'],
                    "<td>
                        <a class='btn btn-sm btn-warning' href='/product/detalhes/" . $value['id'] . "'>
                            <i class='fa-solid fa-pen-to-square'></i> Editar
                        </a>
                        <button type='button' class='btn btn-sm btn-danger' onclick='ShowModal(" . $value['id'] . ");'>
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

    // Converte valor BR (1.234,56) ou US (1234.56) para float
    private function toDecimal(mixed $value): float
    {
        $str = (string) $value;
        // Se tiver vírgula, assume formato BR
        if (str_contains($str, ',')) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
        }
        return (float) $str;
    }


    public function getImagem($request, $response)
    {
        $id = $request->getAttribute('id');

        // Implement the logic to retrieve and return the image
        // This is a simplified example - replace with actual image retrieval logic
        $imagePath = __DIR__ . "/../../public/uploads/produtos/produto-{$id}.jpg";
        if (!file_exists($imagePath)) {
            return $this->json($response, ['status' => false, 'msg' => 'Imagem não encontrada.'], 404);
        }

        $imageData = file_get_contents($imagePath);
        return $this->image($response, $imageData, 'image/jpeg');
    }
}
