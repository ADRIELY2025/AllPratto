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
    //  MESAS — faturamento total por mesa (barras)
    //  Fonte: vw_faturamento_mesa
    //  Funciona com order.mesa INTEGER (estrutura atual do banco)
    public function graficaMesasBar($request, $response)
    {
        $conn = DB::connection();

        $rows = $conn->fetchAllAssociative('
            SELECT numero_mesa,
                   faturamento_total
            FROM public.vw_faturamento_mesa
            ORDER BY numero_mesa ASC
        ');

        if (empty($rows)) {
            return $this->json($response, ['categories' => [], 'values' => []]);
        }

        return $this->json($response, [
            'categories' => array_map(fn($r) => 'Mesa ' . $r['numero_mesa'], $rows),
            'values'     => array_map(fn($r) => (float) $r['faturamento_total'], $rows),
        ]);
    }
    //  MESAS — status em tempo real (rosca)
    //  Fonte: vw_status_mesa_contagem
    //  Deriva status do último pedido de cada mesa (sem tabela mesa)
    public function graficaMesasPie($request, $response)
    {
        $conn = DB::connection();

        $rows = $conn->fetchAllAssociative('
            SELECT status,
                   total
            FROM public.vw_status_mesa_contagem
            ORDER BY status ASC
        ');

        $labels = [
            'livre'      => 'Livres',
            'ocupada'    => 'Ocupadas',
            'aguardando' => 'Aguardando pagamento / limpeza',
        ];

        $colors = [
            'livre'      => '#2E7D5E',
            'ocupada'    => '#C0392B',
            'aguardando' => '#E8A838',
        ];

        if (empty($rows)) {
            return $this->json($response, ['series' => []]);
        }

        return $this->json($response, [
            'series' => array_map(fn($r) => [
                'name'      => $labels[$r['status']] ?? ucfirst($r['status']),
                'value'     => (int) $r['total'],
                'itemStyle' => ['color' => $colors[$r['status']] ?? '#888'],
            ], $rows),
        ]);
    }
    //  CLIENTES — novos cadastros por mês (últimos 6 meses)
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
            SELECT label,
                   mes_ordem,
                   total_vendas
            FROM public.vw_vendas_por_mes_total
            ORDER BY mes_ordem ASC
        ');

        if (empty($rows)) {
            return $this->json($response, ['categories' => [], 'values' => []]);
        }

        return $this->json($response, [
            'categories' => array_column($rows, 'label'),
            'values'     => array_map(fn($r) => (float) $r['total_vendas'], $rows),
        ]);
    }
    //  PRODUTOS — Curva ABC por valor vendido (rosca)
    //  Fonte: vw_curva_abc_grupos
    //  A = até 70% do faturamento | B = até 90% | C = restante
    public function graficaProdutoPie($request, $response)
    {
        $conn = DB::connection();

        $rows = $conn->fetchAllAssociative('
            SELECT grupo,
                   total_valor,
                   pct_total
            FROM public.vw_curva_abc_grupos
            ORDER BY grupo ASC
        ');

        $labels = [
            'A' => 'Grupo A — alto volume',
            'B' => 'Grupo B — médio volume',
            'C' => 'Grupo C — baixo volume',
        ];

        $colors = [
            'A' => '#378ADD',
            'B' => '#63B85C',
            'C' => '#EF9F27',
        ];

        if (empty($rows)) {
            return $this->json($response, ['series' => []]);
        }

        return $this->json($response, [
            'series' => array_map(fn($r) => [
                'name'      => $labels[$r['grupo']] ?? 'Grupo ' . $r['grupo'],
                'value'     => (float) $r['total_valor'],
                'itemStyle' => ['color' => $colors[$r['grupo']] ?? '#888'],
            ], $rows),
        ]);
    }

     public function destaquesSemana($request, $response)
    {
        $conn = DB::connection();
 
        // ── 1. Produtos marcados manualmente ────────────────────────────────
        $marcados = $conn->fetchAllAssociative(<<<'SQL'
            SELECT
                id,
                nome,
                descricao,
                preco_venda,
                imagem_url,
                grupo AS categoria
            FROM public.product
            WHERE destaque_semana = TRUE
              AND ativo    = TRUE
              AND excluido = FALSE
              AND (destaque_ate IS NULL OR destaque_ate >= CURRENT_DATE)
            ORDER BY id
            LIMIT 4
        SQL);
 
        // ── 2. Completar com mais vendidos (cardápio + venda avulsa) ────────
        if (count($marcados) < 4) {
            $ids_ja = array_column($marcados, 'id');
            $limit  = 4 - count($marcados);
 
            $exclude = '';
            if (!empty($ids_ja)) {
                $ph      = implode(',', $ids_ja);
                $exclude = "AND p.id NOT IN ($ph)";
            }
 
            $sql = <<<SQL
                WITH vendas_recentes AS (
                    SELECT oi.product_id AS id, SUM(oi.quantidade) AS qtd
                    FROM public.order_item oi
                    INNER JOIN public."order" o ON o.id = oi.order_id
                    WHERE o.status <> 'cancelado'
                      AND o.criado_em >= CURRENT_DATE - INTERVAL '7 days'
                      AND oi.product_id IS NOT NULL
                    GROUP BY oi.product_id
 
                    UNION ALL
 
                    SELECT it.id_produto AS id, SUM(it.quantidade) AS qtd
                    FROM public.item_sale it
                    INNER JOIN public.sale s ON s.id = it.id_venda
                    WHERE (s.estado_venda IS NULL OR s.estado_venda::TEXT = 'VENDA')
                      AND s.criado_em >= CURRENT_DATE - INTERVAL '7 days'
                      AND it.id_produto IS NOT NULL
                    GROUP BY it.id_produto
                )
                SELECT
                    p.id,
                    p.nome,
                    p.descricao,
                    p.preco_venda,
                    p.imagem_url,
                    p.grupo AS categoria,
                    SUM(v.qtd) AS vendidos
                FROM vendas_recentes v
                JOIN public.product p ON p.id = v.id
                WHERE p.ativo    = TRUE
                  AND p.excluido = FALSE
                  $exclude
                GROUP BY p.id, p.nome, p.descricao, p.preco_venda, p.imagem_url, p.grupo
                ORDER BY vendidos DESC
                LIMIT $limit
            SQL;
 
            $maisVendidos = $conn->fetchAllAssociative($sql);
            $marcados     = array_merge($marcados, $maisVendidos);
        }
 
        // ── 3. Formatar e retornar ───────────────────────────────────────────
        $data = array_map(fn($p) => [
            'id'         => (int) $p['id'],
            'nome'       => $p['nome'],
            'descricao'  => $p['descricao'] ?? '',
            'preco'      => 'R$ ' . number_format((float) $p['preco_venda'], 2, ',', '.'),
            'imagem_url' => $p['imagem_url'] ? '/product/get-imagem/' . $p['id'] : null,
            'categoria'  => $p['categoria'] ?? null,
        ], $marcados);
 
        return $this->json($response, ['success' => true, 'data' => $data]);
    }
 
    public function getDashboardData($request, $response)
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
}
