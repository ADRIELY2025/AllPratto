<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528183236 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Product';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE product (
                id            BIGSERIAL        PRIMARY KEY,
                nome            VARCHAR(255)    NOT NULL,
                codigo_barra  VARCHAR(50)      NULL,
                grupo         text      NULL,
                unidade       text          NOT NULL DEFAULT 0,
                imagem_url    TEXT             NULL,
                nome_imagem    TEXT             NULL,
                preco_compra   NUMERIC(18,4)    NOT NULL DEFAULT 0,
                total_imposto   NUMERIC(18,4)    NOT NULL DEFAULT 0,
                margem_lucro   NUMERIC(18,4)    NOT NULL DEFAULT 0,
                custo_operacional   NUMERIC(18,4)    NOT NULL DEFAULT 0,
                valor_venda_sugerido   NUMERIC(18,4)    NOT NULL DEFAULT 0,
                preco_venda   NUMERIC(18,4)    NOT NULL DEFAULT 0,
                tempo_preparo VARCHAR(30)      NULL,
                descricao     VARCHAR(255)     NOT NULL,
                ativo         BOOLEAN          NOT NULL DEFAULT TRUE,
                excluido         BOOLEAN          NOT NULL DEFAULT FALSE,
                criado_em     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS product');
    }
}