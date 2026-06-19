<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611194437 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Installment_sale_purchase';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE installment_sale_purchase (
                id                       BIGSERIAL  PRIMARY KEY,
                id_payment               BIGINT     NOT NULL REFERENCES payment_terms(id) ON DELETE CASCADE,
                id_sale                  BIGINT     NOT NULL REFERENCES sale(id) ON DELETE CASCADE,
                id_purchase              BIGINT     NOT NULL REFERENCES purchase(id) ON DELETE CASCADE,
                id_installment           BIGINT     NOT NULL REFERENCES installment(id) ON DELETE CASCADE,
                total_parcelas           INTEGER    NOT NULL,
                numero_parcela           INTEGER    NOT NULL,
                valor_parcela            INTEGER    NOT NULL,
                valor_total              INTEGER    NOT NULL,
                status                   VARCHAR(20) NOT NULL DEFAULT 'aberto' CHECK (status IN ('aberto', 'pago', 'cancelado')),
                data_vencimento          INTEGER    NULL DEFAULT 0,
                criado_em                TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em            TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS installment_sale_purchase');
    }
}