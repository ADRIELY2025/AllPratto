<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525205256 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pedidos - payment_terms, tabela base pedido_item e view vw_pedido';
    }

    public function up(Schema $schema): void
    {
        // ────────────────────────────────────────────────────────
        //  TABELA: payment_terms
        //  Formas de pagamento disponíveis no sistema.
        //  Ex: Pix, Crédito, Débito, Dinheiro, Vale-refeição
        // ────────────────────────────────────────────────────────
        $pt = $schema->createTable('payment_terms');

        $pt->addColumn('id',        'bigint',   ['autoincrement' => true]);
        $pt->addColumn('descricao', 'string',   ['length' => 100]);
        $pt->addColumn('ativo',     'boolean',  ['default' => true]);
        $pt->addColumn('criado_em', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);

        $pt->setPrimaryKey(['id']);
        $pt->addIndex(['descricao']);

        // ────────────────────────────────────────────────────────
        //  TABELA BASE: pedido_item
        //
        //  É a única tabela de escrita do fluxo de pedidos.
        //  Cada linha = 1 produto dentro de 1 compra.
        //  id_compra agrupa os itens de um mesmo pedido de mesa.
        //
        //  Daqui saem:
        //    → vw_pedido  (leitura enriquecida — esta migration)
        //    → cozinha    (tabela operacional — próxima migration,
        //                  populada por trigger automático)
        // ────────────────────────────────────────────────────────
        $pi = $schema->createTable('pedido_item');

        $pi->addColumn('id',               'bigint',  ['autoincrement' => true]);
        $pi->addColumn('id_compra',        'bigint');           // agrupa itens do mesmo pedido
        $pi->addColumn('id_cliente',       'bigint',  ['notnull' => false]); // FK → customer (balcão = null)
        $pi->addColumn('id_produto',       'bigint');           // FK → cardapio_item
        $pi->addColumn('id_payment_terms', 'bigint');           // FK → payment_terms
        $pi->addColumn('id_mesa',          'integer');          // número da mesa
        $pi->addColumn('quantidade',       'integer',  ['default' => 1]);
        $pi->addColumn('preco_unitario',   'decimal',  ['precision' => 15, 'scale' => 2]);
        $pi->addColumn('subtotal',         'decimal',  ['precision' => 15, 'scale' => 2]);
        $pi->addColumn('observacao',       'text',     ['notnull' => false]);
        $pi->addColumn('criado_em',        'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $pi->addColumn('atualizado_em',    'datetime', ['default' => 'CURRENT_TIMESTAMP']);

        $pi->setPrimaryKey(['id']);
        $pi->addIndex(['id_compra']);
        $pi->addIndex(['id_cliente']);
        $pi->addIndex(['id_produto']);
        $pi->addIndex(['id_payment_terms']);
        $pi->addIndex(['id_mesa']);
        $pi->addIndex(['criado_em']);

        // Foreign keys via addSql
        $this->addSql(<<<'SQL'
            ALTER TABLE pedido_item
                ADD CONSTRAINT fk_pedido_item_cliente
                    FOREIGN KEY (id_cliente)
                    REFERENCES customer(id)
                    ON DELETE SET NULL,

                ADD CONSTRAINT fk_pedido_item_produto
                    FOREIGN KEY (id_produto)
                    REFERENCES cardapio_item(id)
                    ON DELETE RESTRICT,

                ADD CONSTRAINT fk_pedido_item_payment
                    FOREIGN KEY (id_payment_terms)
                    REFERENCES payment_terms(id)
                    ON DELETE RESTRICT
        SQL);

        // ────────────────────────────────────────────────────────
        //  VIEW: vw_pedido
        //
        //  Leitura desnormalizada da tabela base pedido_item.
        //  Resolve todos os IDs para nomes legíveis e calcula
        //  o total da compra (WINDOW FUNCTION sobre id_compra).
        //
        //  Usada pelo painel admin e pelo histórico do cliente.
        //  NÃO é usada pela cozinha (que lê da tabela cozinha).
        // ────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE VIEW public.vw_pedido AS
            SELECT
                -- chaves
                pi.id                                               AS id_pedido_item,
                pi.id_compra,
                pi.id_mesa,

                -- cliente
                pi.id_cliente,
                COALESCE(c.nome_fantasia, 'Balcão')                AS nome_cliente,
                c.cpf_cnpj,

                -- produto
                pi.id_produto,
                ci.descricao                                        AS nome_produto,
                ci.categoria                                        AS categoria_produto,
                ci.emoji                                            AS emoji_produto,
                ci.tempo_preparo,

                -- valores
                pi.quantidade,
                pi.preco_unitario,
                pi.subtotal,
                SUM(pi.subtotal) OVER (PARTITION BY pi.id_compra)  AS total_compra,

                -- pagamento
                pi.id_payment_terms,
                pt.descricao                                        AS forma_pagamento,

                -- extras
                pi.observacao,
                pi.criado_em,
                pi.atualizado_em

            FROM       public.pedido_item  pi
            LEFT JOIN  public.customer     c   ON c.id  = pi.id_cliente
            JOIN       public.cardapio_item ci  ON ci.id = pi.id_produto
            JOIN       public.payment_terms pt  ON pt.id = pi.id_payment_terms
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW  IF EXISTS public.vw_pedido');
        $this->addSql('DROP TABLE IF EXISTS public.pedido_item');
        $this->addSql('DROP TABLE IF EXISTS public.payment_terms');
    }

}