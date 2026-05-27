<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526204548 extends AbstractMigration
{
     public function getDescription(): string
    {
        return 'Kitchen - tabela operacional e trigger automático de order_item';
    }
 
    public function up(Schema $schema): void
    {
        $kitchen = $schema->createTable('kitchen');
 
        $kitchen->addColumn('id',             'bigint',   ['autoincrement' => true]);
        $kitchen->addColumn('order_item_id',  'bigint',   ['notnull' => true]);
        $kitchen->addColumn('order_id',       'bigint',   ['notnull' => true]);
        $kitchen->addColumn('mesa',           'integer',  ['notnull' => true]);
        $kitchen->addColumn('product_id',     'bigint',   ['notnull' => false]);
        $kitchen->addColumn('product_name',   'string',   ['length' => 255]);
        $kitchen->addColumn('quantidade',     'integer',  ['notnull' => true]);
        $kitchen->addColumn('observacao',     'text',     ['notnull' => false]);
        $kitchen->addColumn('payment_method', 'string',   ['length' => 100, 'notnull' => false]);
        $kitchen->addColumn('status',         'string',   ['length' => 30,  'default' => 'Awaiting']);
        $kitchen->addColumn('received_at',    'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $kitchen->addColumn('updated_at',     'datetime', ['default' => 'CURRENT_TIMESTAMP']);
 
        $kitchen->setPrimaryKey(['id']);
        $kitchen->addForeignKeyConstraint(
            'order_item',
            ['order_item_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'fk_kitchen_order_item'
        );
 
        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_kitchen_active_queue
                ON public.kitchen (received_at ASC)
                WHERE status IN ('Awaiting', 'Preparing')
        SQL);
 
        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_kitchen_mesa_status
                ON public.kitchen (mesa, status)
        SQL);
 
        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_kitchen_order
                ON public.kitchen (order_id, status)
        SQL);
 
        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_kitchen_status
                ON public.kitchen (status)
        SQL);
 
        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_kitchen_order_item
                ON public.kitchen (order_item_id)
        SQL);
 
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.fn_order_item_to_kitchen()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_product_name   TEXT;
                v_payment_method TEXT;
                v_mesa           INTEGER;
            BEGIN
                SELECT descricao
                INTO   v_product_name
                FROM   public.product
                WHERE  id = NEW.product_id;
 
                SELECT o.mesa, pt.descricao
                INTO   v_mesa, v_payment_method
                FROM   public.order o
                LEFT JOIN public.payment_terms pt
                       ON pt.id = o.payment_terms_id
                WHERE  o.id = NEW.order_id;
 
                INSERT INTO public.kitchen (
                    order_item_id,
                    order_id,
                    mesa,
                    product_id,
                    product_name,
                    quantidade,
                    observacao,
                    payment_method,
                    status,
                    received_at,
                    updated_at
                ) VALUES (
                    NEW.id,
                    NEW.order_id,
                    v_mesa,
                    NEW.product_id,
                    COALESCE(v_product_name, NEW.nome),
                    NEW.quantidade,
                    NULL,
                    v_payment_method,
                    'Awaiting',
                    NOW(),
                    NOW()
                );
 
                RETURN NEW;
            END;
            $$
        SQL);
 
        $this->addSql(<<<'SQL'
            CREATE TRIGGER trg_order_item_to_kitchen
            AFTER INSERT ON public.order_item
            FOR EACH ROW
            EXECUTE FUNCTION public.fn_order_item_to_kitchen()
        SQL);
    }
 
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER  IF EXISTS trg_order_item_to_kitchen ON public.order_item');
        $this->addSql('DROP FUNCTION IF EXISTS public.fn_order_item_to_kitchen()');
 
        $this->addSql('DROP INDEX IF EXISTS public.idx_kitchen_active_queue');
        $this->addSql('DROP INDEX IF EXISTS public.idx_kitchen_mesa_status');
        $this->addSql('DROP INDEX IF EXISTS public.idx_kitchen_order');
        $this->addSql('DROP INDEX IF EXISTS public.idx_kitchen_status');
        $this->addSql('DROP INDEX IF EXISTS public.idx_kitchen_order_item');
 
        $schema->dropTable('kitchen');
    }
}