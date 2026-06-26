<?php

declare(strict_types=1);

use App\Database\Connection;
use Faker\Factory;

require_once __DIR__ . '/../../../App/bootstrap.php';

$conn  = Connection::get();
$faker = Factory::create('pt_BR');

$conn->executeStatement('DELETE FROM customer');

$total     = 10;
$batchSize = 25;

for ($i = 0; $i < $total; $i += $batchSize) {
    $currentBatch = min($batchSize, $total - $i);

    $batch = array_map(function () use ($faker) {
        $isPessoa = (bool) $faker->boolean(70); // 70% pessoa física

        return [
            'nome_fantasia'       => $isPessoa ? $faker->firstName() : $faker->company(),
            'sobrenome_razao'     => $isPessoa ? $faker->lastName()  : $faker->company() . ' Ltda',
            'cpf_cnpj'            => $isPessoa ? $faker->cpf(false)  : $faker->cnpj(false),
            'rg_ie'               => $isPessoa ? $faker->rg(false)   : $faker->numerify('##########'),
            'nascimento_fundacao' => $faker->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'ativo'               => $faker->boolean(90) ? 'true' : 'false',
            'criado_em'           => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'atualizado_em'       => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
    }, range(1, $currentBatch));

    foreach ($batch as $row) {
        $conn->insert('customer', $row);
    }
}

echo "✅ Seed customer: {$total} registros inseridos.\n";