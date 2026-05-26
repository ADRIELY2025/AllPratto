<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525212230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria view vw_item_sale — totais líquido e bruto agrupados por venda';
    }
 
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE VIEW vw_item_sale AS
            SELECT
                item_sale.id_venda,
                COALESCE(SUM(total_liquido), 0) AS total_liquido,
                COALESCE(SUM(total_bruto),   0) AS total_bruto
            FROM item_sale
            GROUP BY item_sale.id_venda
        SQL);
    }
 
    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS vw_item_sale');
    }
}