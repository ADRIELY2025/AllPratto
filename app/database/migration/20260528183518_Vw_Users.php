<?php

declare(strict_types=1);

namespace app\database\migration;

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
        #Cria ou substitui a view de leitura com pivot dos contatos por tipo (idempotente).
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
                MAX(c.contato) FILTER (WHERE c.tipo = 'EMAIL')    AS email,
                MAX(c.contato) FILTER (WHERE c.tipo = 'CELULAR')  AS celular,
                MAX(c.contato) FILTER (WHERE c.tipo = 'TELEFONE') AS telefone,
                MAX(c.contato) FILTER (WHERE c.tipo = 'WHATSAPP') AS whatsapp,
                u.criado_em,
                u.atualizado_em
            FROM public.users u
            LEFT JOIN public.contact c
                   ON c.id_usuario = u.id
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