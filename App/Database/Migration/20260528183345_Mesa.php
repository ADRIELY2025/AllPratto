<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528183345 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mesa';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE mesa (
                id            BIGSERIAL     PRIMARY KEY,
                numero        INTEGER       NOT NULL,
                capacidade    INTEGER       NULL,
                status        VARCHAR(30)   NOT NULL DEFAULT 'livre',
                observacao    TEXT          NULL,
                ativo         BOOLEAN       NOT NULL DEFAULT TRUE,
                criado_em     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT uq_mesa_numero UNIQUE (numero),
                CONSTRAINT chk_mesa_status CHECK (status IN ('livre', 'ocupada', 'reservada', 'inativa'))
            )
        SQL);

        $this->addSql('CREATE INDEX idx_mesa_status ON mesa (status)');
        $this->addSql('CREATE INDEX idx_mesa_numero ON mesa (numero)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS mesa');
    }
}
