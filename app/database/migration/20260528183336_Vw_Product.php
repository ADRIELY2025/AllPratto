<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528183336 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vw_Product';
    }

     public function up(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS view_product');
 
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE VIEW view_product AS
            SELECT
                p.id::TEXT,
                p.descricao AS nome,
                p.codigo_barras,
                p.descricao,
                p.valor_venda AS valor,
                p.ativo,
                TRUE AS produto
            FROM public.product p
            WHERE p.ativo = TRUE
        SQL);
    }
 
    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS view_product');
    }
}