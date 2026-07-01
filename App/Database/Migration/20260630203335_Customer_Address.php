<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260630203335 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Customer_Address';
    }

   public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE customer_address (
                id            BIGSERIAL     PRIMARY KEY,
                id_cliente    BIGINT        NOT NULL REFERENCES customer(id) ON DELETE CASCADE,
                logradouro    VARCHAR(255)  NOT NULL,
                numero        VARCHAR(20)   NULL,
                complemento   VARCHAR(100)  NULL,
                bairro        VARCHAR(100)  NULL,
                cidade        VARCHAR(100)  NULL,
                cep           VARCHAR(10)   NULL,
                referencia    VARCHAR(255)  NULL,
                principal     BOOLEAN       NOT NULL DEFAULT TRUE,
                criado_em     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL);
 
        $this->addSql('CREATE INDEX idx_customer_address_cliente ON customer_address (id_cliente)');
 
        $this->addSql(<<<'SQL'
            ALTER TABLE "order"
                ADD COLUMN id_endereco         BIGINT       NULL REFERENCES customer_address(id) ON DELETE SET NULL,
                ADD COLUMN endereco_logradouro VARCHAR(255) NULL,
                ADD COLUMN endereco_numero     VARCHAR(20)  NULL,
                ADD COLUMN endereco_complemento VARCHAR(100) NULL,
                ADD COLUMN endereco_bairro     VARCHAR(100) NULL,
                ADD COLUMN endereco_cidade     VARCHAR(100) NULL,
                ADD COLUMN endereco_cep        VARCHAR(10)  NULL,
                ADD COLUMN endereco_referencia VARCHAR(255) NULL
        SQL);
 
        $this->addSql('CREATE INDEX idx_order_id_endereco ON "order" (id_endereco)');
    }
 
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "order" DROP COLUMN IF EXISTS id_endereco');
        $this->addSql('ALTER TABLE "order" DROP COLUMN IF EXISTS endereco_logradouro');
        $this->addSql('ALTER TABLE "order" DROP COLUMN IF EXISTS endereco_numero');
        $this->addSql('ALTER TABLE "order" DROP COLUMN IF EXISTS endereco_complemento');
        $this->addSql('ALTER TABLE "order" DROP COLUMN IF EXISTS endereco_bairro');
        $this->addSql('ALTER TABLE "order" DROP COLUMN IF EXISTS endereco_cidade');
        $this->addSql('ALTER TABLE "order" DROP COLUMN IF EXISTS endereco_cep');
        $this->addSql('ALTER TABLE "order" DROP COLUMN IF EXISTS endereco_referencia');
        $this->addSql('DROP TABLE IF EXISTS customer_address');
    }
}
 