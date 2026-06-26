<?php

declare(strict_types=1);

use App\Database\Connection;
use Faker\Factory;

require_once __DIR__ . '/../../../App/bootstrap.php';

$conn  = Connection::get();
$faker = Factory::create('pt_BR');

$conn->executeStatement('DELETE FROM company');

$total     = 10;
$batchSize = 10;

for ($i = 0; $i < $total; $i += $batchSize) {
    $currentBatch = min($batchSize, $total - $i);

    $batch = array_map(function () use ($faker) {
        $nome = $faker->company();

        return [
            'nome'         => $nome,
            'razao_social' => $nome . ' ' . $faker->companySuffix(),
            'cnpj'         => $faker->cnpj(false),
            'telefone'     => $faker->phoneNumber(),
            'email'        => $faker->companyEmail(),
            'ativo'        => 'true',
            'criado_em'    => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'atualizado_em'=> (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
    }, range(1, $currentBatch));

    foreach ($batch as $row) {
        $conn->insert('company', $row);
    }
}

echo "✅ Seed company: {$total} registros inseridos.\n";