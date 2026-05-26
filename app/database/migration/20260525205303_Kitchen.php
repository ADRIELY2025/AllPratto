<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525205303 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Kitchen - tabela operacional, trigger automático de order_item e índices';
    }

    public function up(Schema $schema): void
    {
        // ────────────────────────────────────────────────────────
        //  TABELA: kitchen
        //
        //  Tabela operacional de leitura/escrita da kitchen.
        //  Populada AUTOMATICAMENTE via trigger quando um novo
        //  registro é inserido em order_item.
        //
        //  A kitchen          NÃO precisa fazer joins — tudo já chegou
        //  desnormalizado. O status é atualizado diretamente
        //  aqui pela equipe da cozinha.
        //
        //  Fluxo:
        //    INSERT order _item → TRIGGER → INSERT kitchen
        //    Cozinha atualiza status: Aguardando → Preparando → Pronto
        // ────────────────────────────────────────────────────────
        $cozinha = $schema->createTable('cozinha');

        $cozinha->addColumn('id',              'bigint',  ['autoincrement' => true]);
        $cozinha->addColumn('id_order_item',  'bigint');          // FK → order_item (origem)
        $cozinha->addColumn('id_compra',       'bigint');          // agrupa itens do mesmo pedido
        $cozinha->addColumn('id_mesa',         'integer');
        $cozinha->addColumn('id_produto',      'bigint');
        $cozinha->addColumn('nome_produto',    'string',  ['length' => 255]);
        $cozinha->addColumn('categoria',       'string',  ['length' => 100]);
        $cozinha->addColumn('emoji',           'string',  ['length' => 10,  'notnull' => false]);
        $cozinha->addColumn('quantidade',      'integer');
        $cozinha->addColumn('observacao',      'text',    ['notnull' => false]);
        $cozinha->addColumn('nome_cliente',    'string',  ['length' => 255, 'default' => 'Balcão']);
        $cozinha->addColumn('forma_pagamento', 'string',  ['length' => 100]);
        $cozinha->addColumn('status',          'string',  ['length' => 30,  'default' => 'Aguardando']);
        $cozinha->addColumn('recebido_em',     'datetime',['default' => 'CURRENT_TIMESTAMP']); // quando chegou
        $cozinha->addColumn('atualizado_em',   'datetime',['default' => 'CURRENT_TIMESTAMP']);

        $cozinha->setPrimaryKey(['id']);

        // FK para rastrear a origem do item
        $this->addSql(<<<'SQL'
            ALTER TABLE cozinha
                ADD CONSTRAINT fk_cozinha_order_item
                    FOREIGN KEY (id_order_item)
                    REFERENCES order_item(id)
                    ON DELETE CASCADE
        SQL);

        // ────────────────────────────────────────────────────────
        //  FUNÇÃO DO TRIGGER: fn_order_item_para_cozinha()
        //
        //  Executada automaticamente APÓS cada INSERT em order_item.
        //  Resolve os nomes (produto, cliente, pagamento) e insere
        //  um registro na tabela cozinha.
        //
        //  Usa os mesmos joins da vw_order_item para garantir consistência.
        // ────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.fn_order_item_para_cozinha()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_nome_produto    TEXT;
                v_categoria       TEXT;
                v_emoji           TEXT;
                v_nome_cliente    TEXT;
                v_forma_pagamento TEXT;
            BEGIN
                -- Resolve nome do produto
                SELECT descricao, categoria, emoji
                INTO   v_nome_produto, v_categoria, v_emoji
                FROM   public.cardapio_item
                WHERE  id = NEW.id_produto;

                -- Resolve nome do cliente (balcão se nulo)
                SELECT COALESCE(nome_fantasia, 'Balcão')
                INTO   v_nome_cliente
                FROM   public.customer
                WHERE  id = NEW.id_cliente;

                IF v_nome_cliente IS NULL THEN
                    v_nome_cliente := 'Balcão';
                END IF;

                -- Resolve forma de pagamento
                SELECT descricao
                INTO   v_forma_pagamento
                FROM   public.payment_terms
                WHERE  id = NEW.id_payment_terms;

                -- Insere na cozinha
                INSERT INTO public.cozinha (
                    id_order_item,
                    id_compra,
                    id_mesa,
                    id_produto,
                    nome_produto,
                    categoria,
                    emoji,
                    quantidade,
                    observacao,
                    nome_cliente,
                    forma_pagamento,
                    status,
                    recebido_em,
                    atualizado_em
                ) VALUES (
                    NEW.id,
                    NEW.id_compra,
                    NEW.id_mesa,
                    NEW.id_produto,
                    v_nome_produto,
                    v_categoria,
                    v_emoji,
                    NEW.quantidade,
                    NEW.observacao,
                    v_nome_cliente,
                    v_forma_pagamento,
                    'Aguardando',
                    NOW(),
                    NOW()
                );

                RETURN NEW;
            END;
            $$
        SQL);

        // ── Bind do trigger na tabela pedido_item ────────────────
        $this->addSql(<<<'SQL'
            CREATE TRIGGER trg_order_item_para_cozinha
            AFTER INSERT ON public.order_item
            FOR EACH ROW
            EXECUTE FUNCTION public.fn_order_item_para_cozinha()
        SQL);

        // ────────────────────────────────────────────────────────
        //  ÍNDICES NA TABELA cozinha
        //
        //  Otimizados para as 3 consultas mais frequentes:
        //    1. Painel da cozinha — itens na fila por status
        //    2. Busca por mesa   — "o que está pendente na mesa X"
        //    3. Busca por compra — "todos os itens do pedido Y"
        // ────────────────────────────────────────────────────────

        // Fila ativa da cozinha (Aguardando + Preparando), ordenada por chegada
        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_cozinha_fila_ativa
                ON public.cozinha (recebido_em ASC)
                WHERE status IN ('Aguardando', 'Preparando')
        SQL);

        // Busca por mesa + status
        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_cozinha_mesa_status
                ON public.cozinha (id_mesa, status)
        SQL);

        // Busca por compra (para marcar todos os itens de um pedido como prontos)
        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_cozinha_compra
                ON public.cozinha (id_compra, status)
        SQL);

        // Status simples (para contagem de pendentes no dashboard)
        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_cozinha_status
                ON public.cozinha (status)
        SQL);

        // Rastreabilidade — link com order_item original
        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_cozinha_order_item
                ON public.cozinha (id_order_item)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER  IF EXISTS trg_order_item_para_cozinha ON public.order_item');
        $this->addSql('DROP FUNCTION IF EXISTS public.fn_order_item_para_cozinha()');

        $this->addSql('DROP INDEX IF EXISTS public.idx_cozinha_fila_ativa');
        $this->addSql('DROP INDEX IF EXISTS public.idx_cozinha_mesa_status');
        $this->addSql('DROP INDEX IF EXISTS public.idx_cozinha_compra');
        $this->addSql('DROP INDEX IF EXISTS public.idx_cozinha_status');
        $this->addSql('DROP INDEX IF EXISTS public.idx_cozinha_order_item');

        $this->addSql('DROP TABLE IF EXISTS public.cozinha');
    }
}
