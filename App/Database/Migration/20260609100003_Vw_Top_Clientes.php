<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

// CORRIGIDO: estado_venda é ENUM stock_movement_venda ('PRE_VENDA','ORCAMENTO','VENDA').
// A view original comparava com 'cancelado' e 'devolvido' que não existem no ENUM
// — PostgreSQL lança SQLSTATE[22P02] ao tentar fazer esse cast implícito.
// Solução: cast explícito para TEXT antes do NOT IN, ou simplesmente incluir
// apenas registros com estado_venda = 'VENDA' (pedido efetivado).

final class Version20260609100003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vw_Top_Clientes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE VIEW public.vw_top_clientes AS

            -- Fluxo 1: vendas avulsas (PDV) — só conta vendas efetivadas
            SELECT
                c.id                                     AS id_cliente,
                COALESCE(c.nome, 'Cliente #' || c.id)    AS nome,
                COUNT(s.id)                              AS total_compras,
                COALESCE(SUM(s.total_liquido), 0)        AS total_gasto
            FROM public.customer c
            INNER JOIN public.sale s ON s.id_cliente = c.id
            WHERE s.estado_venda::TEXT = 'VENDA'
               OR s.estado_venda IS NULL
            GROUP BY c.id, c.nome

            UNION ALL

            -- Fluxo 2: cardápio online
            SELECT
                c.id,
                COALESCE(c.nome, 'Cliente #' || c.id),
                COUNT(o.id),
                COALESCE(SUM(o.total), 0)
            FROM public.customer c
            INNER JOIN public."order" o ON o.id_cliente = c.id
            WHERE o.status NOT IN ('cancelado')
            GROUP BY c.id, c.nome

            ORDER BY total_gasto DESC
        SQL);
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS public.vw_top_clientes');
    }
}