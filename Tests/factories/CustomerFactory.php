<?php

declare(strict_types=1);

// Dentro de um teste em tests/Integration ou tests/Feature
test('insere customer com dados da factory', function () {
    $faker = Faker\Factory::create('pt_BR');

    $dados = [
        'nome_fantasia'       => $faker->company(),
        'sobrenome_razao'     => $faker->name(),
        'cpf_cnpj'            => $faker->cpf(),
        'inscricao_estadual'  => $faker->numerify('#########'),
        'nascimento_fundacao' => $faker->date('Y-m-d'),
        'ativo'               => true,
    ];

    $inserido = App\Database\Builder\InsertQuery::insert('customer')
        ->save($dados);

    expect($inserido)->toBeTrue();
});
