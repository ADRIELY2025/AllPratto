<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525212313 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria tabela sale com FK para customer e coluna estado_venda';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('sale');

        $table->addColumn('id',            'bigint',  ['autoincrement' => true]);
        $table->addColumn('id_cliente',    'bigint',  ['notnull' => false]);
        $table->addColumn('total_bruto',   'decimal', ['notnull' => false, 'precision' => 18, 'scale' => 4]);
        $table->addColumn('total_liquido', 'decimal', ['notnull' => false, 'precision' => 18, 'scale' => 4]);
        $table->addColumn('desconto',      'decimal', ['notnull' => false, 'precision' => 18, 'scale' => 4]);
        $table->addColumn('acrescimo',     'decimal', ['notnull' => false, 'precision' => 18, 'scale' => 4]);
        $table->addColumn('estado_venda',  'string',  ['length' => 50, 'notnull' => false]);
        $table->addColumn('observacao',    'text',    ['notnull' => false]);
        $table->addColumn('created_at',    'datetime', ['notnull' => false, 'default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('updated_at',    'datetime', ['notnull' => false, 'default' => 'CURRENT_TIMESTAMP']);

        $table->setPrimaryKey(['id']);

        $this->addSql(<<<'SQL'
            ALTER TABLE sale
                ADD CONSTRAINT fk_sale_customer
                    FOREIGN KEY (id_cliente)
                    REFERENCES customer(id)
                    ON DELETE CASCADE ON UPDATE NO ACTION
        SQL);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('sale');
    }
}
