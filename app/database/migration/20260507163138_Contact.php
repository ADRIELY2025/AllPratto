<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507163138 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Contact';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('contact');

        $table->addColumn('id',            'bigint',   ['autoincrement' => true]);
        $table->addColumn('id_usuario',    'bigint',   ['notnull' => false]);
        $table->addColumn('id_cliente',    'bigint',   ['notnull' => false]);
        $table->addColumn('tipo',          'string',   ['length' => 20, 'notnull' => false]);
        $table->addColumn('contato',       'text',     ['notnull' => false]);
        $table->addColumn('criado_em',     'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('atualizado_em', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['contato']);
        $table->addIndex(['tipo']);
        $table->addForeignKeyConstraint('users', ['id_usuario'], ['id'], ['onDelete' => 'CASCADE']);
        $table->addForeignKeyConstraint('customer', ['id_cliente'], ['id'], ['onDelete' => 'CASCADE']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('contact');
    }
}
