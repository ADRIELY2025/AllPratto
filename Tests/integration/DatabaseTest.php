<?php

declare(strict_types=1);

// Teste 1: conexão com PostgreSQL funciona — sem isso nada no sistema opera
test('conexão com PostgreSQL está ativa', function () {
    // Usa a classe de conexão real do projeto
    $pdo = App\Database\Connection::connection();

    // Verifica que retornou uma instância PDO válida
    expect($pdo)->toBeInstanceOf(PDO::class);

    // Executa um comando simples para confirmar que o banco responde
    $result = $pdo->query('SELECT 1 AS ok')->fetch();

    expect($result['ok'])->toBe(1);
});

// Teste 2: ciclo insert → select → delete funciona — garante integridade do CRUD
test('insert select e delete funcionam no PostgreSQL', function () {
    // Documento único para não colidir com dados reais
    $cpfTeste = '999.999.999-99';

    // INSERT — cria um registro temporário
    $inserido = App\Database\Builder\InsertQuery::insert('customer')
        ->save([
            'nome_fantasia'       => 'Teste Integração',
            'sobrenome_razao'     => 'Razão Teste',
            'cpf_cnpj'            => $cpfTeste,
            'inscricao_estadual'  => '000000',
            'nascimento_fundacao' => '2025-01-01',
            'ativo'               => true,
        ]);

    expect($inserido)->toBeTrue();

    // SELECT — confirma que o registro foi salvo corretamente
    $customer = App\Database\Builder\SelectQuery::select()
        ->from('customer')
        ->where('cpf_cnpj', '=', $cpfTeste)
        ->fetch();

    expect($customer)->not->toBeEmpty();
    expect($customer['nome_fantasia'])->toBe('Teste Integração');

    // DELETE — remove o registro de teste para não poluir o banco
    $deletado = App\Database\Builder\DeleteQuery::table('customer')
        ->where('id', '=', $customer['id'])
        ->delete();

    expect($deletado)->toBeTrue();
});
