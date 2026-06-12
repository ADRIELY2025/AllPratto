<?php

declare(strict_types=1);

/*
 * Garante que todos os arquivos utilizem strict_types.
 * Evita conversões automáticas de tipos e torna o código mais seguro.
 */
arch('Todos os arquivos usam strict types')
    ->expect('App')
    ->toUseStrictTypes();

/**
 * Impede que comandos de depuração sejam enviados para produção.
 */
arch('Sem debug no código de produção')
    ->expect('App\Controller')
    ->not->toUse([
        'var_dump',
        'dd',
        'dump',
        'die',
    ]);

/**
 * Controllers não devem acessar o banco diretamente.
 * A responsabilidade do Controller é receber a requisição
 * e coordenar o fluxo da aplicação.
 */
arch('Controller não acessam banco direto')
    ->expect('App\Controller')
    ->not->toUse(\PDO::class);

/**
 * A camada Database não deve depender dos Controllers.
 * Mantém a independência entre persistência e interface da aplicação.
 */
arch('Database não depende de Controllers')
    ->expect('App\Database')
    ->not->toUse('App\Controller');

/**
 * As páginas devem apenas exibir informações.
 * O acesso ao banco deve ocorrer em camadas apropriadas.
 */
arch('Pages não acessam banco diretamente')
    ->expect('App\View\Pages')
    ->not->toUse(\PDO::class);

/**
 * Middlewares são responsáveis por autenticação,
 * autorização e filtros de requisição.
 * Não devem manipular diretamente o banco de dados.
 */



/**
 * Impede o uso de funções potencialmente perigosas
 * que podem comprometer a segurança da aplicação.
 */
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

/**
 * Controllers devem ser finais para evitar heranças
 * desnecessárias e manter uma arquitetura mais previsível.
 */
arch('Controller devem ser classes finais')
    ->expect('App\Controller')
    ->toBeFinal()
    ->ignoring('App\Controller\Base');