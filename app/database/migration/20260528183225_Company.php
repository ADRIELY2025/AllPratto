<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528183225 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Company';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE company (
                id                 BIGSERIAL     PRIMARY KEY,
                nome_fantasia      VARCHAR(255)  NOT NULL,
                razao_social       VARCHAR(255)  NULL,
                cnpj               VARCHAR(18)   NOT NULL,
                inscricao_estadual VARCHAR(30)   NULL,
                telefone           VARCHAR(20)   NULL,
                email              VARCHAR(255)  NULL,
                endereco           VARCHAR(255)  NULL,
                numero             VARCHAR(20)   NULL,
                bairro             VARCHAR(100)  NULL,
                cidade             VARCHAR(100)  NULL,
                estado             VARCHAR(2)    NULL,
                cep                VARCHAR(10)   NULL,
                ativo              BOOLEAN       NOT NULL DEFAULT TRUE,
                criado_em          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS company');
    }
}
