<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622211242 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix installment_sale_purchase: id_purchase nullable + chk constraint + data_vencimento DATE';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DO $$
            DECLARE
                col_type TEXT;
            BEGIN

                -- ── 1. Torna id_purchase nullable (se ainda NOT NULL) ──────────
                IF EXISTS (
                    SELECT 1
                    FROM information_schema.columns
                    WHERE table_name  = 'installment_sale_purchase'
                      AND column_name = 'id_purchase'
                      AND is_nullable = 'NO'
                ) THEN
                    ALTER TABLE installment_sale_purchase
                        ALTER COLUMN id_purchase DROP NOT NULL;
                END IF;

                -- ── 2. Torna id_sale nullable (se ainda NOT NULL) ─────────────
                IF EXISTS (
                    SELECT 1
                    FROM information_schema.columns
                    WHERE table_name  = 'installment_sale_purchase'
                      AND column_name = 'id_sale'
                      AND is_nullable = 'NO'
                ) THEN
                    ALTER TABLE installment_sale_purchase
                        ALTER COLUMN id_sale DROP NOT NULL;
                END IF;

                -- ── 3. Adiciona CHECK constraint (se ainda não existir) ────────
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conname = 'chk_isp_sale_or_purchase'
                ) THEN
                    ALTER TABLE installment_sale_purchase
                        ADD CONSTRAINT chk_isp_sale_or_purchase CHECK (
                            (id_sale IS NOT NULL AND id_purchase IS NULL) OR
                            (id_sale IS NULL     AND id_purchase IS NOT NULL)
                        );
                END IF;

                -- ── 4. Converte data_vencimento para DATE (se ainda INTEGER) ───
                SELECT data_type
                INTO col_type
                FROM information_schema.columns
                WHERE table_name  = 'installment_sale_purchase'
                  AND column_name = 'data_vencimento';

                IF col_type = 'integer' THEN
                    -- Dropa o DEFAULT antes do cast (Postgres não converte default automaticamente)
                    ALTER TABLE installment_sale_purchase
                        ALTER COLUMN data_vencimento DROP DEFAULT;

                    ALTER TABLE installment_sale_purchase
                        ALTER COLUMN data_vencimento TYPE DATE
                        USING (
                            CASE
                                WHEN data_vencimento IS NULL THEN NULL
                                WHEN data_vencimento = 0    THEN NULL
                                ELSE to_timestamp(data_vencimento)::DATE
                            END
                        );

                    -- Restaura o DEFAULT como NULL (DATE não tem valor inteiro padrão)
                    ALTER TABLE installment_sale_purchase
                        ALTER COLUMN data_vencimento SET DEFAULT NULL;
                END IF;

            END
            $$;
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                -- Remove a constraint
                IF EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conname = 'chk_isp_sale_or_purchase'
                ) THEN
                    ALTER TABLE installment_sale_purchase
                        DROP CONSTRAINT chk_isp_sale_or_purchase;
                END IF;

                -- Reverte data_vencimento para INTEGER
                IF EXISTS (
                    SELECT 1
                    FROM information_schema.columns
                    WHERE table_name  = 'installment_sale_purchase'
                      AND column_name = 'data_vencimento'
                      AND data_type   = 'date'
                ) THEN
                    ALTER TABLE installment_sale_purchase
                        ALTER COLUMN data_vencimento DROP DEFAULT;

                    ALTER TABLE installment_sale_purchase
                        ALTER COLUMN data_vencimento TYPE INTEGER
                        USING (
                            CASE
                                WHEN data_vencimento IS NULL THEN 0
                                ELSE EXTRACT(EPOCH FROM data_vencimento)::INTEGER
                            END
                        );

                    ALTER TABLE installment_sale_purchase
                        ALTER COLUMN data_vencimento SET DEFAULT 0;
                END IF;

                -- Volta id_purchase e id_sale para NOT NULL
                ALTER TABLE installment_sale_purchase
                    ALTER COLUMN id_purchase SET NOT NULL,
                    ALTER COLUMN id_sale     SET NOT NULL;
            END
            $$;
        SQL);
    }
}