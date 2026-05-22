<?php

declare(strict_types=1);

arch('Todos os arquivos usam strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('Sem debug no código de produção')
    ->expect('app\controller')
    ->not->toUse(['var_dump', 'dd', 'dump', 'die']);

arch('Controllers não acessam banco direto')
    ->expect('app\controller')
    ->not->toUse('PDO');

#Nenhuma classe deve usar funções perigosas
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

#Garantir que classes são finais ou abstratas
arch('Controllers devem ser classes finais')
    ->expect('app\controller')
    ->toBeFinal()
    ->ignoring('app\controller\Base');