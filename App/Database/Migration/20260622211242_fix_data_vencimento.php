<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622211242 extends AbstractMigration
{
 
    public function getDescription(): string
    {
        return 'Fix data_vencimento column type from INTEGER to DATE in installment_sale_purchase';
    }

    public function up(Schema $schema): void
{
    $this->addSql(<<<'SQL'
        DO $$
        BEGIN
            IF (SELECT data_type FROM information_schema.columns
                WHERE table_name = 'installment_sale_purchase'
                AND column_name = 'data_vencimento') = 'integer' THEN

                ALTER TABLE installment_sale_purchase
                    ALTER COLUMN data_vencimento TYPE DATE
                    USING (
                        CASE
                            WHEN data_vencimento IS NULL THEN NULL
                            WHEN data_vencimento = 0 THEN NULL
                            ELSE to_timestamp(data_vencimento)::DATE
                        END
                    );
            END IF;
        END
        $$;
    SQL);
}
    

    public function down(Schema $schema): void
    {
    }
}