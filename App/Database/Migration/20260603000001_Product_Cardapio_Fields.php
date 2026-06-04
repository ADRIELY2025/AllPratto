<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260603000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adiciona campos do cardápio digital na tabela product';
    }

    public function up(Schema $schema): void
    {
        // Categoria do cardápio (Entradas, Principais, Sobremesas, Bebidas…)
        $this->addSql("ALTER TABLE product ADD COLUMN IF NOT EXISTS categoria     VARCHAR(100)  NULL");

        // Emoji exibido como ícone fallback quando não há imagem
        $this->addSql("ALTER TABLE product ADD COLUMN IF NOT EXISTS emoji         VARCHAR(10)   NULL");

        // Tempo estimado de preparo exibido no card (ex: '20 min')
        $this->addSql("ALTER TABLE product ADD COLUMN IF NOT EXISTS tempo_preparo VARCHAR(20)   NULL");

        // Marca o item como destaque no cardápio
        $this->addSql("ALTER TABLE product ADD COLUMN IF NOT EXISTS destaque      BOOLEAN       NOT NULL DEFAULT FALSE");

        // URL externa da imagem (Imgur, Google Drive, ImgBB etc.)
        // NULL = usa emoji como fallback
        $this->addSql("ALTER TABLE product ADD COLUMN IF NOT EXISTS imagem_url    TEXT          NULL");

        // Index para filtrar por categoria com rapidez
        $this->addSql("CREATE INDEX IF NOT EXISTS idx_product_categoria ON product (categoria)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP INDEX IF EXISTS idx_product_categoria");
        $this->addSql("ALTER TABLE product DROP COLUMN IF EXISTS imagem_url");
        $this->addSql("ALTER TABLE product DROP COLUMN IF EXISTS destaque");
        $this->addSql("ALTER TABLE product DROP COLUMN IF EXISTS tempo_preparo");
        $this->addSql("ALTER TABLE product DROP COLUMN IF EXISTS emoji");
        $this->addSql("ALTER TABLE product DROP COLUMN IF EXISTS categoria");
    }
}