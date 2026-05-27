<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526210400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stock Triggers - triggers automáticos de movimentação de estoque';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TRIGGER trg_refresh_estoque_on_movement
            AFTER INSERT OR UPDATE OR DELETE ON public.stock_movement
            FOR EACH STATEMENT
            EXECUTE FUNCTION public.refresh_mvw_estoque()
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TRIGGER trg_refresh_estoque_on_product
            AFTER UPDATE OF excluido, nome OR DELETE ON public.product
            FOR EACH STATEMENT
            EXECUTE FUNCTION public.refresh_mvw_estoque()
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TRIGGER trg_init_product_stock
            AFTER INSERT ON public.product
            FOR EACH ROW
            EXECUTE FUNCTION public.fn_trigger_inicializar_estoque()
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TRIGGER trg_sale_to_stock_movement
            AFTER INSERT ON public.item_sale
            FOR EACH ROW
            EXECUTE FUNCTION public.fn_trigger_sale_to_stock_movement()
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS trg_refresh_estoque_on_movement ON public.stock_movement');
        $this->addSql('DROP TRIGGER IF EXISTS trg_refresh_estoque_on_product  ON public.product');
        $this->addSql('DROP TRIGGER IF EXISTS trg_init_product_stock           ON public.product');
        $this->addSql('DROP TRIGGER IF EXISTS trg_sale_to_stock_movement       ON public.item_sale');
    }
}
