<?php

declare(strict_types=1);

namespace App\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528183518 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vw_Users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE VIEW public.vw_user AS
            SELECT
                u.id,
                u.nome,
                u.sobrenome,
                u.cpf,
                u.rg,
                u.senha,
                u.ativo,
                u.administrador,
                MAX(c.contato) FILTER (WHERE c.tipo_contato = 'EMAIL')    AS email,
                MAX(c.contato) FILTER (WHERE c.tipo_contato = 'CELULAR')  AS celular,
                MAX(c.contato) FILTER (WHERE c.tipo_contato = 'TELEFONE') AS telefone,
                MAX(c.contato) FILTER (WHERE c.tipo_contato = 'WHATSAPP') AS whatsapp,
                u.criado_em,
                u.atualizado_em
            FROM public.users u
            LEFT JOIN public.contact c ON c.id_usuario = u.id
            GROUP BY
                u.id,
                u.nome,
                u.sobrenome,
                u.cpf,
                u.rg,
                u.senha,
                u.ativo,
                u.administrador,
                u.criado_em,
                u.atualizado_em
        SQL);
    }

    public function down(Schema $schema): void
    {
       
    }
}