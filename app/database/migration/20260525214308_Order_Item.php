<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525214308 extends AbstractMigration
{
   public function getDescription(): string
    {
        return 'OrderItem - tabela base de itens do pedido com FKs e índices';
    }

    public function up(Schema $schema): void
    {
        // ────────────────────────────────────────────────────────
        //  TABELA: order_item
        //
        //  Tabela base de escrita do fluxo de pedidos.
        //  Cada linha representa 1 produto dentro de 1 compra.
        //
        //  Dependências (devem existir antes desta migration):
        //    → customer       (20260504145130)
        //    → cardapio_item  (20260522100000)
        //    → payment_terms  (20260522110000)
        //
        //  Após INSERT aqui, o trigger trg_pedido_para_cozinha
        //  popula automaticamente a tabela cozinha.
        // ────────────────────────────────────────────────────────
        $table = $schema->createTable('order_item');

        $table->addColumn('id', 'bigint', ['autoincrement' => true]);

        $table->addColumn('id_compra', 'bigint');

        $table->addColumn('id_cliente', 'bigint', [
            'notnull' => false,
        ]);

        $table->addColumn('id_produto', 'bigint');

        $table->addColumn('id_payment_terms', 'bigint');

        $table->addColumn('id_mesa', 'integer');

        $table->addColumn('quantidade', 'integer', [
            'default' => 1,
        ]);

        $table->addColumn('preco_unitario', 'decimal', [
            'precision' => 15,
            'scale'     => 2,
        ]);

        $table->addColumn('subtotal', 'decimal', [
            'precision' => 15,
            'scale'     => 2,
        ]);

        $table->addColumn('observacao', 'text', [
            'notnull' => false,
        ]);

        $table->addColumn('criado_em', 'datetime', [
            'default' => 'CURRENT_TIMESTAMP',
        ]);

        $table->addColumn('atualizado_em', 'datetime', [
            'default' => 'CURRENT_TIMESTAMP',
        ]);

        $table->setPrimaryKey(['id']);

        $table->addIndex(['id_compra']);
        $table->addIndex(['id_cliente']);
        $table->addIndex(['id_produto']);
        $table->addIndex(['id_payment_terms']);
        $table->addIndex(['id_mesa']);
        $table->addIndex(['criado_em']);

        $this->addSql(<<<'SQL'
            ALTER TABLE order_item
                ADD CONSTRAINT fk_order_item_cliente
                    FOREIGN KEY (id_cliente)
                    REFERENCES customer(id)
                    ON DELETE SET NULL,

                ADD CONSTRAINT fk_order_item_produto
                    FOREIGN KEY (id_produto)
                    REFERENCES cardapio_item(id)
                    ON DELETE RESTRICT,

                ADD CONSTRAINT fk_order_item_payment
                    FOREIGN KEY (id_payment_terms)
                    REFERENCES payment_terms(id)
                    ON DELETE RESTRICT
        SQL);

        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.fn_order_item_updated_at()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                NEW.atualizado_em = NOW();
                RETURN NEW;
            END;
            $$
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TRIGGER trg_order_item_updated_at
            BEFORE UPDATE ON public.order_item
            FOR EACH ROW
            EXECUTE FUNCTION public.fn_order_item_updated_at()
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER  IF EXISTS trg_order_item_updated_at ON public.order_item');
        $this->addSql('DROP FUNCTION IF EXISTS public.fn_order_item_updated_at()');
        $this->addSql('DROP TABLE    IF EXISTS public.order_item');
    }
}
