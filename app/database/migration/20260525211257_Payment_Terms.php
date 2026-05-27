<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525211257 extends AbstractMigration
{
      public function getDescription(): string
    {
        return 'Payment_Terms';
    }
 
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('payment_terms');
 
        $table->addColumn('id',            'bigint',   ['autoincrement' => true]);
        $table->addColumn('codigo',        'string',   ['length' => 50,  'notnull' => false]);
        $table->addColumn('titulo',        'string',   ['length' => 255, 'notnull' => false]);
        $table->addColumn('descricao',     'text',     ['notnull' => false]);
        $table->addColumn('atalho',        'string',   ['length' => 50,  'notnull' => false]);
        $table->addColumn('parcelas',      'integer',  ['notnull' => false, 'default' => 1]);
        $table->addColumn('criado_em',     'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('atualizado_em', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);
 
        $table->setPrimaryKey(['id']);
        $table->addIndex(['titulo']);
        $table->addIndex(['codigo']);
    }
 
    public function down(Schema $schema): void
    {
        $schema->dropTable('payment_terms');
    }
}
 