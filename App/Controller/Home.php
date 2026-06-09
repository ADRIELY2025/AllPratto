<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\DB;

final class Home extends Base
{
    public function home($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('home'), [
                'titulo' => 'Início',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    // ══════════════════════════════════════════════════════════
    //  Visão Geral — total de cadastros por tabela
    // ══════════════════════════════════════════════════════════

    public function resultadoVendas($request, $response)
    {
        $conn = DB::connection();

        return $this->json($response, [
            'categories' => ['Clientes', 'Fornecedores', 'Produtos', 'Empresas'],
            'values'     => [
                (int) $conn->fetchOne('SELECT COUNT(*) FROM customer'),
                (int) $conn->fetchOne('SELECT COUNT(*) FROM supplier'),
                (int) $conn->fetchOne('SELECT COUNT(*) FROM product'),
                (int) $conn->fetchOne('SELECT COUNT(*) FROM company'),
            ],
        ]);
    }

    public function resultadoMarketing($request, $response)
    {
        $conn = DB::connection();

        return $this->json($response, [
            'series' => [
                ['name' => 'Clientes',     'value' => (int) $conn->fetchOne('SELECT COUNT(*) FROM customer')],
                ['name' => 'Fornecedores', 'value' => (int) $conn->fetchOne('SELECT COUNT(*) FROM supplier')],
                ['name' => 'Produtos',     'value' => (int) $conn->fetchOne('SELECT COUNT(*) FROM product')],
                ['name' => 'Empresas',     'value' => (int) $conn->fetchOne('SELECT COUNT(*) FROM company')],
            ]
        ]);
    }

    // ══════════════════════════════════════════════════════════
    //  MESAS — pedidos por mesa
    //  Fonte: tabela "order" (mesa, total)
    // ══════════════════════════════════════════════════════════

    public function graficaMesasBar($request, $response)
    {
        $conn = DB::connection();

        $rows = $conn->fetchAllAssociative('
            SELECT mesa,
                   COUNT(*) AS pedidos
            FROM "order"
            GROUP BY mesa
            ORDER BY mesa ASC
        ');

        if (empty($rows)) {
            return $this->json($response, ['categories' => [], 'values' => []]);
        }

        return $this->json($response, [
            'categories' => array_map(fn($r) => 'Mesa ' . $r['mesa'], $rows),
            'values'     => array_map(fn($r) => (int) $r['pedidos'], $rows),
        ]);
    }

    public function graficaMesasPie($request, $response)
    {
        $conn = DB::connection();

        $rows = $conn->fetchAllAssociative('
            SELECT mesa,
                   SUM(total) AS faturamento
            FROM "order"
            GROUP BY mesa
            ORDER BY mesa ASC
        ');

        if (empty($rows)) {
            return $this->json($response, ['series' => []]);
        }

        return $this->json($response, [
            'series' => array_map(fn($r) => [
                'name'  => 'Mesa ' . $r['mesa'],
                'value' => (float) $r['faturamento'],
            ], $rows),
        ]);
    }

    // ══════════════════════════════════════════════════════════
    //  CLIENTES — cadastros por mês (últimos 6 meses)
    // ══════════════════════════════════════════════════════════

    public function graficaClienteBar($request, $response)
    {
        $conn = DB::connection();

        $rows = $conn->fetchAllAssociative("
            SELECT TO_CHAR(DATE_TRUNC('month', criado_em), 'MM/YYYY') AS mes,
                   DATE_TRUNC('month', criado_em)                     AS mes_ordem,
                   COUNT(*)                                           AS total
            FROM customer
            WHERE criado_em >= NOW() - INTERVAL '6 months'
            GROUP BY DATE_TRUNC('month', criado_em)
            ORDER BY mes_ordem ASC
        ");

        if (empty($rows)) {
            return $this->json($response, ['categories' => [], 'values' => []]);
        }

        return $this->json($response, [
            'categories' => array_column($rows, 'mes'),
            'values'     => array_map(fn($r) => (int) $r['total'], $rows),
        ]);
    }

    public function graficaClientePie($request, $response)
    {
        $conn = DB::connection();

        $rows = $conn->fetchAllAssociative("
            SELECT CASE WHEN ativo THEN 'Ativo' ELSE 'Inativo' END AS situacao,
                   COUNT(*) AS total
            FROM customer
            GROUP BY ativo
            ORDER BY ativo DESC
        ");

        if (empty($rows)) {
            return $this->json($response, ['series' => []]);
        }

        return $this->json($response, [
            'series' => array_map(fn($r) => [
                'name'  => $r['situacao'],
                'value' => (int) $r['total'],
            ], $rows),
        ]);
    }

    // ══════════════════════════════════════════════════════════
    //  PRODUTOS — top 10 mais pedidos
    //  Fonte: order_item (pedidos do cardápio) + fallback item_sale
    // ══════════════════════════════════════════════════════════

    public function graficaProdutoBar($request, $response)
    {
        $conn = DB::connection();

        $rows = $conn->fetchAllAssociative('
            SELECT oi.nome             AS produto,
                   SUM(oi.quantidade) AS total
            FROM order_item oi
            GROUP BY oi.nome
            ORDER BY total DESC
            LIMIT 10
        ');

        if (empty($rows)) {
            $rows = $conn->fetchAllAssociative("
                SELECT COALESCE(p.descricao, s.nome, 'Produto') AS produto,
                       SUM(s.quantidade)                        AS total
                FROM item_sale s
                LEFT JOIN product p ON p.id = s.id_produto
                GROUP BY COALESCE(p.descricao, s.nome, 'Produto')
                ORDER BY total DESC
                LIMIT 10
            ");
        }

        if (empty($rows)) {
            return $this->json($response, ['categories' => [], 'values' => []]);
        }

        return $this->json($response, [
            'categories' => array_column($rows, 'produto'),
            'values'     => array_map(fn($r) => (int) $r['total'], $rows),
        ]);
    }

    public function graficaProdutoPie($request, $response)
    {
        $conn = DB::connection();

        $rows = $conn->fetchAllAssociative('
            SELECT oi.nome             AS produto,
                   SUM(oi.quantidade) AS total
            FROM order_item oi
            GROUP BY oi.nome
            ORDER BY total DESC
            LIMIT 10
        ');

        if (empty($rows)) {
            $rows = $conn->fetchAllAssociative("
                SELECT COALESCE(p.descricao, s.nome, 'Produto') AS produto,
                       SUM(s.quantidade)                        AS total
                FROM item_sale s
                LEFT JOIN product p ON p.id = s.id_produto
                GROUP BY COALESCE(p.descricao, s.nome, 'Produto')
                ORDER BY total DESC
                LIMIT 10
            ");
        }

        if (empty($rows)) {
            return $this->json($response, ['series' => []]);
        }

        return $this->json($response, [
            'series' => array_map(fn($r) => [
                'name'  => $r['produto'],
                'value' => (int) $r['total'],
            ], $rows),
        ]);
    }
}