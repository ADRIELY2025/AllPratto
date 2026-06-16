<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260608235444 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Purchase';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE purchase (
                id               BIGSERIAL                  PRIMARY KEY,
                id_fornecedor    BIGINT                     NULL,
                total_bruto      NUMERIC(18,4)              NULL,
                total_liquido    NUMERIC(18,4)              NULL,
                desconto         NUMERIC(18,4)              NULL,
                acrescimo        NUMERIC(18,4)              NULL,
                observacao       TEXT                       NULL,
                estado_compra    stock_movement_compra      NULL DEFAULT 'EM_ANDAMENTO',
                data_cadastro    TIMESTAMP                  NULL DEFAULT CURRENT_TIMESTAMP,
                data_atualizacao TIMESTAMP                  NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_purchase_supplier
                    FOREIGN KEY (id_fornecedor)
                    REFERENCES supplier (id)
                    ON DELETE CASCADE
                    ON UPDATE NO ACTION
            )
        SQL);

        $this->addSql('CREATE INDEX idx_purchase_fornecedor    ON purchase (id_fornecedor)');
        $this->addSql('CREATE INDEX idx_purchase_estado_compra ON purchase (estado_compra)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS purchase');
    }
}