<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525213307 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria tabela item_purchase — itens de compra vinculados a purchase e product';
    }
 
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('item_purchase');
 
        $table->addColumn('id',              'bigint',  ['autoincrement' => true]);
        $table->addColumn('id_compra',       'bigint',  ['notnull' => false]);
        $table->addColumn('id_produto',      'bigint',  ['notnull' => false]);
        $table->addColumn('quantidade',      'decimal', ['notnull' => false, 'precision' => 18, 'scale' => 4]);
        $table->addColumn('total_bruto',     'decimal', ['notnull' => false, 'precision' => 18, 'scale' => 4]);
        $table->addColumn('total_liquido',   'decimal', ['notnull' => false, 'precision' => 18, 'scale' => 4, 'comment' => 'Valor a ser pago produto.']);
        $table->addColumn('preco_unitario',  'decimal', ['notnull' => false, 'precision' => 18, 'scale' => 4]);
        $table->addColumn('desconto',        'decimal', ['notnull' => false, 'precision' => 18, 'scale' => 4]);
        $table->addColumn('acrescimo',       'decimal', ['notnull' => false, 'precision' => 18, 'scale' => 4]);
        $table->addColumn('nome',            'text',    ['notnull' => false]);
        $table->addColumn('data_cadastro',   'datetime',['notnull' => false, 'default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('data_atualizacao','datetime',['notnull' => false, 'default' => 'CURRENT_TIMESTAMP']);
 
        $table->setPrimaryKey(['id']);
 
        $this->addSql(<<<'SQL'
            ALTER TABLE item_purchase
                ADD CONSTRAINT fk_item_purchase_compra
                    FOREIGN KEY (id_compra)
                    REFERENCES purchase(id)
                    ON DELETE CASCADE ON UPDATE NO ACTION
        SQL);
 
        $this->addSql(<<<'SQL'
            ALTER TABLE item_purchase
                ADD CONSTRAINT fk_item_purchase_produto
                    FOREIGN KEY (id_produto)
                    REFERENCES product(id)
                    ON DELETE CASCADE ON UPDATE NO ACTION
        SQL);
    }
 
    public function down(Schema $schema): void
    {
        $schema->dropTable('item_purchase');
    }
}