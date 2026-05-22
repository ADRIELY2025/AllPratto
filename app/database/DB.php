<?php

declare(strict_types=1);

namespace app\database;

use Doctrine\DBAL\Query\QueryBuilder;

final class DB
{
    # Retorna um QueryBuilder com SELECT já configurado. Sem argumentos seleciona tudo ('*').
    public static function select(string ...$columns): QueryBuilder
    {
        $qb = Connection::get()->createQueryBuilder();

        return empty($columns)
            ? $qb->select('*')
            : $qb->select(...$columns);
    }

    # Retorna a conexão DBAL para operações de escrita (insert, update, delete, transação, execute).
    public static function connection(): \Doctrine\DBAL\Connection
    {
        return Connection::get();
    }

    # Previne instanciação — uso exclusivo via métodos estáticos
    private function __construct() {}
}
