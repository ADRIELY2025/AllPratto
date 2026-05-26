<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525211537 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria ENUMs customizados: stock_movement_direction, stock_movement_origin, stock_movement_venda, stock_movement_compra';
    }
 
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TYPE stock_movement_direction AS ENUM ('ENTRADA', 'SAIDA')
        SQL);
 
        $this->addSql(<<<'SQL'
            CREATE TYPE stock_movement_origin AS ENUM (
                'VENDA',
                'CANCELAMENTO_VENDA',
                'COMPRA',
                'CANCELAMENTO_COMPRA',
                'AJUSTE_MANUAL',
                'INVENTARIO',
                'TRANSFERENCIA'
            )
        SQL);
 
        $this->addSql(<<<'SQL'
            CREATE TYPE stock_movement_venda AS ENUM (
                'PRE_VENDA',
                'ORCAMENTO',
                'VENDA'
            )
        SQL);
 
        $this->addSql(<<<'SQL'
            CREATE TYPE stock_movement_compra AS ENUM (
                'EM_ANDAMENTO',
                'RECEBIDO'
            )
        SQL);
    }
 
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TYPE IF EXISTS stock_movement_direction');
        $this->addSql('DROP TYPE IF EXISTS stock_movement_origin');
        $this->addSql('DROP TYPE IF EXISTS stock_movement_venda');
        $this->addSql('DROP TYPE IF EXISTS stock_movement_compra');
    }
}