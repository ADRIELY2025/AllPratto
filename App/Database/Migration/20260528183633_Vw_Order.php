<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528183633 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vw_Order';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE VIEW public.vw_pedido AS
            SELECT
                o.id,
                o.mesa,
                o.pagamento,
                pt.titulo       AS condicao_pagamento,
                pt.descricao,
                pt.parcelas,
                o.total,
                o.status,
                o.observacao,
                COUNT(oi.id)    AS total_itens,
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
                pt.titulo,
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
        $this->addSql('DROP VIEW IF EXISTS public.vw_pedido');
    }
}