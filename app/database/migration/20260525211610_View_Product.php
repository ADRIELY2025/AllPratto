<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525211610 extends AbstractMigration
{
     public function getDescription(): string
    {
        return 'Cria view_product — listagem de produtos ativos não excluídos';
    }
 
    public function up(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS view_product');
 
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE VIEW view_product AS
            SELECT
                p.id::TEXT,
                p.nome,
                p.codigo_barra,
                p.grupo,
                p.descricao,
                p.preco_venda AS valor,
                p.ativo,
                TRUE AS produto
            FROM public.product p
            WHERE p.excluido = FALSE
        SQL);
    }
 
    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS view_product');
    }
}