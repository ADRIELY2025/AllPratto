<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525212815 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria tabela item_sale — itens vendidos, com FKs para sale e product';
    }
 
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('item_sale');
 
        $table->addOption('comment', 'Tabela de itens vendidos');
 
        $table->addColumn('id',               'bigint',  ['autoincrement' => true]);
        $table->addColumn('id_venda',         'bigint',  ['notnull' => false]);
        $table->addColumn('id_produto',       'bigint',  ['notnull' => false]);
        $table->addColumn('descricao',        'text',    ['notnull' => false]);
        $table->addColumn('quantidade',       'decimal', ['notnull' => false, 'precision' => 18, 'scale' => 4]);
        $table->addColumn('total_bruto',      'decimal', ['notnull' => false, 'precision' => 18, 'scale' => 4]);
        $table->addColumn('unitario_bruto',   'decimal', ['notnull' => false, 'precision' => 18, 'scale' => 4]);
        $table->addColumn('total_liquido',    'decimal', ['notnull' => false, 'precision' => 18, 'scale' => 4]);
        $table->addColumn('unitario_liquido', 'decimal', ['notnull' => false, 'precision' => 18, 'scale' => 4]);
        $table->addColumn('desconto',         'decimal', ['notnull' => false, 'precision' => 18, 'scale' => 4]);
        $table->addColumn('acrescimo',        'decimal', ['notnull' => false, 'precision' => 18, 'scale' => 4]);
        $table->addColumn('nome',             'text',    ['notnull' => false]);
        $table->addColumn('created_at',       'datetime',['notnull' => false, 'default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('updated_at',       'datetime',['notnull' => false, 'default' => 'CURRENT_TIMESTAMP']);
 
        $table->setPrimaryKey(['id']);
 
        $this->addSql(<<<'SQL'
            ALTER TABLE item_sale
                ADD CONSTRAINT fk_item_sale_venda
                    FOREIGN KEY (id_venda)
                    REFERENCES sale(id)
                    ON DELETE CASCADE ON UPDATE NO ACTION
        SQL);
 
        $this->addSql(<<<'SQL'
            ALTER TABLE item_sale
                ADD CONSTRAINT fk_item_sale_produto
                    FOREIGN KEY (id_produto)
                    REFERENCES product(id)
                    ON DELETE CASCADE ON UPDATE NO ACTION
        SQL);
    }
 
    public function down(Schema $schema): void
    {
        $schema->dropTable('item_sale');
    }
}