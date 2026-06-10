<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

// CORRIGIDO: usava ON m.id = o.mesa (INTEGER solto).
// Agora usa ON m.id = o.id_mesa (FK correta da migration refatorada).

final class Version20260609100001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vw_Faturamento_Mesa';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE VIEW public.vw_faturamento_mesa AS
            SELECT
                m.id                            AS id_mesa,
                m.numero                        AS numero_mesa,
                COUNT(o.id)                     AS total_pedidos,
                COALESCE(SUM(o.total), 0)       AS faturamento_total,
                COALESCE(AVG(o.total), 0)       AS ticket_medio,
                MAX(o.criado_em)                AS ultimo_pedido
            FROM public.mesa m
            LEFT JOIN public."order" o
                   ON o.id_mesa = m.id
            GROUP BY m.id, m.numero
            ORDER BY m.numero
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS public.vw_faturamento_mesa');
    }
}
