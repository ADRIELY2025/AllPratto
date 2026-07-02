<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

// Define um valor padrão seguro para `estado_venda` e corrige registros
// existentes que estejam NULL.

final class Version20260617120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix_Sale_Estado_Default';
    }

    public function up(Schema $schema): void
    {
        // Define um valor padrão seguro para estado_venda e preenche
        // eventuais registros antigos que estejam NULL.
        $this->addSql("ALTER TABLE public.sale ALTER COLUMN estado_venda SET DEFAULT 'PRE_VENDA'");
        $this->addSql("UPDATE public.sale SET estado_venda = 'PRE_VENDA' WHERE estado_venda IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE public.sale ALTER COLUMN estado_venda DROP DEFAULT');
    }
}
