<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

// CORRIGIDO: coluna `descricao` aparecia duas vezes (como "nome" e como "descricao").
// PostgreSQL rejeita SELECT com nome de coluna duplicado na mesma projeção.
// Removida a segunda ocorrência — `p.descricao AS nome` já cobre o dado.

final class Version20260528183336 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vw_Product';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS view_product');

        $this->addSql(<<<'SQL'
            CREATE OR REPLACE VIEW view_product AS
            SELECT
                p.id::TEXT,
                p.descricao      AS nome,
                p.codigo_barras,
                p.valor_venda    AS valor,
                p.categoria,
                p.emoji,
                p.tempo_preparo,
                p.destaque,
                p.imagem_url,
                p.ativo,
                TRUE             AS produto
            FROM public.product p
            WHERE p.ativo = TRUE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS view_product');
    }
}
