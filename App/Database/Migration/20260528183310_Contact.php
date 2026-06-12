<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528183310 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Contact';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE contact (
                id            BIGSERIAL   PRIMARY KEY,
                id_fornecedor BIGINT      NULL REFERENCES supplier(id) ON DELETE CASCADE,
                id_usuario    BIGINT      NULL REFERENCES users(id) ON DELETE CASCADE,
                id_empresa    BIGINT      NULL REFERENCES company(id) ON DELETE CASCADE,
                id_cliente    BIGINT      NULL REFERENCES customer(id) ON DELETE CASCADE,
                tipo_contato          VARCHAR(20) NULL,
                contato       TEXT        NULL,
                email         TEXT        NULL,
                criado_em     TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT uq_contact_contato UNIQUE (contato)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_contact_tipo_contato ON contact (tipo_contato)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS contact');
    }
}
