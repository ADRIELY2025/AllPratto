<?php
declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260609000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adiciona colunas do cardápio na tabela product';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE product ADD COLUMN IF NOT EXISTS categoria    VARCHAR(100) NULL");
        $this->addSql("ALTER TABLE product ADD COLUMN IF NOT EXISTS emoji        VARCHAR(10)  NULL");
        $this->addSql("ALTER TABLE product ADD COLUMN IF NOT EXISTS tempo_preparo VARCHAR(30) NULL");
        $this->addSql("ALTER TABLE product ADD COLUMN IF NOT EXISTS destaque     BOOLEAN NOT NULL DEFAULT FALSE");
        $this->addSql("ALTER TABLE product ADD COLUMN IF NOT EXISTS imagem_url   TEXT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE product DROP COLUMN IF EXISTS categoria");
        $this->addSql("ALTER TABLE product DROP COLUMN IF EXISTS emoji");
        $this->addSql("ALTER TABLE product DROP COLUMN IF EXISTS tempo_preparo");
        $this->addSql("ALTER TABLE product DROP COLUMN IF EXISTS destaque");
        $this->addSql("ALTER TABLE product DROP COLUMN IF EXISTS imagem_url");
    }
}