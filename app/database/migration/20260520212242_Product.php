<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520212242 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Product';
    }

     public function up(Schema $schema): void
    {
        $table = $schema->createTable('product');
 
        $table->addColumn('id',            'bigint',   ['autoincrement' => true]);
        $table->addColumn('descricao',     'string',   ['length' => 255]);
        $table->addColumn('codigo_barras', 'string',   ['length' => 50,  'notnull' => false]);
        $table->addColumn('sku',           'string',   ['length' => 50,  'notnull' => false]);
        $table->addColumn('valor_custo',   'decimal',  ['precision' => 15, 'scale' => 2, 'default' => 0]);
        $table->addColumn('valor_venda',   'decimal',  ['precision' => 15, 'scale' => 2, 'default' => 0]);
        $table->addColumn('estoque',       'integer',  ['default' => 0]);
        $table->addColumn('ativo',         'boolean',  ['default' => true]);
        $table->addColumn('criado_em',     'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('atualizado_em', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);
 
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['codigo_barras']);
        $table->addUniqueIndex(['sku']);
        $table->addIndex(['descricao']);
    }
 
    public function down(Schema $schema): void
    {
        $schema->dropTable('product');
    }
}