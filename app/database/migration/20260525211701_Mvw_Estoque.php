<?php

declare(strict_types=1);

namespace app\database\migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525211701 extends AbstractMigration
{
     public function getDescription(): string
    {
        return 'Cria materialized view mvw_estoque e índices HASH em product e stock_movement';
    }
 
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE MATERIALIZED VIEW mvw_estoque AS
            SELECT
                id_produto,
                product.nome,
                SUM(COALESCE(stock_movement.quantidade_entrada, 0)) AS total_entradas,
                SUM(COALESCE(stock_movement.quantidade_saida,  0)) AS total_saidas,
                (
                    SUM(COALESCE(stock_movement.quantidade_entrada, 0)) -
                    SUM(COALESCE(stock_movement.quantidade_saida,  0))
                ) AS estoque_atual,
                MAX(data_cadastro) AS ultima_movimentacao
            FROM stock_movement
            LEFT JOIN product ON product.id = stock_movement.id_produto
            WHERE product.excluido != true
            GROUP BY stock_movement.id_produto, product.nome
        SQL);
 
        $this->addSql('CREATE INDEX product_id_hash              ON product        USING HASH (id)');
        $this->addSql('CREATE INDEX product_nome_hash            ON product        USING HASH (nome)');
        $this->addSql('CREATE INDEX stock_movement_idprd_hash    ON stock_movement USING HASH (id_produto)');
    }
 
    public function down(Schema $schema): void
    {
        $this->addSql('DROP MATERIALIZED VIEW IF EXISTS mvw_estoque CASCADE');
    }
}
