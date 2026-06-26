<?php

declare(strict_types=1);

use App\Database\Connection;
use Faker\Factory;

require_once __DIR__ . '/../../../App/bootstrap.php';

$conn  = Connection::get();
$faker = Factory::create('pt_BR');

// Limpa em ordem por FK
$conn->executeStatement('DELETE FROM installment_sale_purchase');
$conn->executeStatement('DELETE FROM item_sale');
$conn->executeStatement('DELETE FROM sale');

// Busca IDs existentes
$customerIds = $conn->fetchFirstColumn('SELECT id FROM customer WHERE ativo = TRUE LIMIT 50');
$productRows = $conn->fetchAllAssociative('SELECT id, preco_venda FROM product WHERE ativo = TRUE');
$paymentRows = $conn->fetchAllAssociative('SELECT pt.id AS id_payment, i.id AS id_installment, i.parcela, i.intervalo FROM payment_terms pt JOIN installment i ON i.id_pagamento = pt.id ORDER BY pt.id, i.parcela');

if (empty($customerIds) || empty($productRows) || empty($paymentRows)) {
    echo "⚠️  Execute os seeds 002_Customer, 004_Product e 006_PaymentTerms antes deste.\n";
    exit(1);
}

// Agrupa installments por payment
$paymentInstallments = [];
foreach ($paymentRows as $row) {
    $paymentInstallments[$row['id_payment']][] = $row;
}
$paymentIds = array_keys($paymentInstallments);

$estadosVenda = ['PRE_VENDA', 'ORCAMENTO', 'VENDA'];
$totalVendas  = 50;

for ($v = 0; $v < $totalVendas; $v++) {
    $idCliente   = $faker->randomElement($customerIds);
    $estadoVenda = $faker->randomElement($estadosVenda);
    $criadoEm   = $faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d H:i:s');

    // Insere a venda
    $conn->insert('sale', [
        'id_cliente'    => $idCliente,
        'total_bruto'   => 0,
        'total_liquido' => 0,
        'desconto'      => 0,
        'acrescimo'     => 0,
        'observacao'    => $faker->boolean(20) ? $faker->sentence(4) : null,
        'estado_venda'  => $estadoVenda,
        'criado_em'     => $criadoEm,
        'atualizado_em' => $criadoEm,
    ]);

    // Busca o ID da venda recém-inserida
    $idVenda = (int) $conn->fetchOne(
        "SELECT id FROM sale ORDER BY criado_em DESC, id DESC LIMIT 1"
    );

    // Insere de 1 a 5 itens
    $qtdItens            = $faker->numberBetween(1, 5);
    $totalBruto          = 0.0;
    $produtosSelecionados = $faker->randomElements($productRows, min($qtdItens, count($productRows)));

    foreach ($produtosSelecionados as $produto) {
        $qtd         = (float) $faker->numberBetween(1, 5);
        $unitBruto   = (float) $produto['preco_venda'];
        $desconto    = $faker->boolean(20) ? round($unitBruto * 0.05, 4) : 0.0;
        $unitLiquido = $unitBruto - $desconto;
        $itemBruto   = round($qtd * $unitBruto, 4);
        $itemLiquido = round($qtd * $unitLiquido, 4);

        $conn->insert('item_sale', [
            'id_venda'         => $idVenda,
            'id_produto'       => $produto['id'],
            'nome'             => null,
            'descricao'        => null,
            'quantidade'       => $qtd,
            'unitario_bruto'   => $unitBruto,
            'total_bruto'      => $itemBruto,
            'unitario_liquido' => $unitLiquido,
            'total_liquido'    => $itemLiquido,
            'desconto'         => round($desconto * $qtd, 4),
            'acrescimo'        => 0,
            'criado_em'        => $criadoEm,
            'atualizado_em'    => $criadoEm,
        ]);

        $totalBruto += $itemBruto;
    }

    $totalLiquido = round($totalBruto * 0.95, 4);
    $desconto     = round($totalBruto - $totalLiquido, 4);

    $conn->update('sale', [
        'total_bruto'   => $totalBruto,
        'total_liquido' => $totalLiquido,
        'desconto'      => $desconto,
        'atualizado_em' => $criadoEm,
    ], ['id' => $idVenda]);

    // Parcelas apenas para vendas finalizadas (VENDA)
    if ($estadoVenda === 'VENDA') {
        $idPayment    = $faker->randomElement($paymentIds);
        $parcelas     = $paymentInstallments[$idPayment];
        $totalParc    = count($parcelas);
        $valorTotal   = (int) round($totalLiquido);
        $valorParcela = (int) round($valorTotal / $totalParc);
        $dataBase     = new DateTimeImmutable($criadoEm);

        foreach ($parcelas as $parcela) {
            $diasIntervalo = (int) $parcela['intervalo'];
            $vencimento   = $dataBase->modify("+{$diasIntervalo} days")->format('Y-m-d');
            $pago         = $faker->boolean(55);
            $status       = $pago ? 'pago' : 'aberto';

            $conn->insert('installment_sale_purchase', [
                'id_payment'      => $idPayment,
                'id_sale'         => $idVenda,
                'id_purchase'     => null,
                'id_installment'  => $parcela['id_installment'],
                'total_parcelas'  => $totalParc,
                'numero_parcela'  => $parcela['parcela'],
                'valor_parcela'   => $valorParcela,
                'valor_total'     => $valorTotal,
                'status'          => $status,
                'data_vencimento' => $vencimento,
                'criado_em'       => $criadoEm,
                'atualizado_em'   => $criadoEm,
            ]);
        }
    }
}

echo "✅ Seed sale: {$totalVendas} vendas com itens e parcelas inseridas.\n";