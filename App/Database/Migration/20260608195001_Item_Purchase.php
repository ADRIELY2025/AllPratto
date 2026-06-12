<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260608235445 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Item Purchase';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE item_purchase (
                id               BIGSERIAL       PRIMARY KEY,
                nome             TEXT            NULL,
                id_compra        BIGINT          NULL,
                id_produto       BIGINT          NULL,
                quantidade       NUMERIC(18,4)   NULL,
                total_bruto      NUMERIC(18,4)   NULL,
                total_liquido    NUMERIC(18,4)   NULL,
                preco_unitario   NUMERIC(18,4)   NULL,
                desconto         NUMERIC(18,4)   NULL,
                acrescimo        NUMERIC(18,4)   NULL,
                data_cadastro    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
                data_atualizacao TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_item_purchase_purchase
                    FOREIGN KEY (id_compra)
                    REFERENCES purchase (id)
                    ON DELETE CASCADE
                    ON UPDATE NO ACTION,
                CONSTRAINT fk_item_purchase_product
                    FOREIGN KEY (id_produto)
                    REFERENCES product (id)
                    ON DELETE CASCADE
                    ON UPDATE NO ACTION
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS item_purchase');
    }
}