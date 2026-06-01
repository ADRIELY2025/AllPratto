<?php

declare(strict_types=1);

return [
    'table_storage' => [
        # Nome da tabela que o Doctrine usa para registrar quais migrations já rodaram
        'table_name' => 'doctrine_migration_versions',
    ],

    'migrations_paths' => [
        # namespace => caminho no disco
        'App\Database\Migration' => __DIR__ . '/App/Database/Migration',
    ],

    # Executa todas as migrations numa única transação — se uma falhar, reverte tudo
    'all_or_nothing' => true,

    # Cada migration roda dentro de uma transação individual
    'transactional' => true,

    # Verifica se a plataforma do banco é compatível com a migration
    'check_database_platform' => true,

    'organize_migrations' => 'none',
];
