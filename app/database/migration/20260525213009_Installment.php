<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525213009 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria tabela installment — parcelas vinculadas a payment_terms';
    }
 
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('installment');
 
        $table->addColumn('id',                          'bigint',  ['autoincrement' => true]);
        $table->addColumn('id_pagamento',                'bigint',  ['notnull' => false]);
        $table->addColumn('parcela',                     'integer', ['notnull' => false]);
        $table->addColumn('intervalo',                   'integer', ['notnull' => false]);
        $table->addColumn('alterar_vencimento_conta',    'integer', ['notnull' => false]);
        $table->addColumn('data_cadastro',               'datetime',['notnull' => false, 'default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('data_atualizacao',            'datetime',['notnull' => false, 'default' => 'CURRENT_TIMESTAMP']);
 
        $table->setPrimaryKey(['id']);
 
        $this->addSql(<<<'SQL'
            ALTER TABLE installment
                ADD CONSTRAINT fk_installment_payment_terms
                    FOREIGN KEY (id_pagamento)
                    REFERENCES payment_terms(id)
                    ON DELETE CASCADE ON UPDATE NO ACTION
        SQL);
    }
 
    public function down(Schema $schema): void
    {
        $schema->dropTable('installment');
    }
}
 