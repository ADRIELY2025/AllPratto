<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520212229 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Company';
    }

     public function up(Schema $schema): void
    {
        $table = $schema->createTable('company');
 
        $table->addColumn('id',                 'bigint',   ['autoincrement' => true]);
        $table->addColumn('nome_fantasia',       'string',   ['length' => 255]);
        $table->addColumn('razao_social',        'string',   ['length' => 255, 'notnull' => false]);
        $table->addColumn('cnpj',               'string',   ['length' => 18]);
        $table->addColumn('inscricao_estadual', 'string',   ['length' => 30,  'notnull' => false]);
        $table->addColumn('telefone',           'string',   ['length' => 20,  'notnull' => false]);
        $table->addColumn('email',              'string',   ['length' => 255, 'notnull' => false]);
        $table->addColumn('endereco',           'string',   ['length' => 255, 'notnull' => false]);
        $table->addColumn('numero',             'string',   ['length' => 20,  'notnull' => false]);
        $table->addColumn('bairro',             'string',   ['length' => 100, 'notnull' => false]);
        $table->addColumn('cidade',             'string',   ['length' => 100, 'notnull' => false]);
        $table->addColumn('estado',             'string',   ['length' => 2,   'notnull' => false]);
        $table->addColumn('cep',                'string',   ['length' => 10,  'notnull' => false]);
        $table->addColumn('ativo',              'boolean',  ['default' => true]);
        $table->addColumn('criado_em',          'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('atualizado_em',      'datetime', ['default' => 'CURRENT_TIMESTAMP']);
 
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['cnpj']);
        $table->addIndex(['nome_fantasia']);
        $table->addIndex(['cidade']);
        $table->addIndex(['estado']);
    }
 
    public function down(Schema $schema): void
    {
        $schema->dropTable('company');
    }
}