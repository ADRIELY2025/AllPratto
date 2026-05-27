<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526202055 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'VW_order';
    }

    public function up(Schema $schema): void
    {
         // ── 4. View vw_pedido ─────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE VIEW public.vw_pedido AS
            SELECT
                o.id,
                o.mesa,
                o.pagamento,
                pt.descricao  AS condicao_pagamento,
                pt.parcelas,
                o.total,
                o.status,
                o.observacao,
                COUNT(oi.id)  AS total_itens,
                o.criado_em,
                o.atualizado_em
            FROM public.order o
            LEFT JOIN public.payment_terms pt
                   ON pt.id = o.payment_terms_id
            LEFT JOIN public.order_item oi
                   ON oi.order_id = o.id
            GROUP BY
                o.id,
                o.mesa,
                o.pagamento,
                pt.descricao,
                pt.parcelas,
                o.total,
                o.status,
                o.observacao,
                o.criado_em,
                o.atualizado_em
        SQL);
    }

    public function down(Schema $schema): void
    {
        // escreva aqui o rollback do up()
    }
}