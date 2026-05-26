<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525213057 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria tabela purchase com FK para supplier e coluna ENUM estado_compra';
    }
 
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('purchase');
 
        $table->addColumn('id',              'bigint',  ['autoincrement' => true]);
        $table->addColumn('id_fornecedor',   'bigint',  ['notnull' => false]);
        $table->addColumn('total_bruto',     'decimal', ['notnull' => false, 'precision' => 18, 'scale' => 4]);
        $table->addColumn('total_liquido',   'decimal', ['notnull' => false, 'precision' => 18, 'scale' => 4, 'comment' => 'Valor a ser pago pelo cliente.']);
        $table->addColumn('desconto',        'decimal', ['notnull' => false, 'precision' => 18, 'scale' => 4]);
        $table->addColumn('acrescimo',       'decimal', ['notnull' => false, 'precision' => 18, 'scale' => 4]);
        $table->addColumn('observacao',      'text',    ['notnull' => false]);
        $table->addColumn('data_cadastro',   'datetime',['notnull' => false, 'default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('data_atualizacao','datetime',['notnull' => false, 'default' => 'CURRENT_TIMESTAMP']);
 
        $table->setPrimaryKey(['id']);
 
        $this->addSql(<<<'SQL'
            ALTER TABLE purchase
                ADD CONSTRAINT fk_purchase_supplier
                    FOREIGN KEY (id_fornecedor)
                    REFERENCES supplier(id)
                    ON DELETE CASCADE ON UPDATE NO ACTION
        SQL);
 
        // ENUM customizado criado na migration Version20256755885858
        $this->addSql('ALTER TABLE purchase ADD COLUMN estado_compra stock_movement_compra');
    }
 
    public function down(Schema $schema): void
    {
        $schema->dropTable('purchase');
    }
}