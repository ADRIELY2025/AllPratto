<?php

declare(strict_types=1);

namespace App\Controller;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;


final class Mesa extends Base
{
    // ──────────────────────────────────────────
    //  Página HTML da lista de mesas
    // ──────────────────────────────────────────
    public function list($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('list-mesa'), [
                'titulo' => 'Lista de mesas',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    // ──────────────────────────────────────────
    //  Página HTML de criação / edição
    // ──────────────────────────────────────────
    public function details($request, $response, $args)
    {
        $id     = $args['id'] ?? null;
        $action = ($id === null) ? 'c' : 'e';
        $mesa   = [];

        if (!is_null($id)) {
            $qb   = \App\Database\DB::select('*')->from('mesa');
            $mesa = $qb
                ->where('id = ' . $qb->createPositionalParameter($id, \Doctrine\DBAL\ParameterType::INTEGER))
                ->fetchAssociative();
        }

        return $this->getTwig()
            ->render($response, $this->setView('mesa'), [
                'titulo' => 'Detalhes da mesa',
                'id'     => $id,
                'action' => $action,
                'mesa'   => $mesa,
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    // ──────────────────────────────────────────
    //  Criar nova mesa
    // ──────────────────────────────────────────
    public function insert($request, $response)
    {
        $form = $request->getParsedBody();

        $data = [
            'numero'     => (int) ($form['numero'] ?? 0),
            'capacidade' => isset($form['capacidade']) && $form['capacidade'] !== ''
                ? (int) $form['capacidade']
                : null,
            'status'     => $form['status'] ?? 'livre',
            'observacao' => $form['observacao'] ?? null,
            'ativo'      => (($form['ativo'] ?? 'false') === 'true')
        ];

        try {

            $conn = \App\Database\DB::connection();

            $conn->insert('mesa', $data);

            $id = (int) $conn->lastInsertId();

            if (!$id) {
                return $this->json($response, [
                    'status' => false,
                    'msg'    => 'Não foi possível obter o ID.',
                    'id'     => 0
                ], 500);
            }

            // URL que será aberta pelo QRCode
            $qrUrl = PROTOCOL . '://' . HOST . '/cardapio/mesa/' . $data['numero'];

            $qrDir = ROOT . '/storage/qrcode/' . $id;

            if (!is_dir($qrDir)) {
                mkdir($qrDir, 0775, true);
            }

            $fileName = "mesa_{$id}.png";

            $writer = new PngWriter();

            // Create QR code
            $qrCode = new QrCode(
                data: $qrUrl,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::Low,
                size: 300,
                margin: 10,
                roundBlockSizeMode: RoundBlockSizeMode::Margin,
                foregroundColor: new Color(0, 0, 0),
                backgroundColor: new Color(255, 255, 255)
            );

            $result = $writer->write($qrCode);

            // Validate the result
            $writer->validateResult($result, $qrUrl);

            $result->saveToFile($qrDir . '/' . $fileName);

            return $this->json($response, [
                'status' => true,
                'msg'    => 'Mesa salva com sucesso!',
                'id'     => $id,
                'qrcode' => '/uploads/qrcodes/' . $fileName,
                'url'    => $qrUrl
            ], 201);
        } catch (\Throwable $e) {

            return $this->json($response, [
                'status' => false,
                'msg'    => $e->getMessage(),
                'id'     => 0
            ], 500);
        }
    }

    // ──────────────────────────────────────────
    //  Atualizar mesa
    // ──────────────────────────────────────────
    public function update($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Por favor informe o ID do registro', 'id' => 0], 403);
        }

        $data = [
            'numero'      => (int) ($form['numero']     ?? 0),
            'capacidade'  => isset($form['capacidade']) && $form['capacidade'] !== ''
                ? (int) $form['capacidade'] : null,
            'status'      => $form['status']     ?? 'livre',
            'observacao'  => $form['observacao'] ?? null,
            'ativo'       => (($form['ativo'] ?? 'false') === 'true') ? true : false,
            'atualizado_em' => date('Y-m-d H:i:s'),
        ];

        try {
            $updated = \App\Database\DB::connection()->update('mesa', $data, ['id' => (int) $id]);

            if (!$updated) {
                return $this->json($response, ['status' => false, 'msg' => 'Nenhum registro alterado.', 'id' => 0], 403);
            }

            return $this->json($response, ['status' => true, 'msg' => 'Mesa alterada com sucesso!', 'id' => $id], 200);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    // ──────────────────────────────────────────
    //  Atualizar apenas o status (livre / ocupada)
    //  Usado pelo cardápio ao receber um pedido
    // ──────────────────────────────────────────
    public function updateStatus($request, $response)
    {
        $form   = $request->getParsedBody();
        $id     = $form['id']     ?? null;
        $status = $form['status'] ?? null;

        $statusValidos = ['livre', 'ocupada', 'reservada', 'inativa'];

        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o ID da mesa', 'id' => 0], 403);
        }

        if (!in_array($status, $statusValidos, true)) {
            return $this->json($response, ['status' => false, 'msg' => 'Status inválido. Use: ' . implode(', ', $statusValidos), 'id' => 0], 422);
        }

        try {
            $updated = \App\Database\DB::connection()->update('mesa', [
                'status'        => $status,
                'atualizado_em' => date('Y-m-d H:i:s'),
            ], ['id' => (int) $id]);

            if (!$updated) {
                return $this->json($response, ['status' => false, 'msg' => 'Nenhum registro alterado.', 'id' => 0], 403);
            }

            return $this->json($response, ['status' => true, 'msg' => 'Status atualizado!', 'id' => $id], 200);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    // ──────────────────────────────────────────
    //  Excluir mesa
    // ──────────────────────────────────────────
    public function delete($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o código da mesa', 'id' => 0], 403);
        }

        try {
            $deleted = \App\Database\DB::connection()->delete('mesa', ['id' => (int) $id]);

            if (!$deleted) {
                return $this->json($response, ['status' => false, 'msg' => 'Nenhum registro removido.', 'id' => $id], 403);
            }

            return $this->json($response, ['status' => true, 'msg' => 'Mesa removida com sucesso!', 'id' => $id]);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    // ──────────────────────────────────────────
    //  DataTables — lista paginada
    // ──────────────────────────────────────────
    public function listingdata($request, $response)
    {
        $form   = $request->getParsedBody();
        $term   = $form['search']['value'] ?? null;
        $start  = (int) ($form['start']  ?? 0);
        $length = (int) ($form['length'] ?? 10);

        $columns = [
            0 => 'id',
            1 => 'numero',
            2 => 'capacidade',
            3 => 'status',
            4 => 'ativo',
            5 => 'criado_em',
            6 => 'atualizado_em',
        ];

        $posField   = (isset($form['order'][0]['column']) && isset($columns[(int) $form['order'][0]['column']]))
            ? (int) $form['order'][0]['column']
            : 0;
        $orderType  = strtoupper($form['order'][0]['dir'] ?? 'ASC');
        $orderType  = in_array($orderType, ['ASC', 'DESC'], true) ? $orderType : 'ASC';
        $orderField = $columns[$posField];

        try {
            $totalRecords = (int) \App\Database\DB::select('COUNT(*)')
                ->from('mesa')
                ->fetchOne();

            $query = \App\Database\DB::select("
                id,
                numero,
                capacidade,
                status,
                observacao,
                ativo,
                to_char(criado_em,     'DD/MM/YYYY HH24:MI:SS') AS criado_em,
                to_char(atualizado_em, 'DD/MM/YYYY HH24:MI:SS') AS atualizado_em
            ")->from('mesa');

            if (!is_null($term) && $term !== '') {
                $query->setParameter('term', '%' . $term . '%');
                $query->where('CAST(id AS TEXT) ILIKE :term')
                    ->orWhere('CAST(numero AS TEXT) ILIKE :term')
                    ->orWhere('status ILIKE :term')
                    ->orWhere('observacao ILIKE :term');
            }

            $filteredRecords = (int) (clone $query)->select('COUNT(*)')->fetchOne();

            $mesas = $query
                ->orderBy($orderField, $orderType)
                ->setFirstResult($start)
                ->setMaxResults($length)
                ->fetchAllAssociative();

            $rows = [];
            foreach ($mesas as $key => $value) {
                $statusBadge = match ($value['status']) {
                    'livre'     => "<span class='badge bg-success'>Livre</span>",
                    'ocupada'   => "<span class='badge bg-danger'>Ocupada</span>",
                    'reservada' => "<span class='badge bg-warning text-dark'>Reservada</span>",
                    'inativa'   => "<span class='badge bg-secondary'>Inativa</span>",
                    default     => $value['status'],
                };

                $rows[$key] = [
                    $value['id'],
                    $value['numero'],
                    $value['capacidade'] ?? '—',
                    $statusBadge,
                    ($value['ativo'] === true) ? 'Ativo' : 'Inativo',
                    $value['criado_em'],
                    $value['atualizado_em'],
                    "<td>
                    <a class='btn btn-sm btn-warning' href='/mesa/detalhes/" . $value['id'] . "'><i class='fa-solid fa-pen-to-square'></i> Editar</a>
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
