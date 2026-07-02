<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

// CORRIGIDO: id_cliente era BIGINT sem FK. Agora referencia customer(id).
// estado_venda usa o ENUM venda_estado em vez de VARCHAR solto.

final class Version20260528183259 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sale';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE sale (
                id            BIGSERIAL              PRIMARY KEY,
                id_cliente    BIGINT                 NULL REFERENCES customer(id) ON DELETE SET NULL,
                total_bruto   NUMERIC(18,4)          NULL,
                total_liquido NUMERIC(18,4)          NULL,
                desconto      NUMERIC(18,4)          NULL,
                acrescimo     NUMERIC(18,4)          NULL,
                observacao    TEXT                   NULL,
                estado_venda  venda_estado           NULL, -- Adicionado aqui
                criado_em     TIMESTAMP              NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP              NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL);

        $this->addSql('CREATE INDEX idx_sale_id_cliente ON sale (id_cliente)');
    }


    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS sale');
    }
}
