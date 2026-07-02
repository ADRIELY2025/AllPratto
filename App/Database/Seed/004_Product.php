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
// ─────────────────────────────────────────────────────────────────────────────

$storageDir = ROOT . '/storage/produtos';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0777, true);
}

$dirImagens = ROOT . '/public/img';

$produtos = [
    // Entradas e Aperitivos
    ['nome' => 'Bruschetta de Tomate',      'grupo' => 'Entradas e Aperitivos', 'preco_compra' => 3.00,  'preco_venda' => 12.00, 'imagem' => 'bruschetta-de-tomate.jpg'],
    ['nome' => 'Salada Caprese',            'grupo' => 'Entradas e Aperitivos', 'preco_compra' => 4.00,  'preco_venda' => 16.00, 'imagem' => 'salada-caprese.jpg'],
    ['nome' => 'Coxinha de Frango',         'grupo' => 'Entradas e Aperitivos', 'preco_compra' => 2.50,  'preco_venda' => 8.00,  'imagem' => 'coxinha-de-frango.jpg'],
    ['nome' => 'Queso Fundido',             'grupo' => 'Entradas e Aperitivos', 'preco_compra' => 5.00,  'preco_venda' => 20.00, 'imagem' => 'queso-fundido.jpg'],

    // Prato Principal
    ['nome' => 'Spaghetti alla Carbonara',  'grupo' => 'Prato Principal',        'preco_compra' => 6.00,  'preco_venda' => 28.00, 'imagem' => 'spaghetti-carbonara.jpg'],
    ['nome' => 'Frango à Passarinho',       'grupo' => 'Prato Principal',        'preco_compra' => 8.00,  'preco_venda' => 32.00, 'imagem' => 'frango-a-passarinho.jpg'],
    ['nome' => 'Tacos de Carnitas',        'grupo' => 'Prato Principal',        'preco_compra' => 7.00,  'preco_venda' => 30.00, 'imagem' => 'tacos-carnitas.jpg'],
    ['nome' => 'Boeuf Bourguignon',        'grupo' => 'Prato Principal',        'preco_compra' => 12.00, 'preco_venda' => 55.00, 'imagem' => 'boeuf-bourguignon.jpg'],
    ['nome' => 'Lasanha de Berinjela (Vegana)', 'grupo' => 'Prato Principal',    'preco_compra' => 5.50,  'preco_venda' => 26.00, 'imagem' => 'lasanha-berinjela.jpg'],

    // Sobremesas
    ['nome' => 'Tiramisu',                  'grupo' => 'Sobremesas',             'preco_compra' => 3.50,  'preco_venda' => 14.00, 'imagem' => 'tiramisu.jpg'],
    ['nome' => 'Pudim de Leite',           'grupo' => 'Sobremesas',             'preco_compra' => 2.50,  'preco_venda' => 10.00, 'imagem' => 'pudim-de-leite.jpg'],
    ['nome' => 'Creme Brûlée',             'grupo' => 'Sobremesas',             'preco_compra' => 3.80,  'preco_venda' => 15.00, 'imagem' => 'creme-brulee.jpg'],

    // Bebidas
    ['nome' => 'Suco de Laranja Natural',  'grupo' => 'Bebidas',                'preco_compra' => 1.20,  'preco_venda' => 7.00,  'imagem' => 'suco-laranja.jpg'],
    ['nome' => 'Agua Mineral',             'grupo' => 'Bebidas',                'preco_compra' => 0.50,  'preco_venda' => 4.00,  'imagem' => 'agua-mineral.jpg'],
    ['nome' => 'Margarita',                'grupo' => 'Bebidas',                'preco_compra' => 4.00,  'preco_venda' => 22.00, 'imagem' => 'margarita.jpg'],
];

// Busca arquivo ignorando maiúsculas/minúsculas (Windows ↔ Linux)
function encontrarArquivo(string $dir, string $nome): ?string
{
    foreach (glob($dir . '/*') as $caminho) {
        if (strcasecmp(basename($caminho), $nome) === 0) {
            return $caminho;
        }
    }
    return null;
}

$inseridos = 0;
$ignorados = 0;
$comImagem = 0;
$semImagem = 0;

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

    $inseridos++;
    $nomeArquivo = $produto['imagem'] ?? null;
    $origemReal  = $nomeArquivo ? encontrarArquivo($dirImagens, $nomeArquivo) : null;

    if ($origemReal !== null) {
        $nomeReal = basename($origemReal);
        $destDir  = $storageDir . DIRECTORY_SEPARATOR . $id;

        if (!is_dir($destDir)) {
            mkdir($destDir, 0777, true);
        }

        copy($origemReal, $destDir . DIRECTORY_SEPARATOR . $nomeReal);

        $conn->executeStatement(
            "UPDATE product SET nome_imagem = ? WHERE id = ?",
            [$nomeReal, $id]
        );

        echo "  🖼️  [{$produto['nome']}] ID={$id} → storage/produtos/{$id}/{$nomeReal}\n";
        $comImagem++;
    } else {
        echo "  ⚠️  [{$produto['nome']}] ID={$id} → sem imagem ('{$nomeArquivo}' não encontrado em public/img/)\n";
        $semImagem++;
    }
}

echo "\n✅ Seed product finalizada.\n";
echo "   ✅ Inseridos   : {$inseridos}\n";
echo "   ⏭️  Já existiam : {$ignorados}\n";
echo "   🖼️  Com imagem : {$comImagem}\n";
echo "   ⚠️  Sem imagem : {$semImagem}\n";