<?php

declare(strict_types=1);

namespace app\controller;

use app\database\DB;

final class ItemSale extends Base
{
    private const TABLE = 'item_sale';

    public function insert($request, $response)
    {
        $form = $request->getParsedBody();
        if (empty($form['id_venda']) || empty($form['id_produto'])) {
            return $this->json($response, [
                'status' => false,
                'msg' => 'id_venda e id_produto são obrigatórios',
                'id' => null,
                'data' => [],
            ], 400);
        }

        try {
            $clean = $this->sanitize($form);
            DB::connection()->insert(self::TABLE, $clean);
            $id = (int) DB::connection()->fetchOne("SELECT currval(pg_get_serial_sequence('item_sale', 'id'))");

            return $this->json($response, [
                'status' => true,
                'msg' => 'Item de venda salvo com sucesso!',
                'id' => $id,
                'data' => [$clean],
            ], 201);
        } catch (\Exception $err) {
            return $this->json($response, [
                'status' => false,
                'msg' => 'Erro: ' . $err->getMessage(),
                'id' => null,
                'data' => [],
            ], 500);
        }
    }

    private function sanitize(array $data): array
    {
        $ignore = ['id', 'action'];
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
            if (is_string($value) && preg_match('/^[0-9]+(\.[0-9]+)?$/', $value)) {
                $clean[$key] = strpos($value, '.') !== false ? (float) $value : (int) $value;
                continue;
            }
            $clean[$key] = $value;
        }

        return $clean;
    }
}
