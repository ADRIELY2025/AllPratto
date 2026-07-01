<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

// CORRIGIDO: mesmo bug do Vw_Top_Clientes — estado_venda é ENUM venda_estado
// e não aceita comparação com 'cancelado'/'devolvido'.
// Solução: cast explícito ::TEXT antes do NOT IN, ou filtrar só 'VENDA'.

final class Version20260609100004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vw_Vendas_Por_Mes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE VIEW public.vw_vendas_por_mes AS

            -- Cardápio online: agrupa pelo criado_em do pedido pai
            SELECT
                EXTRACT(YEAR  FROM o.criado_em)::INT                 AS ano,
                EXTRACT(MONTH FROM o.criado_em)::INT                 AS mes_numero,
                TO_CHAR(DATE_TRUNC('month', o.criado_em), 'MM/YYYY') AS label,
                DATE_TRUNC('month', o.criado_em)                     AS mes_ordem,
                COALESCE(SUM(oi.subtotal), 0)                        AS total_vendas,
                'cardapio'::TEXT                                      AS origem
            FROM public.order_item oi
            INNER JOIN public."order" o ON o.id = oi.order_id
            WHERE o.status NOT IN ('cancelado')
            GROUP BY
                EXTRACT(YEAR  FROM o.criado_em),
                EXTRACT(MONTH FROM o.criado_em),
                DATE_TRUNC('month', o.criado_em)

            UNION ALL

            -- Venda avulsa: agrupa pelo criado_em da venda
            -- CORRIGIDO: estado_venda::TEXT evita SQLSTATE[22P02] no cast do ENUM
            SELECT
                EXTRACT(YEAR  FROM s.criado_em)::INT                 AS ano,
                EXTRACT(MONTH FROM s.criado_em)::INT                 AS mes_numero,
                TO_CHAR(DATE_TRUNC('month', s.criado_em), 'MM/YYYY') AS label,
                DATE_TRUNC('month', s.criado_em)                     AS mes_ordem,
                COALESCE(SUM(it.total_liquido), 0)                   AS total_vendas,
                'venda'::TEXT                                         AS origem
            FROM public.item_sale it
            INNER JOIN public.sale s ON s.id = it.id_venda
            WHERE s.estado_venda IS NULL
               OR s.estado_venda::TEXT = 'VENDA'
            GROUP BY
                EXTRACT(YEAR  FROM s.criado_em),
                EXTRACT(MONTH FROM s.criado_em),
                DATE_TRUNC('month', s.criado_em)
        SQL);

        $this->addSql(<<<'SQL'
            CREATE OR REPLACE VIEW public.vw_vendas_por_mes_total AS
            SELECT
                ano,
                mes_numero,
                label,
                mes_ordem,
                SUM(total_vendas) AS total_vendas
            FROM public.vw_vendas_por_mes
            GROUP BY ano, mes_numero, label, mes_ordem
            ORDER BY mes_ordem ASC
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS public.vw_vendas_por_mes_total');
        $this->addSql('DROP VIEW IF EXISTS public.vw_vendas_por_mes');
    }
}