<?php

declare(strict_types=1);

// Verifica se todos os arquivos do projeto usam strict_types=1
arch('Todos os arquivos usam strict types')
    ->expect('App')
    ->toUseStrictTypes();

// Impede que debug acidental vá para produção
arch('Sem debug no código de produção')
    ->expect('App\Controller')
    ->not->toUse(['var_dump', 'dd', 'dump', 'die']);

// Controller não deve falar com banco diretamente
arch('Controller não acessam banco direto')
    ->expect('App\Controller')
    ->not->toUse('PDO');

// Bloqueia funções que executam código do sistema operacional
arch('Sem funções perigosas no código')
    ->expect('App')
    ->not->toUse([
        'eval',
        'exec',
        'shell_exec',
        'system',
        'passthru',
        'proc_open',
    ]);

// Controller devem ser classes finais
arch('Controller devem ser classes finais')
    ->expect('App\Controller')
    ->toBeFinal()
    ->ignoring('App\Controller\Base');