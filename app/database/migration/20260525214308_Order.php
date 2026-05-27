<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525214308 extends AbstractMigration
{
     public function getDescription(): string
    {
        return 'Order - payment_terms, tabela base order_item e view vw_pedido';
    }
 
    public function up(Schema $schema): void
    {
        // ── 2. Cabeçalho do pedido ────────────────────────────────────────────
        $order = $schema->createTable('order');
 
        $order->addColumn('id',               'bigint',   ['autoincrement' => true]);
        $order->addColumn('mesa',             'integer',  ['notnull' => true]);
        $order->addColumn('payment_terms_id', 'bigint',   ['notnull' => false]);
        $order->addColumn('pagamento',        'string',   ['length' => 50, 'notnull' => false]);
        $order->addColumn('total',            'decimal',  ['precision' => 15, 'scale' => 2, 'default' => 0]);
        $order->addColumn('status',           'string',   ['length' => 50, 'default' => 'pendente']);
        $order->addColumn('observacao',       'text',     ['notnull' => false]);
        $order->addColumn('criado_em',        'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $order->addColumn('atualizado_em',    'datetime', ['default' => 'CURRENT_TIMESTAMP']);
 
        $order->setPrimaryKey(['id']);
        $order->addIndex(['mesa']);
        $order->addIndex(['status']);
        $order->addForeignKeyConstraint(
            'payment_terms',
            ['payment_terms_id'],
            ['id'],
            ['onDelete' => 'SET NULL'],
            'fk_order_payment_terms'
        );
 
        // ── 3. Itens do pedido ────────────────────────────────────────────────
        $item = $schema->createTable('order_item');
 
        $item->addColumn('id',         'bigint',  ['autoincrement' => true]);
        $item->addColumn('order_id',   'bigint',  ['notnull' => true]);
        $item->addColumn('product_id', 'bigint',  ['notnull' => false]);
        $item->addColumn('nome',       'string',  ['length' => 255]);
        $item->addColumn('preco',      'decimal', ['precision' => 15, 'scale' => 2]);
        $item->addColumn('quantidade', 'integer', ['default' => 1]);
        $item->addColumn('subtotal',   'decimal', ['precision' => 15, 'scale' => 2]);
 
        $item->setPrimaryKey(['id']);
        $item->addIndex(['order_id']);
        $item->addForeignKeyConstraint(
            'order',
            ['order_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'fk_order_item_order'
        );
        $item->addForeignKeyConstraint(
            'product',
            ['product_id'],
            ['id'],
            ['onDelete' => 'SET NULL'],
            'fk_order_item_product'
        );
    }
 
    public function down(Schema $schema): void
    {
    
    }
}
 