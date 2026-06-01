<?php

declare(strict_types=1);

arch('Todos os arquivos usam strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('Sem debug no código de produção')
    ->expect('App\Controller')
    ->not->toUse(['var_dump', 'dd', 'dump', 'die']);

arch('Controller não acessam banco direto')
    ->expect('App\Controller')
    ->not->toUse(\PDO::class);

# Nenhuma classe deve usar funções perigosas
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



# Garantir que classes são finais ou abstratas
arch('Controller devem ser classes finais')
    ->expect('App\Controller')
    ->toBeFinal()
    ->ignoring('App\Controller\Base');