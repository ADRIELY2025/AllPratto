<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/*
 * ALTERAÇÕES em relação à versão original:
 *  - JOIN com mesa via id_mesa (antes usava o inteiro solto)
 *  - Expõe numero_mesa e capacidade_mesa
 *  - Expõe id_cliente e nome do cliente via JOIN com customer
 *  - Removido campo `pagamento` (VARCHAR solto que foi excluído do order)
 */

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
                m.id            AS id_mesa,
                m.numero        AS numero_mesa,
                m.capacidade    AS capacidade_mesa,
                c.id            AS id_cliente,
                c.nome_fantasia AS nome_cliente,
                pt.id           AS id_forma_pagamento,
                pt.titulo       AS forma_pagamento,
                pt.descricao    AS descricao_pagamento,
                pt.parcelas,
                o.total,
                o.status,
                o.observacao,
                COUNT(oi.id)    AS total_itens,
                o.criado_em,
                o.atualizado_em
            FROM public."order" o
            INNER JOIN public.mesa m
                    ON m.id = o.id_mesa
            LEFT JOIN public.customer c
                   ON c.id = o.id_cliente
            LEFT JOIN public.payment_terms pt
                   ON pt.id = o.payment_terms_id
            LEFT JOIN public.order_item oi
                   ON oi.order_id = o.id
            GROUP BY
                o.id, m.id, m.numero, m.capacidade,
                c.id, c.nome_fantasia,
                pt.id, pt.titulo, pt.descricao, pt.parcelas,
                o.total, o.status, o.observacao,
                o.criado_em, o.atualizado_em
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS public.vw_pedido');
    }
}
