<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/*
 * ALTERAÇÕES em relação à versão original:
 *  - Removido campo `mesa INTEGER` duplicado → a cozinha busca a mesa pelo order (order.id_mesa)
 *  - Adicionado `id_mesa BIGINT FK → mesa(id)` (desnormalização intencional para performance
 *    na fila da cozinha — evita JOIN a cada refresh da tela)
 *  - Adicionado `id_cliente BIGINT FK → customer(id)` (cozinha pode precisar saber para quem)
 *  - Adicionado `payment_terms_id BIGINT FK → payment_terms(id)` no lugar do VARCHAR solto
 *    `payment_method` — mantém integridade referencial
 *  - Trigger atualizada para propagar as novas FKs automaticamente ao inserir em order_item
 */

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
                id               BIGSERIAL     PRIMARY KEY,
                order_item_id    BIGINT        NOT NULL REFERENCES order_item(id)    ON DELETE CASCADE,
                order_id         BIGINT        NOT NULL REFERENCES "order"(id)       ON DELETE CASCADE,
                id_mesa          BIGINT        NOT NULL REFERENCES mesa(id)          ON DELETE RESTRICT,
                id_cliente       BIGINT        NULL     REFERENCES customer(id)      ON DELETE SET NULL,
                id_produto       BIGINT        NULL     REFERENCES product(id)       ON DELETE SET NULL,
                payment_terms_id BIGINT        NULL     REFERENCES payment_terms(id) ON DELETE SET NULL,
                product_name     VARCHAR(255)  NOT NULL,
                quantidade       INTEGER       NOT NULL,
                observacao       TEXT          NULL,
                status           VARCHAR(30)   NOT NULL DEFAULT 'Awaiting',
                received_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT chk_kitchen_status CHECK (
                    status IN ('Awaiting','Preparing','Ready','Delivered','Cancelled')
                )
            )
        SQL);

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_kitchen_active_queue
            ON public.kitchen (received_at ASC) WHERE status IN (\'Awaiting\', \'Preparing\')');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_kitchen_mesa_status  ON public.kitchen (id_mesa, status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_kitchen_order        ON public.kitchen (order_id, status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_kitchen_status       ON public.kitchen (status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_kitchen_order_item   ON public.kitchen (order_item_id)');

        // -----------------------------------------------------------------------
        // Trigger: ao inserir em order_item, cria automaticamente o registro
        // na fila da cozinha com todas as FKs preenchidas.
        // -----------------------------------------------------------------------
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.fn_order_item_to_kitchen()
            RETURNS TRIGGER LANGUAGE plpgsql AS $$
            DECLARE
                v_product_name   TEXT;
                v_id_mesa        BIGINT;
                v_id_cliente     BIGINT;
                v_payment_id     BIGINT;
            BEGIN
                -- Busca nome do produto
                SELECT descricao INTO v_product_name
                FROM public.product
                WHERE id = NEW.product_id;

                -- Busca dados do pedido (mesa, cliente, forma de pagamento)
                SELECT o.id_mesa, o.id_cliente, o.payment_terms_id
                INTO   v_id_mesa, v_id_cliente, v_payment_id
                FROM   public."order" o
                WHERE  o.id = NEW.order_id;

                INSERT INTO public.kitchen (
                    order_item_id, order_id, id_mesa, id_cliente,
                    id_produto, payment_terms_id,
                    product_name, quantidade, observacao,
                    status, received_at, updated_at
                ) VALUES (
                    NEW.id,
                    NEW.order_id,
                    v_id_mesa,
                    v_id_cliente,
                    NEW.product_id,
                    v_payment_id,
                    COALESCE(v_product_name, NEW.nome),
                    NEW.quantidade,
                    NULL,
                    'Awaiting',
                    NOW(),
                    NOW()
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
