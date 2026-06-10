<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

// Sem alteração de lógica — já estava correto com id_mesa e id_cliente.
// Mantido aqui para garantir consistência do pacote.

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
                id_mesa          BIGINT         NOT NULL REFERENCES mesa(id)          ON DELETE RESTRICT,
                id_cliente       BIGINT         NULL     REFERENCES customer(id)      ON DELETE SET NULL,
                payment_terms_id BIGINT         NULL     REFERENCES payment_terms(id) ON DELETE SET NULL,
                total            NUMERIC(15,2)  NOT NULL DEFAULT 0,
                status           VARCHAR(50)    NOT NULL DEFAULT 'pendente',
                observacao       TEXT           NULL,
                criado_em        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT chk_order_status CHECK (
                    status IN ('pendente','em_preparo','pronto','entregue','cancelado','pago')
                )
            )
        SQL);

        $this->addSql('CREATE INDEX idx_order_id_mesa    ON "order" (id_mesa)');
        $this->addSql('CREATE INDEX idx_order_id_cliente ON "order" (id_cliente)');
        $this->addSql('CREATE INDEX idx_order_status     ON "order" (status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS "order"');
    }
}
