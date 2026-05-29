<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528183217 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE users (
                id            BIGSERIAL    PRIMARY KEY,
                nome          TEXT         NOT NULL DEFAULT '',
                sobrenome     TEXT         NOT NULL DEFAULT '',
                cpf           TEXT         NOT NULL DEFAULT '',
                rg            TEXT         NOT NULL DEFAULT '',
                senha         TEXT         NOT NULL DEFAULT '',
                ativo         BOOLEAN      NOT NULL DEFAULT FALSE,
                administrador BOOLEAN      NOT NULL DEFAULT FALSE,
                criado_em     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT uq_users_cpf UNIQUE (cpf)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_users_nome      ON users (nome)');
        $this->addSql('CREATE INDEX idx_users_sobrenome ON users (sobrenome)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS users');
    }
}
