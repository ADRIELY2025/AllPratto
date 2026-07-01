<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260630202518 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Order_item_Status';
    }

  public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE order_item
                ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'ativo'
        SQL);
 
        $this->addSql(<<<'SQL'
            ALTER TABLE order_item
                ADD CONSTRAINT chk_order_item_status CHECK (status IN ('ativo','cancelado'))
        SQL);
 
        $this->addSql('CREATE INDEX idx_order_item_status ON order_item (status)');
 
        // -----------------------------------------------------------------------
        // Trigger: ao marcar order_item como 'cancelado', propaga o cancelamento
        // para o registro correspondente em kitchen (sem excluir nada em nenhum
        // dos dois lados).
        // -----------------------------------------------------------------------
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.fn_order_item_cancel_propaga_kitchen()
            RETURNS TRIGGER LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.status = 'cancelado' AND OLD.status IS DISTINCT FROM 'cancelado' THEN
                    UPDATE public.kitchen
                       SET status     = 'Cancelled',
                           updated_at = NOW()
                     WHERE order_item_id = NEW.id
                       AND status NOT IN ('Delivered', 'Cancelled');
                END IF;
                RETURN NEW;
            END; $$
        SQL);
 
        $this->addSql(<<<'SQL'
            CREATE TRIGGER trg_order_item_cancel
            AFTER UPDATE OF status ON public.order_item
            FOR EACH ROW EXECUTE FUNCTION public.fn_order_item_cancel_propaga_kitchen()
        SQL);
    }
 
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS trg_order_item_cancel ON public.order_item');
        $this->addSql('DROP FUNCTION IF EXISTS public.fn_order_item_cancel_propaga_kitchen()');
        $this->addSql('DROP INDEX IF EXISTS idx_order_item_status');
        $this->addSql('ALTER TABLE order_item DROP CONSTRAINT IF EXISTS chk_order_item_status');
        $this->addSql('ALTER TABLE order_item DROP COLUMN IF EXISTS status');
    }
}
 