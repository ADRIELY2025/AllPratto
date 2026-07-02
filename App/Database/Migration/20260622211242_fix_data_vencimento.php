<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622211242 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix installment_sale: data_vencimento DATE';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DO $$
            DECLARE
                col_type TEXT;
            BEGIN

                -- ── Converte data_vencimento para DATE (se ainda INTEGER) ───
                SELECT data_type
                INTO col_type
                FROM information_schema.columns
                WHERE table_name  = 'installment_sale'
                  AND column_name = 'data_vencimento';

                IF col_type = 'integer' THEN
                    -- Dropa o DEFAULT antes do cast (Postgres não converte default automaticamente)
                    ALTER TABLE installment_sale
                        ALTER COLUMN data_vencimento DROP DEFAULT;

                    ALTER TABLE installment_sale
                        ALTER COLUMN data_vencimento TYPE DATE
                        USING (
                            CASE
                                WHEN data_vencimento IS NULL THEN NULL
                                WHEN data_vencimento = 0    THEN NULL
                                ELSE to_timestamp(data_vencimento)::DATE
                            END
                        );

                    -- Restaura o DEFAULT como NULL (DATE não tem valor inteiro padrão)
                    ALTER TABLE installment_sale
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
                -- Reverte data_vencimento para INTEGER
                IF EXISTS (
                    SELECT 1
                    FROM information_schema.columns
                    WHERE table_name  = 'installment_sale'
                      AND column_name = 'data_vencimento'
                      AND data_type   = 'date'
                ) THEN
                    ALTER TABLE installment_sale
                        ALTER COLUMN data_vencimento DROP DEFAULT;

                    ALTER TABLE installment_sale
                        ALTER COLUMN data_vencimento TYPE INTEGER
                        USING (
                            CASE
                                WHEN data_vencimento IS NULL THEN 0
                                ELSE EXTRACT(EPOCH FROM data_vencimento)::INTEGER
                            END
                        );

                    ALTER TABLE installment_sale
                        ALTER COLUMN data_vencimento SET DEFAULT 0;
                END IF;
            END
            $$;
        SQL);
    }
}
