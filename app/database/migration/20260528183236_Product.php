<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528183236 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Product';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE product (
                id            BIGSERIAL        PRIMARY KEY,
                descricao     VARCHAR(255)     NOT NULL,
                codigo_barras VARCHAR(50)      NULL,
                valor_custo   NUMERIC(15,2)    NOT NULL DEFAULT 0,
                valor_venda   NUMERIC(15,2)    NOT NULL DEFAULT 0,
                estoque       INTEGER          NOT NULL DEFAULT 0,
                ativo         BOOLEAN          NOT NULL DEFAULT TRUE,
                criado_em     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT uq_product_codigo_barras UNIQUE (codigo_barras)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_product_descricao ON product (descricao)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS product');
    }
}
