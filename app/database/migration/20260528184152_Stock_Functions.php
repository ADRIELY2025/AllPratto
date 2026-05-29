<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528184152 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stock_Functions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.refresh_mvw_estoque()
            RETURNS TRIGGER AS $$
            BEGIN
                REFRESH MATERIALIZED VIEW public.mvw_estoque;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.fn_trigger_inicializar_estoque()
            RETURNS TRIGGER AS $$
            BEGIN
                INSERT INTO public.stock_movement (
                    id_produto,
                    quantidade_entrada,
                    quantidade_saida,
                    observacao
                ) VALUES (
                    NEW.id,
                    0,
                    0,
                    'INICIALIZAÇÃO DE CADASTRO'
                );
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.fn_trigger_sale_to_stock_movement()
            RETURNS TRIGGER
            LANGUAGE plpgsql AS $$
            BEGIN
                INSERT INTO public.stock_movement (
                    id_item_venda,
                    id_produto,
                    quantidade_saida,
                    observacao
                ) VALUES (
                    NEW.id,
                    NEW.id_produto,
                    NEW.quantidade,
                    'VENDA AUTOMÁTICA'
                );
                RETURN NEW;
            END;
            $$
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP FUNCTION IF EXISTS public.refresh_mvw_estoque()');
        $this->addSql('DROP FUNCTION IF EXISTS public.fn_trigger_inicializar_estoque()');
        $this->addSql('DROP FUNCTION IF EXISTS public.fn_trigger_sale_to_stock_movement()');
    }
}
