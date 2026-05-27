<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526201358 extends AbstractMigration
{
    
   public function getDescription(): string
    {
        return 'OrderItem - tabela base de itens do pedido com FKs e índices';
    }

    public function up(Schema $schema): void
    {
        //  TABELA: order_item
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
