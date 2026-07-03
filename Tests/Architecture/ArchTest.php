<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Testes de Arquitetura (Architecture Tests)
|--------------------------------------------------------------------------
|
| Diferente dos testes de unidade/feature, os testes arch() não executam
| o código: eles fazem análise estática, varrendo o código-fonte para
| garantir que regras de design do projeto são respeitadas.
|
*/

arch('Todos os arquivos usam strict types')
    ->expect('App')
    ->toUseStrictTypes();
// Por quê: com strict_types=1, o PHP não faz conversão implícita de tipo
// em parâmetros e retornos, evitando bugs sutis (ex: "18,90" virar 18.0
// silenciosamente em vez de gerar erro).

arch('Sem debug no código de produção')
    ->expect('App\Controller')
    ->not->toUse(['var_dump', 'dd', 'dump', 'die']);
// Por quê: chamadas de debug esquecidas em produção podem vazar dados
// sensíveis na resposta HTTP ou travar a aplicação inesperadamente
// (die/dd interrompem a execução no meio da requisição).

arch('Controller não acessam banco direto')
    ->expect('App\Controller')
    ->not->toUse(\PDO::class);
// Por quê: o projeto usa Doctrine DBAL como camada de abstração de banco
// (App\Database\DB). Se um Controller usasse \PDO diretamente, ficaria
// acoplado ao driver específico do banco, perdendo os benefícios do DBAL
// (portabilidade entre bancos, proteção contra SQL injection via
// parâmetros nomeados, testabilidade).

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
// Por quê: essas funções permitem execução arbitrária de comandos do
// sistema operacional. Se algum dado do usuário chegar até elas sem
// sanitização, é uma vulnerabilidade crítica de RCE (Remote Code
// Execution). Bloquear o uso na arquitetura é uma camada extra de
// proteção, independente de revisão manual.

# Garantir que classes são finais ou abstratas
arch('Controller devem ser classes finais')
    ->expect('App\Controller')
    ->toBeFinal()
    ->ignoring('App\Controller\Base');
// Por quê: Controllers representam endpoints concretos da aplicação e não
// devem ser estendidos livremente por herança, o que poderia gerar
// comportamento inesperado em rotas. A classe Base é a exceção
// proposital, pois é feita para ser herdada pelos demais Controllers.

