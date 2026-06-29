<?php

declare(strict_types=1);

use App\Database\Connection;
use Faker\Factory;

require_once __DIR__ . '/../../../App/bootstrap.php';

$conn  = Connection::get();
$faker = Factory::create('pt_BR');

$conn->executeStatement('DELETE FROM product');

// ──────────────────────────────────────────────────────────────────────────────
// IMAGENS
// Coloque arquivos em public/img com o mesmo nome definido em 'imagem' abaixo.
// Formatos aceitos: jpg, jpeg, png, webp
// Se o arquivo não existir, o produto é inserido sem imagem (sem erro).
// ──────────────────────────────────────────────────────────────────────────────
$dirImagens = ROOT . '/public/img';   // C:\projeto\AllPratto\public\img

$produtos = [
    ['nome' => 'X-Burguer',          'grupo' => 'Lanches',    'preco_compra' => 8.00,  'preco_venda' => 18.90, 'imagem' => 'x-burguer.jpg'],
    ['nome' => 'X-Bacon',            'grupo' => 'Lanches',    'preco_compra' => 9.50,  'preco_venda' => 22.90, 'imagem' => 'x-bacon.jpg'],
    ['nome' => 'Coca-Cola 350ml',    'grupo' => 'Bebidas',    'preco_compra' => 2.50,  'preco_venda' => 6.00,  'imagem' => 'coca-cola.jpg'],
    ['nome' => 'Suco de Laranja',    'grupo' => 'Bebidas',    'preco_compra' => 3.00,  'preco_venda' => 7.50,  'imagem' => 'suco-laranja.jpg'],
    ['nome' => 'Água Mineral',       'grupo' => 'Bebidas',    'preco_compra' => 0.80,  'preco_venda' => 3.00,  'imagem' => 'agua-mineral.jpg'],
    ['nome' => 'Porção de Fritas',   'grupo' => 'Petiscos',   'preco_compra' => 4.00,  'preco_venda' => 12.00, 'imagem' => 'porcao-fritas.jpg'],
    ['nome' => 'Pastel de Carne',    'grupo' => 'Petiscos',   'preco_compra' => 2.50,  'preco_venda' => 6.50,  'imagem' => 'pastel-carne.jpg'],
    ['nome' => 'Frango Grelhado',    'grupo' => 'Grelhados',  'preco_compra' => 12.00, 'preco_venda' => 29.90, 'imagem' => 'frango-grelhado.jpg'],
    ['nome' => 'Picanha 300g',       'grupo' => 'Grelhados',  'preco_compra' => 35.00, 'preco_venda' => 69.90, 'imagem' => 'picanha.jpg'],
    ['nome' => 'Espaguete Bolonhesa','grupo' => 'Massas',     'preco_compra' => 8.00,  'preco_venda' => 24.90, 'imagem' => 'espaguete-bolonhesa.jpg'],
    ['nome' => 'Lasanha',            'grupo' => 'Massas',     'preco_compra' => 10.00, 'preco_venda' => 28.90, 'imagem' => 'lasanha.jpg'],
    ['nome' => 'Salada Caesar',      'grupo' => 'Saladas',    'preco_compra' => 5.00,  'preco_venda' => 16.00, 'imagem' => 'salada-caesar.jpg'],
    ['nome' => 'Petit Gateau',       'grupo' => 'Sobremesas', 'preco_compra' => 6.00,  'preco_venda' => 18.00, 'imagem' => 'petit-gateau.jpg'],
    ['nome' => 'Sorvete 2 Bolas',    'grupo' => 'Sobremesas', 'preco_compra' => 3.50,  'preco_venda' => 9.00,  'imagem' => 'sorvete.jpg'],
    ['nome' => 'Prato do Dia',       'grupo' => 'Pratos',     'preco_compra' => 10.00, 'preco_venda' => 22.90, 'imagem' => 'prato-do-dia.jpg'],
    ['nome' => 'Feijão Tropeiro',    'grupo' => 'Pratos',     'preco_compra' => 7.00,  'preco_venda' => 19.90, 'imagem' => 'feijao-tropeiro.jpg'],
    ['nome' => 'Cerveja Long Neck',  'grupo' => 'Bebidas',    'preco_compra' => 4.00,  'preco_venda' => 9.90,  'imagem' => 'cerveja-long-neck.jpg'],
    ['nome' => 'Caipirinha',         'grupo' => 'Bebidas',    'preco_compra' => 5.00,  'preco_venda' => 16.00, 'imagem' => 'caipirinha.jpg'],
    ['nome' => 'Batata Recheada',    'grupo' => 'Petiscos',   'preco_compra' => 6.00,  'preco_venda' => 17.90, 'imagem' => 'batata-recheada.jpg'],
    ['nome' => 'Misto Quente',       'grupo' => 'Lanches',    'preco_compra' => 3.00,  'preco_venda' => 8.50,  'imagem' => 'misto-quente.jpg'],
];

$inseridos   = 0;
$semImagem   = 0;
$comImagem   = 0;

foreach ($produtos as $produto) {
    $margem = round(($produto['preco_venda'] - $produto['preco_compra']) / $produto['preco_compra'] * 100, 2);

    // Insere o produto sem imagem primeiro para obter o ID
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

    $id = (int) $conn->lastInsertId();
    $inseridos++;

    // Tenta copiar a imagem de public/img para storage/produtos/{id}/
    $nomeArquivo = $produto['imagem'] ?? null;
    $origem      = $dirImagens . DIRECTORY_SEPARATOR . $nomeArquivo;

    if ($nomeArquivo && file_exists($origem)) {
        $destDir = ROOT . '/storage/produtos/' . $id;

        if (!is_dir($destDir)) {
            mkdir($destDir, 0777, true);
        }

        // Mantém o mesmo nome do arquivo original
        $destino = $destDir . DIRECTORY_SEPARATOR . $nomeArquivo;
        copy($origem, $destino);

        // Atualiza nome_imagem no banco
        $conn->update('product', ['nome_imagem' => $nomeArquivo], ['id' => $id]);

        echo "  🖼️  [{$produto['nome']}] imagem copiada → storage/produtos/{$id}/{$nomeArquivo}\n";
        $comImagem++;
    } else {
        echo "  ⚠️  [{$produto['nome']}] sem imagem (arquivo não encontrado: public/img/{$nomeArquivo})\n";
        $semImagem++;
    }
}

echo "\n✅ Seed product: {$inseridos} registros inseridos.\n";
echo "   🖼️  Com imagem : {$comImagem}\n";
echo "   ⚠️  Sem imagem : {$semImagem}\n";