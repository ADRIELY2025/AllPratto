<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Installment - parcelas da condição de pagamento';
    }
 
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('installment');
 
        $table->addColumn('id',                        'bigint',   ['autoincrement' => true]);
        $table->addColumn('id_pagamento',              'bigint',   ['notnull' => true]);
        $table->addColumn('parcela',                   'integer',  ['notnull' => false]);
        $table->addColumn('intervalo',                 'integer',  ['notnull' => false]);
        $table->addColumn('alterar_vencimento_conta',  'integer',  ['notnull' => false, 'default' => 0]);
        $table->addColumn('criado_em',                 'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('atualizado_em',             'datetime', ['default' => 'CURRENT_TIMESTAMP']);
 
        $table->setPrimaryKey(['id']);
        $table->addIndex(['id_pagamento']);
        $table->addForeignKeyConstraint(
            'payment_terms',
            ['id_pagamento'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'fk_installment_payment_terms'
        );
    }
 
    public function down(Schema $schema): void
    {
        $schema->dropTable('installment');
    }
}