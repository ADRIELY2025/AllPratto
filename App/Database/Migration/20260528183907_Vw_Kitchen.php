<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/*
 * ALTERAÇÕES em relação à versão original:
 *  - Expõe numero_mesa via JOIN com mesa
 *  - Expõe nome_cliente via JOIN com customer
 *  - Expõe titulo da forma de pagamento via JOIN com payment_terms
 *  - Remove o VARCHAR solto payment_method (excluído da kitchen)
 */

final class Version20260528183907 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vw_Kitchen';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE VIEW public.vw_kitchen AS
            SELECT
                k.id,
                k.order_id,
                k.id_mesa,
                m.numero        AS numero_mesa,
                k.id_cliente,
                c.nome_fantasia AS nome_cliente,
                k.id_produto,
                k.product_name,
                k.quantidade,
                k.observacao,
                k.payment_terms_id,
                pt.titulo       AS forma_pagamento,
                k.status,
                k.received_at,
                k.updated_at
            FROM public.kitchen k
            INNER JOIN public.mesa m
                    ON m.id = k.id_mesa
            LEFT JOIN public.customer c
                   ON c.id = k.id_cliente
            LEFT JOIN public.payment_terms pt
                   ON pt.id = k.payment_terms_id
            ORDER BY k.received_at ASC
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS public.vw_kitchen');
    }
}
