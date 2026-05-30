<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528183837 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mvw_Estoque';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE MATERIALIZED VIEW mvw_estoque AS
            SELECT
                sm.id_produto,
                p.descricao AS nome,
                SUM(COALESCE(sm.quantidade_entrada, 0))                                                        AS total_entradas,
                SUM(COALESCE(sm.quantidade_saida,   0))                                                        AS total_saidas,
                SUM(COALESCE(sm.quantidade_entrada, 0)) - SUM(COALESCE(sm.quantidade_saida, 0))                AS estoque_atual,
                MAX(sm.data_cadastro)                                                                          AS ultima_movimentacao
            FROM public.stock_movement sm
            LEFT JOIN public.product p ON p.id = sm.id_produto
            GROUP BY sm.id_produto, p.descricao
        SQL);

        $this->addSql('CREATE INDEX product_id_hash            ON public.product        USING HASH (id)');
        $this->addSql('CREATE INDEX product_nome_hash          ON public.product        USING HASH (descricao)');
        $this->addSql('CREATE INDEX stock_movement_idprd_hash  ON public.stock_movement USING HASH (id_produto)');
    }

    public function down(Schema $schema): void
    {
    
    }
}
