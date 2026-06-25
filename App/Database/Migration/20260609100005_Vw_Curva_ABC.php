<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

// CORRIGIDO: estado_venda é ENUM — cast ::TEXT antes de comparar.

final class Version20260609100005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vw_Curva_ABC';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
        CREATE OR REPLACE VIEW public.vw_curva_abc_produto AS
            WITH vendas AS (

                -- Cardápio
                SELECT
                    oi.nome AS produto,
                    SUM(oi.subtotal) AS valor_total
                FROM public.order_item oi
                INNER JOIN public."order" o
                    ON o.id = oi.order_id
                WHERE o.status <> 'cancelado'
                GROUP BY oi.nome

                UNION ALL

                -- Venda avulsa
                SELECT
                    COALESCE(
                        NULLIF(p.descricao, ''),
                        it.nome,
                        'Produto #' || it.id_produto
                    ) AS produto,
                    SUM(it.total_liquido) AS valor_total
                FROM public.item_sale it
                LEFT JOIN public.product p
                    ON p.id = it.id_produto
                INNER JOIN public.sale s
                    ON s.id = it.id_venda
                WHERE s.estado_venda IS NULL
                   OR s.estado_venda::TEXT = 'VENDA'
                GROUP BY
                    COALESCE(
                        NULLIF(p.descricao, ''),
                        it.nome,
                        'Produto #' || it.id_produto
                    )
            ),

            consolidado AS (
                SELECT
                    produto,
                    SUM(valor_total) AS valor_total
                FROM vendas
                GROUP BY produto
            ),

            total AS (
                SELECT SUM(valor_total) AS grand_total
                FROM consolidado
            ),

            acumulado AS (
                SELECT
                    c.produto,
                    c.valor_total,
                    t.grand_total,

                    ROUND(
                        ((c.valor_total / NULLIF(t.grand_total, 0)) * 100)::numeric,
                        2
                    ) AS pct_individual,

                    ROUND(
                        (
                            SUM(c.valor_total) OVER (ORDER BY c.valor_total DESC)
                            / NULLIF(t.grand_total, 0)
                            * 100
                        )::numeric,
                        2
                    ) AS pct_acumulado

                FROM consolidado c
                CROSS JOIN total t
            )

            SELECT
                produto,
                valor_total,
                pct_individual,
                pct_acumulado,

                CASE
                    WHEN pct_acumulado <= 70 THEN 'A'
                    WHEN pct_acumulado <= 90 THEN 'B'
                    ELSE 'C'
                END AS grupo_abc

            FROM acumulado
            ORDER BY valor_total DESC;
        SQL);

        $this->addSql(<<<'SQL'
            CREATE OR REPLACE VIEW public.vw_curva_abc_grupos AS
            SELECT
                grupo_abc                      AS grupo,
                COUNT(*)                       AS total_produtos,
                ROUND(SUM(valor_total), 2)     AS total_valor,
                ROUND(SUM(pct_individual), 2)  AS pct_total
            FROM public.vw_curva_abc_produto
            GROUP BY grupo_abc
            ORDER BY grupo_abc ASC
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS public.vw_curva_abc_grupos');
        $this->addSql('DROP VIEW IF EXISTS public.vw_curva_abc_produto');
    }
}