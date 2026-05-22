<?php

declare(strict_types=1);

test('ciclo CRUD completo no banco PostgreSQL', function () {

    // 1. INSERT — cria um registro real no banco
    $inserido = \App\Database\Builder\InsertQuery::insert('customer')
        ->save([
            'nome_fantasia'       => 'Teste Integração',
            'sobrenome_razao'     => 'Razão Teste',
            'cpf_cnpj'            => '000.000.000-00',
            'inscricao_estadual'  => '000000',
            'nascimento_fundacao' => '2025-01-01',
            'ativo'               => true,
        ]);

    expect($inserido)->toBeTrue();

    // 2. SELECT — busca o registro recém-criado
    $customer = \App\Database\Builder\SelectQuery::select()
        ->from('customer')
        ->where('cpf_cnpj', '=', '000.000.000-00')
        ->fetch();

    expect($customer)->not->toBeEmpty();
    expect($customer['nome_fantasia'])->toBe('Teste Integração');

    $id = $customer['id'];

    // 3. UPDATE — altera o registro
    $atualizado = \App\Database\Builder\UpdateQuery::table('customer')
        ->set(['nome_fantasia' => 'Teste Alterado'])
        ->where('id', '=', $id)
        ->update();

    expect($atualizado)->toBeTrue();

    // Confirma que a alteração persistiu
    $customerAlterado = \App\Database\Builder\SelectQuery::select()
        ->from('customer')
        ->where('id', '=', $id)
        ->fetch();

    expect($customerAlterado['nome_fantasia'])->toBe('Teste Alterado');

    // 4. DELETE — remove o registro de teste
    $deletado = \App\Database\Builder\DeleteQuery::table('customer')
        ->where('id', '=', $id)
        ->delete();

    expect($deletado)->toBeTrue();

    // Confirma que não existe mais
    $customerRemovido = \App\Database\Builder\SelectQuery::select()
        ->from('customer')
        ->where('id', '=', $id)
        ->fetch();

    expect($customerRemovido)->toBeEmpty();
});
