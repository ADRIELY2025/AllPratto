<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/*
 * CORREÇÕES em relação à versão original:
 *
 * 1. id_sale / id_purchase: ambos eram NOT NULL, impossibilitando qualquer insert
 *    (uma parcela pertence a UMA venda OU a UMA compra, nunca as duas).
 *    Agora são NULL com CHECK garantindo que exatamente um seja preenchido.
 *
 * 2. valor_parcela / valor_total: eram INTEGER (truncava centavos).
 *    Agora NUMERIC(18,4) consistente com o restante do sistema.
 *
 * 3. data_vencimento: era INTEGER NULL DEFAULT 0, sem sentido para uma data.
 *    Agora DATE NULL.
 */

final class Version20260611194437 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Installment_sale_purchase';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE installment_sale_purchase (
                id             BIGSERIAL      PRIMARY KEY,
                id_payment     BIGINT         NOT NULL REFERENCES payment_terms(id) ON DELETE CASCADE,
                id_sale        BIGINT         NULL     REFERENCES sale(id)          ON DELETE CASCADE,
                id_purchase    BIGINT         NULL     REFERENCES purchase(id)      ON DELETE CASCADE,
                id_installment BIGINT         NOT NULL REFERENCES installment(id)   ON DELETE CASCADE,
                total_parcelas INTEGER        NOT NULL,
                numero_parcela INTEGER        NOT NULL,
                valor_parcela  NUMERIC(18,4)  NOT NULL,
                valor_total    NUMERIC(18,4)  NOT NULL,
                status         VARCHAR(20)    NOT NULL DEFAULT 'aberto'
                                   CHECK (status IN ('aberto', 'pago', 'cancelado')),
                data_vencimento DATE          NULL,
                criado_em      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,

                CONSTRAINT chk_isp_sale_or_purchase
                    CHECK (
                        (id_sale IS NOT NULL AND id_purchase IS NULL) OR
                        (id_sale IS NULL     AND id_purchase IS NOT NULL)
                    )
            )
        SQL);

        $this->addSql('CREATE INDEX idx_isp_sale        ON installment_sale_purchase (id_sale)');
        $this->addSql('CREATE INDEX idx_isp_purchase    ON installment_sale_purchase (id_purchase)');
        $this->addSql('CREATE INDEX idx_isp_installment ON installment_sale_purchase (id_installment)');
        $this->addSql('CREATE INDEX idx_isp_status      ON installment_sale_purchase (status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS installment_sale_purchase');
    }
}