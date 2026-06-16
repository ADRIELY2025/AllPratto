<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528183547 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Order_Item';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE order_item (
                id         BIGSERIAL      PRIMARY KEY,
                order_id   BIGINT         NOT NULL REFERENCES "order"(id) ON DELETE CASCADE,
                nome       VARCHAR(255)   NOT NULL,
                preco      NUMERIC(15,2)  NOT NULL,
                quantidade INTEGER        NOT NULL DEFAULT 1,
                subtotal   NUMERIC(15,2)  NOT NULL
            )
        SQL);

        $this->addSql('CREATE INDEX idx_order_item_order_id ON order_item (order_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS order_item');
    }
}
