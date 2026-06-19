<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528183340 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Payment_Terms';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE payment_terms (
                id            BIGSERIAL     PRIMARY KEY,
                codigo        VARCHAR(50)   NULL,
                titulo        VARCHAR(255)  NULL,
                atalho        VARCHAR(50)   NULL,
                criado_em     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL);

        $this->addSql('CREATE INDEX idx_payment_terms_titulo ON payment_terms (titulo)');
        $this->addSql('CREATE INDEX idx_payment_terms_codigo ON payment_terms (codigo)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS payment_terms');
    }
}
