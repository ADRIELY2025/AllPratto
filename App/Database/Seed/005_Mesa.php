<?php

declare(strict_types=1);

use App\Database\Connection;

require_once __DIR__ . '/../../../App/bootstrap.php';

$conn = Connection::get();

$conn->executeStatement('DELETE FROM mesa');

$mesas = [
    ['numero' => 1,  'capacidade' => 2,  'status' => 'livre'],
    ['numero' => 2,  'capacidade' => 2,  'status' => 'livre'],
    ['numero' => 3,  'capacidade' => 4,  'status' => 'livre'],
    ['numero' => 4,  'capacidade' => 4,  'status' => 'livre'],
    ['numero' => 5,  'capacidade' => 4,  'status' => 'livre'],
    ['numero' => 6,  'capacidade' => 6,  'status' => 'livre'],
    ['numero' => 7,  'capacidade' => 6,  'status' => 'livre'],
    ['numero' => 8,  'capacidade' => 8,  'status' => 'livre'],
    ['numero' => 9,  'capacidade' => 8,  'status' => 'livre'],
    ['numero' => 10, 'capacidade' => 10, 'status' => 'livre'],
    ['numero' => 11, 'capacidade' => 4,  'status' => 'livre'],
    ['numero' => 12, 'capacidade' => 4,  'status' => 'livre'],
    ['numero' => 13, 'capacidade' => 2,  'status' => 'livre'],
    ['numero' => 14, 'capacidade' => 2,  'status' => 'livre'],
    ['numero' => 15, 'capacidade' => 12, 'status' => 'livre'],
];

foreach ($mesas as $mesa) {
    $conn->insert('mesa', [
        'numero'       => $mesa['numero'],
        'capacidade'   => $mesa['capacidade'],
        'status'       => $mesa['status'],
        'observacao'   => null,
        'ativo'        => 'true',
        'criado_em'    => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        'atualizado_em'=> (new DateTimeImmutable())->format('Y-m-d H:i:s'),
    ]);
}

echo '✅ Seed mesa: ' . count($mesas) . " registros inseridos (mesas 1–15).\n";