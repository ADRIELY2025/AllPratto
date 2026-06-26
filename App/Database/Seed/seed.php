#!/usr/bin/env php
<?php

/**
 * Seed Runner — AllPratto
 *
 * Uso:
 *   php App/Database/Seed/seed.php          # roda todos os seeds em ordem
 *   php App/Database/Seed/seed.php 004      # roda apenas o seed que começa com "004"
 *
 * Os arquivos de seed devem estar em App/Database/Seed/ e seguir o padrão NNN_Nome.php
 */

declare(strict_types=1);

$dir = __DIR__;

// Filtro opcional por prefixo (ex: "004")
$filter = $argv[1] ?? null;

$files = glob($dir . '/[0-9][0-9][0-9]_*.php');
sort($files);

if (empty($files)) {
    echo "Nenhum arquivo de seed encontrado em {$dir}\n";
    exit(1);
}

$ran = 0;

foreach ($files as $file) {
    $basename = basename($file);

    if ($filter !== null && !str_starts_with($basename, $filter)) {
        continue;
    }

    echo "\n▶ Executando: {$basename}\n";

    try {
        require $file;
        $ran++;
    } catch (Throwable $e) {
        echo "❌ Erro em {$basename}: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\n✔ {$ran} seed(s) executado(s).\n";
