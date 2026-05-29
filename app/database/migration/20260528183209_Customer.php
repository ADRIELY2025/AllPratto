<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528183209 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Customer';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE customer (
                id               BIGSERIAL       PRIMARY KEY,
                nome_fantasia    VARCHAR(255)    NOT NULL,
                nome_mesa        VARCHAR(255)    NOT NULL,
                sobrenome_razao  VARCHAR(255)    NULL,
                cpf_cnpj         VARCHAR(18)     NOT NULL,
                inscricao_estadual VARCHAR(30)   NULL,
                nascimento_fundacao DATE          NULL,
                ativo            BOOLEAN         NOT NULL DEFAULT TRUE,
                criado_em        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT uq_customer_cpf_cnpj UNIQUE (cpf_cnpj)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_customer_nome_fantasia ON customer (nome_fantasia)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS customer');
    }
}
