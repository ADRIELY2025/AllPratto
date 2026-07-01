<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528183159 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Custom_Types';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TYPE venda_estado AS ENUM (
            'PRE_VENDA',
            'ORCAMENTO',
            'VENDA'
        )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TYPE IF EXISTS venda_estado');
    }
}
