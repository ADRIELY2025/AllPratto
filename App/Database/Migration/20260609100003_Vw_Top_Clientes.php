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
        SELECT
            id_cliente,
            nome,
            SUM(total_compras) AS total_compras,
            SUM(total_gasto) AS total_gasto
        FROM (

            SELECT
                c.id AS id_cliente,
                COALESCE(
                    NULLIF(
                        TRIM(
                            COALESCE(c.nome_fantasia,'') || ' ' ||
                            COALESCE(c.sobrenome_razao,'')
                        ),
                        ''
                    ),
                    'Cliente #' || c.id
                ) AS nome,
                COUNT(s.id) AS total_compras,
                COALESCE(SUM(s.total_liquido),0) AS total_gasto
            FROM customer c
            INNER JOIN sale s
                ON s.id_cliente = c.id
            WHERE s.estado_venda::TEXT = 'VENDA'
            OR s.estado_venda IS NULL
            GROUP BY c.id, c.nome_fantasia, c.sobrenome_razao

            UNION ALL

            SELECT
                c.id,
                COALESCE(
                    NULLIF(
                        TRIM(
                            COALESCE(c.nome_fantasia,'') || ' ' ||
                            COALESCE(c.sobrenome_razao,'')
                        ),
                        ''
                    ),
                    'Cliente #' || c.id
                ),
                COUNT(o.id),
                COALESCE(SUM(o.total),0)
            FROM customer c
            INNER JOIN "order" o
                ON o.id_cliente = c.id
            WHERE o.status <> 'cancelado'
            GROUP BY c.id, c.nome_fantasia, c.sobrenome_razao

        ) dados
        GROUP BY id_cliente, nome
        ORDER BY total_gasto DESC;
        SQL);
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS public.vw_top_clientes');
    }
}