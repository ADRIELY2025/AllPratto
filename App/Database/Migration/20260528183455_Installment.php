<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528183455 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Installment';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE installment (
                id                       BIGSERIAL  PRIMARY KEY,
                id_pagamento             BIGINT     NOT NULL REFERENCES payment_terms(id) ON DELETE CASCADE,
                parcela                  INTEGER    NULL,
                intervalo                INTEGER    NULL,
                alterar_vencimento_conta INTEGER    NULL DEFAULT 0,
                criado_em                TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em            TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL);

        $this->addSql('CREATE INDEX idx_installment_id_pagamento ON installment (id_pagamento)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS installment');
    }
}
