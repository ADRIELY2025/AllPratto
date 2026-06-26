<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260625200812 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix_Order_Item_Product_Id';
    }

   public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                -- Adiciona product_id se não existir
                IF NOT EXISTS (
                    SELECT 1
                    FROM   information_schema.columns
                    WHERE  table_name  = 'order_item'
                    AND    column_name = 'product_id'
                ) THEN
                    ALTER TABLE order_item
                        ADD COLUMN product_id BIGINT NULL REFERENCES product(id) ON DELETE SET NULL;
                END IF;

                -- Adiciona subtotal se não existir (pode estar faltando também)
                IF NOT EXISTS (
                    SELECT 1
                    FROM   information_schema.columns
                    WHERE  table_name  = 'order_item'
                    AND    column_name = 'subtotal'
                ) THEN
                    ALTER TABLE order_item
                        ADD COLUMN subtotal NUMERIC(15,2) NOT NULL DEFAULT 0;
                END IF;
            END
            $$;
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Reversão intencional vazia — remover colunas pode apagar dados
    }
}