<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525211635 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria tabela stock_movement com FKs e colunas de tipo ENUM customizado';
    }
 
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('stock_movement');
 
        $table->addColumn('id',                 'bigint',  ['autoincrement' => true]);
        $table->addColumn('id_item_compra',     'bigint',  ['notnull' => false]);
        $table->addColumn('id_item_venda',      'bigint',  ['notnull' => false]);
        $table->addColumn('id_produto',         'bigint',  ['notnull' => false]);
        $table->addColumn('quantidade_entrada', 'decimal', ['notnull' => false, 'precision' => 18, 'scale' => 4]);
        $table->addColumn('quantidade_saida',   'decimal', ['notnull' => false, 'precision' => 18, 'scale' => 4]);
        $table->addColumn('observacao',         'text',    ['notnull' => false]);
        $table->addColumn('data_cadastro',      'datetime',['notnull' => false, 'default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('data_atualizacao',   'datetime',['notnull' => false, 'default' => 'CURRENT_TIMESTAMP']);
 
        $table->setPrimaryKey(['id']);
 
        // FKs
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_movement
                ADD CONSTRAINT fk_stock_movement_item_compra
                    FOREIGN KEY (id_item_compra)
                    REFERENCES item_purchase(id)
                    ON DELETE CASCADE ON UPDATE NO ACTION
        SQL);
 
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_movement
                ADD CONSTRAINT fk_stock_movement_item_venda
                    FOREIGN KEY (id_item_venda)
                    REFERENCES item_sale(id)
                    ON DELETE CASCADE ON UPDATE NO ACTION
        SQL);
 
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_movement
                ADD CONSTRAINT fk_stock_movement_produto
                    FOREIGN KEY (id_produto)
                    REFERENCES product(id)
                    ON DELETE CASCADE ON UPDATE NO ACTION
        SQL);
 
        // Colunas com ENUM customizado (criados na migration anterior)
        $this->addSql('ALTER TABLE stock_movement ADD COLUMN tipo             stock_movement_direction');
        $this->addSql('ALTER TABLE stock_movement ADD COLUMN origem_movimento stock_movement_origin');
    }
 
    public function down(Schema $schema): void
    {
        $schema->dropTable('stock_movement');
    }
}