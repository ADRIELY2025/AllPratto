<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528183350 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Order';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE "order" (
                id               BIGSERIAL      PRIMARY KEY,
                mesa             INTEGER        NOT NULL,
                payment_terms_id BIGINT         NULL REFERENCES payment_terms(id) ON DELETE SET NULL,
                pagamento        VARCHAR(50)    NULL,
                total            NUMERIC(15,2)  NOT NULL DEFAULT 0,
                status           VARCHAR(50)    NOT NULL DEFAULT 'pendente',
                observacao       TEXT           NULL,
                criado_em        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL);

        $this->addSql('CREATE INDEX idx_order_mesa   ON "order" (mesa)');
        $this->addSql('CREATE INDEX idx_order_status ON "order" (status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS "order"');
    }
}
