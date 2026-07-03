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
    // Entradas e Aperitivos
    ['nome' => 'Carpaccio de Carne',            'grupo' => 'Entradas e Aperitivos', 'preco_compra' => 6.50,  'preco_venda' => 26.00],
    ['nome' => 'Bolinho de Bacalhau',           'grupo' => 'Entradas e Aperitivos', 'preco_compra' => 4.00,  'preco_venda' => 18.00],
    ['nome' => 'Hummus com Pão Sírio',          'grupo' => 'Entradas e Aperitivos', 'preco_compra' => 3.00,  'preco_venda' => 14.00],
    ['nome' => 'Anéis de Cebola Empanados',     'grupo' => 'Entradas e Aperitivos', 'preco_compra' => 2.80,  'preco_venda' => 13.00],

    // Prato Principal
    ['nome' => 'Risoto de Camarão',             'grupo' => 'Prato Principal',        'preco_compra' => 10.00, 'preco_venda' => 48.00],
    ['nome' => 'Filé Mignon ao Molho Madeira',  'grupo' => 'Prato Principal',        'preco_compra' => 14.00, 'preco_venda' => 62.00],
    ['nome' => 'Salmão Grelhado com Legumes',   'grupo' => 'Prato Principal',        'preco_compra' => 11.00, 'preco_venda' => 50.00],
    ['nome' => 'Moqueca de Peixe',              'grupo' => 'Prato Principal',        'preco_compra' => 9.50,  'preco_venda' => 44.00],
    ['nome' => 'Curry de Grão-de-Bico (Vegano)','grupo' => 'Prato Principal',        'preco_compra' => 5.00,  'preco_venda' => 24.00],

    // Sobremesas
    ['nome' => 'Petit Gateau',                  'grupo' => 'Sobremesas',             'preco_compra' => 4.20,  'preco_venda' => 17.00],
    ['nome' => 'Mousse de Maracujá',            'grupo' => 'Sobremesas',             'preco_compra' => 2.80,  'preco_venda' => 11.00],
    ['nome' => 'Cheesecake de Frutas Vermelhas','grupo' => 'Sobremesas',             'preco_compra' => 4.50,  'preco_venda' => 18.00],

    // Bebidas
    ['nome' => 'Limonada Suíça',                'grupo' => 'Bebidas',                'preco_compra' => 1.50,  'preco_venda' => 8.00],
    ['nome' => 'Refrigerante Lata',             'grupo' => 'Bebidas',                'preco_compra' => 1.80,  'preco_venda' => 7.00],
    ['nome' => 'Caipirinha',                    'grupo' => 'Bebidas',                'preco_compra' => 3.50,  'preco_venda' => 20.00],
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