<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526210100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stock_Movement - movimentações de estoque com ENUMs de tipo e origem';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('stock_movement');

        $table->addColumn('id',                  'bigint',   ['autoincrement' => true]);
        $table->addColumn('id_item_compra',      'bigint',   ['notnull' => false]);
        $table->addColumn('id_item_venda',       'bigint',   ['notnull' => false]);
        $table->addColumn('id_produto',          'bigint',   ['notnull' => false]);
        $table->addColumn('quantidade_entrada',  'decimal',  ['precision' => 18, 'scale' => 4, 'notnull' => false]);
        $table->addColumn('quantidade_saida',    'decimal',  ['precision' => 18, 'scale' => 4, 'notnull' => false]);
        $table->addColumn('observacao',          'text',     ['notnull' => false]);
        $table->addColumn('data_cadastro',       'datetime', ['notnull' => false, 'default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('data_atualizacao',    'datetime', ['notnull' => false, 'default' => 'CURRENT_TIMESTAMP']);

        $table->setPrimaryKey(['id']);

        $table->addForeignKeyConstraint(
            'item_sale',
            ['id_item_venda'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'fk_stock_movement_item_venda'
        );
        $table->addForeignKeyConstraint(
            'product',
            ['id_produto'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'fk_stock_movement_produto'
        );

        // Colunas com ENUM criado na migration Custom_Types
        $this->addSql('ALTER TABLE stock_movement ADD COLUMN tipo            stock_movement_direction');
        $this->addSql('ALTER TABLE stock_movement ADD COLUMN origem_movimento stock_movement_origin');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('stock_movement');
    }
}
