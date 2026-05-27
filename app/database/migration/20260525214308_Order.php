<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525214308 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Order - cabeçalho do pedido com FK para payment_terms';
    }

    public function up(Schema $schema): void
    {
        // ── Cabeçalho do pedido ───────────────────────────────────────────────
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
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('order');
    }
}