<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528183340 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sale';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE sale (
                id            BIGSERIAL       PRIMARY KEY,
                id_cliente    BIGINT          NULL,
                total_bruto   NUMERIC(18,4)   NULL,
                total_liquido NUMERIC(18,4)   NULL,
                desconto      NUMERIC(18,4)   NULL,
                acrescimo     NUMERIC(18,4)   NULL,
                estado_venda  VARCHAR(50)     NULL,
                observacao    TEXT            NULL,
                criado_em     TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS sale');
    }
}
