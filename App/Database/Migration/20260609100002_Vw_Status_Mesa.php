<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

// CORRIGIDO: usava o.mesa (INTEGER solto) em DISTINCT ON e ORDER BY.
// Agora usa o.id_mesa (FK) com JOIN na tabela mesa para obter o numero.
// Também corrigido o DISTINCT ON / ORDER BY — precisam usar a mesma coluna.

final class Version20260609100002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vw_Status_Mesa';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE VIEW public.vw_status_mesa AS
            WITH ultimo_pedido AS (
                SELECT DISTINCT ON (o.id_mesa)
                    o.id_mesa,
                    o.status  AS ultimo_status,
                    o.criado_em
                FROM public."order" o
                ORDER BY o.id_mesa, o.criado_em DESC
            )
            SELECT
                m.id            AS id_mesa,
                m.numero        AS numero_mesa,
                CASE
                    WHEN up.ultimo_status IN ('pendente', 'em_preparo') THEN 'ocupada'
                    WHEN up.ultimo_status IN ('pronto', 'entregue')     THEN 'aguardando'
                    WHEN up.ultimo_status IN ('pago', 'cancelado')      THEN 'livre'
                    ELSE m.status
                END AS status
            FROM public.mesa m
            LEFT JOIN ultimo_pedido up ON up.id_mesa = m.id
            WHERE m.ativo = TRUE
        SQL);

        $this->addSql(<<<'SQL'
            CREATE OR REPLACE VIEW public.vw_status_mesa_contagem AS
            SELECT
                status,
                COUNT(*) AS total
            FROM public.vw_status_mesa
            GROUP BY status
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS public.vw_status_mesa_contagem');
        $this->addSql('DROP VIEW IF EXISTS public.vw_status_mesa');
    }
}
