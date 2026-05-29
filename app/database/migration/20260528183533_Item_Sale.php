<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528183533 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Item_Sale';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE item_sale (
                id               BIGSERIAL      PRIMARY KEY,
                id_venda         BIGINT         NULL REFERENCES sale(id)    ON DELETE CASCADE,
                id_produto       BIGINT         NULL REFERENCES product(id) ON DELETE CASCADE,
                descricao        TEXT           NULL,
                quantidade       NUMERIC(18,4)  NULL,
                total_bruto      NUMERIC(18,4)  NULL,
                unitario_bruto   NUMERIC(18,4)  NULL,
                total_liquido    NUMERIC(18,4)  NULL,
                unitario_liquido NUMERIC(18,4)  NULL,
                desconto         NUMERIC(18,4)  NULL,
                acrescimo        NUMERIC(18,4)  NULL,
                nome             TEXT           NULL,
                criado_em        TIMESTAMP      NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em    TIMESTAMP      NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS item_sale');
    }
}
