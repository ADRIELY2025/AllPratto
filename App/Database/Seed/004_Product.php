<?php

declare(strict_types=1);

use App\Database\Connection;
use Faker\Factory;

require_once __DIR__ . '/../../../App/bootstrap.php';

$conn  = Connection::get();
$faker = Factory::create('pt_BR');

// ─────────────────────────────────────────────────────────────────────────────
// ⚠️  NÃO apaga produtos existentes.
//     A seed é idempotente: só insere os produtos da lista abaixo que ainda
//     NÃO existem na tabela (verificado por nome, case-insensitive).
//     Esta seed NÃO trata imagens — os produtos são criados sem imagem
//     (imagem_url / nome_imagem ficam NULL). A imagem pode ser adicionada
//     depois pela tela de edição do produto.
// ─────────────────────────────────────────────────────────────────────────────

$produtos = [

    // Entradas
    [
        'nome' => 'Bruschetta de Tomate',
        'grupo' => 'Entradas',
        'preco_compra' => 5.80,
        'preco_venda' => 24.90
    ],
    [
        'nome' => 'Coxinha de Frango',
        'grupo' => 'Entradas',
        'preco_compra' => 3.80,
        'preco_venda' => 18.90
    ],
    [
        'nome' => 'Queso Fundido',
        'grupo' => 'Entradas',
        'preco_compra' => 8.50,
        'preco_venda' => 34.90
    ],
    [
        'nome' => 'Salada Caprese',
        'grupo' => 'Entradas',
        'preco_compra' => 7.20,
        'preco_venda' => 29.90
    ],

    // Pratos Principais
    [
        'nome' => 'Boeuf Bourguignon',
        'grupo' => 'Prato Principal',
        'preco_compra' => 22.00,
        'preco_venda' => 84.90
    ],
    [
        'nome' => 'Lasanha de Berinjela',
        'grupo' => 'Prato Principal',
        'preco_compra' => 12.50,
        'preco_venda' => 49.90
    ],
    [
        'nome' => 'Spaghetti Carbonara',
        'grupo' => 'Prato Principal',
        'preco_compra' => 10.80,
        'preco_venda' => 46.90
    ],
    [
        'nome' => 'Tacos Carnitas',
        'grupo' => 'Prato Principal',
        'preco_compra' => 11.80,
        'preco_venda' => 47.90
    ],
    [
        'nome' => 'Frango à Passarinho',
        'grupo' => 'Prato Principal',
        'preco_compra' => 9.50,
        'preco_venda' => 39.90
    ],

    // Sobremesas
    [
        'nome' => 'Crème Brûlée',
        'grupo' => 'Sobremesas',
        'preco_compra' => 6.50,
        'preco_venda' => 24.90
    ],
    [
        'nome' => 'Pudim de Leite',
        'grupo' => 'Sobremesas',
        'preco_compra' => 4.20,
        'preco_venda' => 18.90
    ],
    [
        'nome' => 'Tiramisù',
        'grupo' => 'Sobremesas',
        'preco_compra' => 6.80,
        'preco_venda' => 27.90
    ],

    // Bebidas
    [
        'nome' => 'Água Mineral',
        'grupo' => 'Bebidas',
        'preco_compra' => 1.80,
        'preco_venda' => 6.90
    ],
    [
        'nome' => 'Margarita',
        'grupo' => 'Bebidas',
        'preco_compra' => 7.50,
        'preco_venda' => 29.90
    ],
    [
        'nome' => 'Suco de Laranja',
        'grupo' => 'Bebidas',
        'preco_compra' => 3.50,
        'preco_venda' => 12.90
    ],

];

$inseridos = 0;
$ignorados = 0;

foreach ($produtos as $produto) {

    // Pula se já existe produto ativo com esse nome
    $jaExiste = (int) $conn->fetchOne(
        "SELECT COUNT(*) FROM product WHERE nome ILIKE ? AND excluido = false",
        [$produto['nome']]
    );

    if ($jaExiste > 0) {
        echo "  ⏭️  [{$produto['nome']}] já existe — ignorado\n";
        $ignorados++;
        continue;
    }

    $margem = round(
        ($produto['preco_venda'] - $produto['preco_compra']) / $produto['preco_compra'] * 100,
        2
    );

    // ✅ RETURNING id → pega o ID real do PostgreSQL, sem depender de lastInsertId()
    $id = (int) $conn->fetchOne(
        "INSERT INTO product (
            nome,
            codigo_barra,
            grupo,
            unidade,
            imagem_url,
            nome_imagem,
            preco_compra,
            total_imposto,
            margem_lucro,
            custo_operacional,
            valor_venda_sugerido,
            preco_venda,
            tempo_preparo,
            descricao,
            ativo,
            excluido,
            criado_em,
            atualizado_em
        ) VALUES (
            ?, ?, ?, ?,
            NULL, NULL,
            ?, ?, ?,
            ?, ?, ?,
            ?, ?, true, false,
            NOW(), NOW()
        ) RETURNING id",
        [
            $produto['nome'],
            $faker->ean13(),
            $produto['grupo'],
            'UN',
            $produto['preco_compra'],
            round($produto['preco_compra'] * 0.10, 4),
            $margem,
            round($produto['preco_compra'] * 0.05, 4),
            $produto['preco_venda'],
            $produto['preco_venda'],
            $faker->randomElement(['5 min', '10 min', '15 min', '20 min', '30 min']),
            $faker->sentence(6),
        ]
    );

    echo "  ✅ [{$produto['nome']}] ID={$id} inserido (sem imagem)\n";
    $inseridos++;
}

echo "\n✅ Seed product (002) finalizada.\n";
echo "   ✅ Inseridos   : {$inseridos}\n";
echo "   ⏭️  Já existiam : {$ignorados}\n";