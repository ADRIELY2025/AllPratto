<?php

declare(strict_types=1);

use App\Database\Connection;
use Faker\Factory;

require_once __DIR__ . '/../../../App/bootstrap.php';

$conn  = Connection::get();
$faker = Factory::create('pt_BR');

// Limpa pastas de imagens antigas antes de deletar os produtos
$storageDir = ROOT . '/public/produtos';
if (is_dir($storageDir)) {
    $pastas = glob($storageDir . '/*', GLOB_ONLYDIR);
    foreach ($pastas as $pasta) {
        $arquivos = glob($pasta . '/*');
        foreach ($arquivos as $arquivo) {
            unlink($arquivo);
        }
        rmdir($pasta);
    }
}

$conn->executeStatement('DELETE FROM product');

// ──────────────────────────────────────────────────────────────────────────────
// IMAGENS
// Coloque os arquivos em public/img/ com os nomes definidos em 'imagem' abaixo.
// Se não existir, o produto é inserido sem imagem (sem erro).
// ──────────────────────────────────────────────────────────────────────────────
$dirImagens = ROOT . '/public/img';

$produtos = [
    ['nome' => 'Coca-Cola 350ml',    'grupo' => 'Bebidas',    'preco_compra' => 2.50,  'preco_venda' => 6.00,  'imagem' => 'coca-cola.jpg'],
   
];

$inseridos = 0;
$semImagem = 0;
$comImagem = 0;

foreach ($produtos as $produto) {
    $margem = round(($produto['preco_venda'] - $produto['preco_compra']) / $produto['preco_compra'] * 100, 2);

    $conn->insert('product', [
        'nome'                 => $produto['nome'],
        'codigo_barra'         => $faker->ean13(),
        'grupo'                => $produto['grupo'],
        'unidade'              => 'UN',
        'imagem_url'           => null,
        'nome_imagem'          => null,
        'preco_compra'         => $produto['preco_compra'],
        'total_imposto'        => round($produto['preco_compra'] * 0.10, 4),
        'margem_lucro'         => $margem,
        'custo_operacional'    => round($produto['preco_compra'] * 0.05, 4),
        'valor_venda_sugerido' => $produto['preco_venda'],
        'preco_venda'          => $produto['preco_venda'],
        'tempo_preparo'        => $faker->randomElement(['5 min', '10 min', '15 min', '20 min', '30 min']),
        'descricao'            => $faker->sentence(6),
        'ativo'                => 'true',
        'excluido'             => 'false',
        'criado_em'            => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        'atualizado_em'        => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
    ]);

    $id          = (int) $conn->lastInsertId();
    $nomeArquivo = $produto['imagem'] ?? null;
    $origem      = $dirImagens . DIRECTORY_SEPARATOR . $nomeArquivo;
    $inseridos++;

    if ($nomeArquivo && file_exists($origem)) {
        // ✅ Salva em public/produtos/{id}/ — servido direto pelo Nginx
        $destDir = $storageDir . DIRECTORY_SEPARATOR . $id;

        if (!is_dir($destDir)) {
            mkdir($destDir, 0777, true);
        }

        copy($origem, $destDir . DIRECTORY_SEPARATOR . $nomeArquivo);
        $conn->update('product', ['nome_imagem' => $nomeArquivo], ['id' => $id]);

        echo "  🖼️  [{$produto['nome']}] ID={$id} → public/produtos/{$id}/{$nomeArquivo}\n";
        $comImagem++;
    } else {
        echo "  ⚠️  [{$produto['nome']}] sem imagem\n";
        $semImagem++;
    }
}

echo "\n✅ Seed product: {$inseridos} registros inseridos.\n";
echo "   🖼️  Com imagem : {$comImagem}\n";
echo "   ⚠️  Sem imagem : {$semImagem}\n";