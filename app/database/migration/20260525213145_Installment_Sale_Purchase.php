<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525213145 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria tabela installment_sale_purchase — parcelas vinculadas a venda, compra e condição de pagamento';
    }
 
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('installment_sale_purchase');
 
        $table->addColumn('id',               'bigint',  ['autoincrement' => true]);
        $table->addColumn('id_sale',          'bigint',  ['notnull' => false]);
        $table->addColumn('id_purchase',      'bigint',  ['notnull' => true]);
        $table->addColumn('id_installment',   'bigint',  ['notnull' => false]);
        $table->addColumn('id_payment_terms', 'bigint',  ['notnull' => true]);
        $table->addColumn('total_parcelas',   'integer', ['notnull' => true]);
        $table->addColumn('numero_parcela',   'integer', ['notnull' => true]);
        $table->addColumn('valor_parcela',    'decimal', ['notnull' => true,  'precision' => 18, 'scale' => 4]);
        $table->addColumn('valor_total',      'decimal', ['notnull' => true,  'precision' => 18, 'scale' => 4]);
        $table->addColumn('data_vencimento',  'date',    ['notnull' => false, 'default' => 'CURRENT_DATE']);
        $table->addColumn('data_cadastro',    'datetime',['notnull' => false, 'default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('data_atualizacao', 'datetime',['notnull' => false, 'default' => 'CURRENT_TIMESTAMP']);
 
        $table->setPrimaryKey(['id']);
 
        // ENUM nativo do banco para status
        $this->addSql(<<<'SQL'
            ALTER TABLE installment_sale_purchase
                ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'aberto'
                    CHECK (status IN ('aberto', 'pago', 'cancelado'))
        SQL);
 
        $this->addSql(<<<'SQL'
            ALTER TABLE installment_sale_purchase
                ADD CONSTRAINT fk_isp_sale
                    FOREIGN KEY (id_sale)
                    REFERENCES sale(id)
                    ON DELETE CASCADE
        SQL);
 
        $this->addSql(<<<'SQL'
            ALTER TABLE installment_sale_purchase
                ADD CONSTRAINT fk_isp_purchase
                    FOREIGN KEY (id_purchase)
                    REFERENCES purchase(id)
                    ON DELETE CASCADE
        SQL);
 
        $this->addSql(<<<'SQL'
            ALTER TABLE installment_sale_purchase
                ADD CONSTRAINT fk_isp_installment
                    FOREIGN KEY (id_installment)
                    REFERENCES installment(id)
                    ON DELETE CASCADE
        SQL);
 
        $this->addSql(<<<'SQL'
            ALTER TABLE installment_sale_purchase
                ADD CONSTRAINT fk_isp_payment_terms
                    FOREIGN KEY (id_payment_terms)
                    REFERENCES payment_terms(id)
                    ON DELETE RESTRICT
        SQL);
    }
 
    public function down(Schema $schema): void
    {
        $schema->dropTable('installment_sale_purchase');
    }
}