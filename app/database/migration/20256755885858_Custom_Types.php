<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20256755885858 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Custom Types - ENUMs de movimentação de estoque, venda e compra';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TYPE stock_movement_direction AS ENUM ('ENTRADA', 'SAIDA')");

        $this->addSql("CREATE TYPE stock_movement_origin AS ENUM (
            'VENDA',
            'CANCELAMENTO_VENDA',
            'COMPRA',
            'CANCELAMENTO_COMPRA',
            'AJUSTE_MANUAL',
            'INVENTARIO',
            'TRANSFERENCIA'
        )");

        $this->addSql("CREATE TYPE stock_movement_venda AS ENUM (
            'PRE_VENDA',
            'ORCAMENTO',
            'VENDA'
        )");

        $this->addSql("CREATE TYPE stock_movement_compra AS ENUM (
            'EM_ANDAMENTO',
            'RECEBIDO'
        )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TYPE IF EXISTS stock_movement_direction');
        $this->addSql('DROP TYPE IF EXISTS stock_movement_origin');
        $this->addSql('DROP TYPE IF EXISTS stock_movement_venda');
        $this->addSql('DROP TYPE IF EXISTS stock_movement_compra');
    }
}