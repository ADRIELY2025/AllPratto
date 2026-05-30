<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528183810 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Kitchen';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE kitchen (
                id             BIGSERIAL     PRIMARY KEY,
                order_item_id  BIGINT        NOT NULL REFERENCES order_item(id) ON DELETE CASCADE,
                order_id       BIGINT        NOT NULL,
                mesa           INTEGER       NOT NULL,
                product_id     BIGINT        NULL,
                product_name   VARCHAR(255)  NOT NULL,
                quantidade     INTEGER       NOT NULL,
                observacao     TEXT          NULL,
                payment_method VARCHAR(100)  NULL,
                status         VARCHAR(30)   NOT NULL DEFAULT 'Awaiting',
                received_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL);

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_kitchen_active_queue
            ON public.kitchen (received_at ASC) WHERE status IN (\'Awaiting\', \'Preparing\')');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_kitchen_mesa_status  ON public.kitchen (mesa, status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_kitchen_order        ON public.kitchen (order_id, status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_kitchen_status       ON public.kitchen (status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_kitchen_order_item   ON public.kitchen (order_item_id)');

        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.fn_order_item_to_kitchen()
            RETURNS TRIGGER LANGUAGE plpgsql AS $$
            DECLARE
                v_product_name   TEXT;
                v_payment_method TEXT;
                v_mesa           INTEGER;
            BEGIN
                SELECT descricao INTO v_product_name FROM public.product WHERE id = NEW.product_id;
                SELECT o.mesa, pt.descricao
                INTO   v_mesa, v_payment_method
                FROM   public."order" o
                LEFT JOIN public.payment_terms pt ON pt.id = o.payment_terms_id
                WHERE  o.id = NEW.order_id;

                INSERT INTO public.kitchen (
                    order_item_id, order_id, mesa, product_id, product_name,
                    quantidade, observacao, payment_method, status, received_at, updated_at
                ) VALUES (
                    NEW.id, NEW.order_id, v_mesa, NEW.product_id,
                    COALESCE(v_product_name, NEW.nome),
                    NEW.quantidade, NULL, v_payment_method, 'Awaiting', NOW(), NOW()
                );
                RETURN NEW;
            END; $$
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TRIGGER trg_order_item_to_kitchen
            AFTER INSERT ON public.order_item
            FOR EACH ROW EXECUTE FUNCTION public.fn_order_item_to_kitchen()
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS trg_order_item_to_kitchen ON public.order_item');
        $this->addSql('DROP FUNCTION IF EXISTS public.fn_order_item_to_kitchen()');
        $this->addSql('DROP TABLE IF EXISTS kitchen');
    }
}
