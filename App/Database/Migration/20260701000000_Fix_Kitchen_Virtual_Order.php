<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A migration Virtual_Order (20260629000001) tornou "order".id_mesa nullable
 * para suportar pedidos virtuais (iFood / telefone), mas esqueceu de propagar
 * isso para a tabela "kitchen" — que é alimentada automaticamente pelo trigger
 * fn_order_item_to_kitchen() e ainda exigia id_mesa NOT NULL.
 *
 * Resultado: qualquer pedido virtual (sem mesa) quebrava com
 * "null value in column id_mesa of relation kitchen violates not-null constraint"
 * assim que o primeiro item do pedido era inserido.
 *
 * Esta migration:
 *  1. Torna kitchen.id_mesa nullable (com FK ON DELETE SET NULL).
 *  2. Corrige a view vw_kitchen para LEFT JOIN mesa (antes era INNER JOIN,
 *     o que faria pedidos virtuais desaparecerem da view mesmo depois do
 *     id_mesa aceitar NULL) e expõe "Pedido Virtual" quando não há mesa.
 */
final class Version20260701000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Torna kitchen.id_mesa nullable e corrige vw_kitchen para suportar pedidos virtuais';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kitchen ALTER COLUMN id_mesa DROP NOT NULL');

        $this->addSql('ALTER TABLE kitchen DROP CONSTRAINT IF EXISTS kitchen_id_mesa_fkey');
        $this->addSql('ALTER TABLE kitchen ADD CONSTRAINT kitchen_id_mesa_fkey
            FOREIGN KEY (id_mesa) REFERENCES mesa(id) ON DELETE SET NULL');

        $this->addSql('DROP VIEW IF EXISTS public.vw_kitchen');

        $this->addSql(<<<'SQL'
            CREATE VIEW public.vw_kitchen AS
            SELECT
                k.id,
                k.order_id,
                k.id_mesa,
                COALESCE('Mesa ' || m.numero::text, 'Pedido Virtual') AS numero_mesa,
                k.id_cliente,
                 TRIM(
                    COALESCE(c.nome_fantasia, '') || ' ' ||
                    COALESCE(c.sobrenome_razao, '')
                ) AS nome_cliente,
                k.id_produto,
                k.product_name,
                k.quantidade,
                k.observacao,
                k.payment_terms_id,
                pt.titulo AS forma_pagamento,
                k.status,
                k.received_at,
                k.updated_at
            FROM public.kitchen k
            LEFT JOIN public.mesa m
                ON m.id = k.id_mesa
            LEFT JOIN public.customer c
                ON c.id = k.id_cliente
            LEFT JOIN public.payment_terms pt
               ON pt.id = k.payment_terms_id;
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS public.vw_kitchen');

        $this->addSql(<<<'SQL'
            CREATE VIEW public.vw_kitchen AS
            SELECT
                k.id,
                k.order_id,
                k.id_mesa,
                m.numero AS numero_mesa,
                k.id_cliente,
                 TRIM(
                    COALESCE(c.nome_fantasia, '') || ' ' ||
                    COALESCE(c.sobrenome_razao, '')
                ) AS nome_cliente,
                k.id_produto,
                k.product_name,
                k.quantidade,
                k.observacao,
                k.payment_terms_id,
                pt.titulo AS forma_pagamento,
                k.status,
                k.received_at,
                k.updated_at
            FROM public.kitchen k
            INNER JOIN public.mesa m
                ON m.id = k.id_mesa
            LEFT JOIN public.customer c
                ON c.id = k.id_cliente
            LEFT JOIN public.payment_terms pt
               ON pt.id = k.payment_terms_id;
        SQL);

        // Atenção: reverter exige que não existam registros de kitchen com id_mesa NULL
        // (pedidos virtuais precisariam ser removidos ou associados a uma mesa antes).
        $this->addSql('ALTER TABLE kitchen DROP CONSTRAINT IF EXISTS kitchen_id_mesa_fkey');
        $this->addSql('ALTER TABLE kitchen ALTER COLUMN id_mesa SET NOT NULL');
        $this->addSql('ALTER TABLE kitchen ADD CONSTRAINT kitchen_id_mesa_fkey
            FOREIGN KEY (id_mesa) REFERENCES mesa(id) ON DELETE RESTRICT');
    }
}
