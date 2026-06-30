<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Torna id_mesa nullable na tabela "order" para suportar pedidos virtuais
 * (iFood / telefone) que não possuem mesa física associada.
 *
 * Também atualiza a view vw_kitchen para exibir "Pedido Virtual" quando
 * id_mesa for NULL.
 */
final class Version20260629000001_Virtual_Order extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Torna id_mesa nullable em "order" para suportar pedidos virtuais';
    }

    public function up(Schema $schema): void
    {
        // 1. Remove a constraint NOT NULL de id_mesa
        $this->addSql('ALTER TABLE "order" ALTER COLUMN id_mesa DROP NOT NULL');

        // 2. Remove a FK antiga e recria com NULL permitido + SET NULL no delete
        $this->addSql('ALTER TABLE "order" DROP CONSTRAINT IF EXISTS order_id_mesa_fkey');
        $this->addSql('ALTER TABLE "order" ADD CONSTRAINT order_id_mesa_fkey
            FOREIGN KEY (id_mesa) REFERENCES mesa(id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // Reverte: id_mesa volta a ser NOT NULL
        // (atenção: pedidos virtuais existentes terão id_mesa = NULL e
        //  precisarão ser deletados ou associados a uma mesa antes do rollback)
        $this->addSql('ALTER TABLE "order" DROP CONSTRAINT IF EXISTS order_id_mesa_fkey');
        $this->addSql('ALTER TABLE "order" ALTER COLUMN id_mesa SET NOT NULL');
        $this->addSql('ALTER TABLE "order" ADD CONSTRAINT order_id_mesa_fkey
            FOREIGN KEY (id_mesa) REFERENCES mesa(id) ON DELETE RESTRICT');
    }
}
