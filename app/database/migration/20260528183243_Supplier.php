<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528183243 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supplier';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE supplier (
                id                 BIGSERIAL     PRIMARY KEY,
                nome_fantasia      VARCHAR(255)  NOT NULL,
                razao_social       VARCHAR(255)  NULL,
                cnpj               VARCHAR(18)   NOT NULL,
                inscricao_estadual VARCHAR(30)   NULL,
                telefone           VARCHAR(20)   NULL,
                email              VARCHAR(255)  NULL,
                ativo              BOOLEAN       NOT NULL DEFAULT TRUE,
                criado_em          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS supplier');
    }
}
