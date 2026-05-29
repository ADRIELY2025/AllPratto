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
test('Sem funções perigosas no código', function () {

    $content = file_get_contents(__DIR__ . '/../App/Controller/CustomerController.php');

    expect($content)
        ->not->toContain('eval(')
        ->not->toContain('exec(')
        ->not->toContain('shell_exec(')
        ->not->toContain('system(')
        ->not->toContain('passthru(')
        ->not->toContain('proc_open(');
});

# Garantir que classes são finais ou abstratas
arch('Controller devem ser classes finais')
    ->expect('App\Controller')
    ->toBeFinal()
    ->ignoring('App\Controller\Base');