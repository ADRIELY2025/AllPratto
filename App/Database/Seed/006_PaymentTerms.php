<?php

declare(strict_types=1);

use App\Database\Connection;

require_once __DIR__ . '/../../../App/bootstrap.php';

$conn = Connection::get();

// Deleta na ordem correta por causa das FKs
$conn->executeStatement('DELETE FROM installment');
$conn->executeStatement('DELETE FROM payment_terms');

/**
 * Insere uma condição de pagamento e suas parcelas.
 *
 * @param string $codigo
 * @param string $titulo
 * @param string $atalho
 * @param array<array{parcela: int, intervalo: int}> $parcelas
 */
function inserirCondicao(
    \Doctrine\DBAL\Connection $conn,
    string $codigo,
    string $titulo,
    string $atalho,
    array $parcelas
): void {
    $agora = (new DateTimeImmutable())->format('Y-m-d H:i:s');

    $conn->insert('payment_terms', [
        'codigo'       => $codigo,
        'titulo'       => $titulo,
        'atalho'       => $atalho,
        'criado_em'    => $agora,
        'atualizado_em'=> $agora,
    ]);

    $id = (int) $conn->lastInsertId();

    if ($id === 0) {
        // PostgreSQL não suporta lastInsertId() sem sequence — busca pelo codigo
        $id = (int) $conn->fetchOne(
            'SELECT id FROM payment_terms WHERE codigo = ?',
            [$codigo]
        );
    }

    foreach ($parcelas as $p) {
        $conn->insert('installment', [
            'id_pagamento'             => $id,
            'parcela'                  => $p['parcela'],
            'intervalo'                => $p['intervalo'],
            'alterar_vencimento_conta' => 0,
            'criado_em'                => $agora,
            'atualizado_em'            => $agora,
        ]);
    }
}

// À Vista
inserirCondicao($conn, 'avista', 'À Vista', 'AV', [
    ['parcela' => 1, 'intervalo' => 0],
]);

// Pix
inserirCondicao($conn, 'pix', 'Pix', 'PIX', [
    ['parcela' => 1, 'intervalo' => 0],
]);

// Dinheiro
inserirCondicao($conn, 'dinheiro', 'Dinheiro', 'DIN', [
    ['parcela' => 1, 'intervalo' => 0],
]);

// Cartão de Débito
inserirCondicao($conn, 'debito', 'Cartão Débito', 'DEB', [
    ['parcela' => 1, 'intervalo' => 1],
]);

// Cartão de Crédito (até 12x)
inserirCondicao($conn, 'credito', 'Cartão Crédito', 'CC', array_map(
    fn($n) => ['parcela' => $n, 'intervalo' => $n * 30],
    range(1, 12)
));

// Boleto (até 6x)
inserirCondicao($conn, 'boleto', 'Boleto', 'BOL', array_map(
    fn($n) => ['parcela' => $n, 'intervalo' => $n * 30],
    range(1, 6)
));

// Cheque (até 6x)
inserirCondicao($conn, 'cheque', 'Cheque', 'CHQ', array_map(
    fn($n) => ['parcela' => $n, 'intervalo' => $n * 30],
    range(1, 6)
));

// Crediário (até 3x)
inserirCondicao($conn, 'crediario', 'Crediário', 'CRE', array_map(
    fn($n) => ['parcela' => $n, 'intervalo' => $n * 30],
    range(1, 3)
));

echo "✅ Seed payment_terms e installment inseridos com sucesso!\n";
