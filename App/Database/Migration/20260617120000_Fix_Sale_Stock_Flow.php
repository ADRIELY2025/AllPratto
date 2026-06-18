<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/*
 * CORREÇÃO DE ARQUITETURA — fluxo de Venda x Estoque
 * ─────────────────────────────────────────────────────────────────────────
 * Problema observado:
 *   Ao inserir um item em uma venda (INSERT INTO item_sale), o trigger
 *   trg_sale_to_stock_movement disparava IMEDIATAMENTE um INSERT em
 *   stock_movement, mesmo com a venda ainda em rascunho (PRE_VENDA).
 *   Isso fazia o estoque ser baixado item a item, durante a digitação do
 *   carrinho, e qualquer falha nessa cadeia (ex.: REFRESH MATERIALIZED VIEW
 *   public.mvw_estoque falhando) impedia o item de ser salvo — é exatamente
 *   o erro relatado ("relation public.mvw_estoque does not exist").
 *
 * Correção:
 *   1. O trigger deixa de existir em item_sale (inserir/remover item nunca
 *      mais toca em estoque).
 *   2. Um novo trigger passa a existir em sale, disparando SÓ quando
 *      estado_venda muda PARA 'VENDA' (ou seja: quando o usuário conclui a
 *      venda no modal de pagamento). Nesse momento, todos os itens já
 *      lançados na venda geram seus respectivos registros de saída em
 *      stock_movement, de uma vez só.
 *   3. mvw_estoque é recriada de forma defensiva (IF NOT EXISTS), garantindo
 *      que ela exista independente do estado em que o banco já estava.
 *   4. sale.estado_venda passa a ter DEFAULT 'PRE_VENDA' — se a aplicação
 *      um dia deixar de enviar esse campo, o banco já assume o valor certo.
 */
final class Version20260617120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix_Sale_Stock_Flow';
    }

    public function up(Schema $schema): void
    {
        // 1) Remove o trigger/função antigos (disparavam no INSERT de item_sale)
        $this->addSql('DROP TRIGGER IF EXISTS trg_sale_to_stock_movement ON public.item_sale');
        $this->addSql('DROP FUNCTION IF EXISTS public.fn_trigger_sale_to_stock_movement()');

        // 2) Garante que a materialized view exista, independente do histórico
        //    de migrations já executado nesse banco.
        $this->addSql(<<<'SQL'
            CREATE MATERIALIZED VIEW IF NOT EXISTS public.mvw_estoque AS
            SELECT
                sm.id_produto,
                p.descricao AS nome,
                SUM(COALESCE(sm.quantidade_entrada, 0))                                          AS total_entradas,
                SUM(COALESCE(sm.quantidade_saida,   0))                                          AS total_saidas,
                SUM(COALESCE(sm.quantidade_entrada, 0)) - SUM(COALESCE(sm.quantidade_saida, 0))  AS estoque_atual,
                MAX(sm.data_cadastro)                                                             AS ultima_movimentacao
            FROM public.stock_movement sm
            LEFT JOIN public.product p ON p.id = sm.id_produto
            GROUP BY sm.id_produto, p.descricao
        SQL);

        $this->addSql('CREATE INDEX IF NOT EXISTS product_id_hash           ON public.product        USING HASH (id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS product_nome_hash         ON public.product        USING HASH (descricao)');
        $this->addSql('CREATE INDEX IF NOT EXISTS stock_movement_idprd_hash ON public.stock_movement  USING HASH (id_produto)');

        // 3) Define um valor padrão seguro para estado_venda e preenche
        //    eventuais registros antigos que estejam NULL.
        $this->addSql("ALTER TABLE public.sale ALTER COLUMN estado_venda SET DEFAULT 'PRE_VENDA'");
        $this->addSql("UPDATE public.sale SET estado_venda = 'PRE_VENDA' WHERE estado_venda IS NULL");

        // 4) Nova função: ao finalizar a venda, gera a saída de estoque de
        //    TODOS os itens já lançados, em um único disparo.
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.fn_trigger_sale_to_stock_movement()
            RETURNS TRIGGER
            LANGUAGE plpgsql AS $$
            BEGIN
                INSERT INTO public.stock_movement (
                    id_item_venda,
                    id_produto,
                    quantidade_saida,
                    tipo,
                    origem_movimento,
                    observacao
                )
                SELECT
                    i.id,
                    i.id_produto,
                    i.quantidade,
                    'SAIDA',
                    'VENDA',
                    'VENDA FINALIZADA #' || NEW.id
                FROM public.item_sale i
                WHERE i.id_venda = NEW.id
                  AND i.id_produto IS NOT NULL;

                RETURN NEW;
            END;
            $$
        SQL);

        // 5) Novo trigger: dispara em sale, apenas na transição PARA 'VENDA'.
        $this->addSql(<<<'SQL'
            CREATE TRIGGER trg_sale_to_stock_movement
            AFTER UPDATE OF estado_venda ON public.sale
            FOR EACH ROW
            WHEN (NEW.estado_venda = 'VENDA' AND (OLD.estado_venda IS DISTINCT FROM 'VENDA'))
            EXECUTE FUNCTION public.fn_trigger_sale_to_stock_movement()
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Remove o que esta migration criou em "sale"...
        $this->addSql('DROP TRIGGER IF EXISTS trg_sale_to_stock_movement ON public.sale');
        $this->addSql('DROP FUNCTION IF EXISTS public.fn_trigger_sale_to_stock_movement()');
        $this->addSql('ALTER TABLE public.sale ALTER COLUMN estado_venda DROP DEFAULT');

        // ...e restaura o comportamento original em "item_sale" (mesma definição
        // da migration 20260528184152/20260528184205), para manter a reversão fiel.
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

        $this->addSql(<<<'SQL'
            CREATE TRIGGER trg_sale_to_stock_movement
            AFTER INSERT ON public.item_sale
            FOR EACH ROW
            EXECUTE FUNCTION public.fn_trigger_sale_to_stock_movement()
        SQL);
    }
}
