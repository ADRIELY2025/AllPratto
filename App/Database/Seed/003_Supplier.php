<?php

declare(strict_types=1);

use App\Database\Connection;
use Faker\Factory;

require_once __DIR__ . '/../../../App/bootstrap.php';

$conn  = Connection::get();
$faker = Factory::create('pt_BR');

$conn->executeStatement('DELETE FROM supplier');

$total     = 10;
$batchSize = 10;

for ($i = 0; $i < $total; $i += $batchSize) {
    $currentBatch = min($batchSize, $total - $i);

    $batch = array_map(function () use ($faker) {
        $nome = $faker->company();

        return [
            'nome_fantasia'      => $nome,
            'razao_social'       => $nome . ' ' . $faker->companySuffix(),
            'inscricao_estadual' => $faker->numerify('###########'),
            'telefone'           => $faker->phoneNumber(),
            'email'              => $faker->companyEmail(),
            'cnpj_cpf'           => $faker->cnpj(false),
            'ie_rg'              => $faker->numerify('##########'),
            'ativo'              => $faker->boolean(90) ? 'true' : 'false',
            'criado_em'          => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'atualizado_em'      => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
    }, range(1, $currentBatch));

    foreach ($batch as $row) {
        $conn->insert('supplier', $row);
    }
}

echo "✅ Seed supplier: {$total} registros inseridos.\n";