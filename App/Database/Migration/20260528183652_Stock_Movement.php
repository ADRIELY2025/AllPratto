<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

// CORRIGIDO: id_item_compra era BIGINT sem FK declarada.
// Agora referencia item_purchase(id) ON DELETE SET NULL.

final class Version20260528183652 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stock_Movement';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE stock_movement (
                id                 BIGSERIAL      PRIMARY KEY,
                id_item_compra     BIGINT         NULL,
                id_item_venda      BIGINT         NULL REFERENCES item_sale(id)    ON DELETE SET NULL,
                id_produto         BIGINT         NULL REFERENCES product(id)      ON DELETE SET NULL,
                quantidade_entrada NUMERIC(18,4)  NULL,
                quantidade_saida   NUMERIC(18,4)  NULL,
                observacao         TEXT           NULL,
                data_cadastro      TIMESTAMP      NULL DEFAULT CURRENT_TIMESTAMP,
                data_atualizacao   TIMESTAMP      NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL);

        // 2. Adiciona as colunas com os tipos ENUM personalizados do PostgreSQL
        $this->addSql('ALTER TABLE stock_movement ADD COLUMN tipo stock_movement_direction');
        $this->addSql('ALTER TABLE stock_movement ADD COLUMN origem_movimento stock_movement_origin');

        // 3. Cria o índice
        $this->addSql('CREATE INDEX idx_stock_movement_produto ON stock_movement (id_produto)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS stock_movement');
    }
}
