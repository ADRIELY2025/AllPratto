<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

// stock_movement.id_item_compra não pôde ter FK declarada na sua migration
// porque item_purchase ainda não existia naquele ponto.
// Esta migration adiciona a FK agora que item_purchase já foi criada.

final class Version20260609100006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fk_Stock_Item_Purchase';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE public.stock_movement
                ADD CONSTRAINT fk_stock_movement_item_purchase
                FOREIGN KEY (id_item_compra)
                REFERENCES public.item_purchase(id)
                ON DELETE SET NULL
        SQL);

        $this->addSql('CREATE INDEX idx_stock_movement_item_compra ON stock_movement (id_item_compra)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE public.stock_movement DROP CONSTRAINT IF EXISTS fk_stock_movement_item_purchase');
        $this->addSql('DROP INDEX IF EXISTS idx_stock_movement_item_compra');
    }
}
