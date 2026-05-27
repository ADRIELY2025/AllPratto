<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526204723 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'VW_kitchen - view operacional da cozinha';
    }
 
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE VIEW public.vw_kitchen AS
            SELECT
                k.id,
                k.order_id,
                k.mesa,
                k.product_id,
                k.product_name,
                k.quantidade,
                k.observacao,
                k.payment_method,
                k.status,
                k.received_at,
                k.updated_at
            FROM public.kitchen k
            ORDER BY k.received_at ASC
        SQL);
    }
 
    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS public.vw_kitchen');
    }
}