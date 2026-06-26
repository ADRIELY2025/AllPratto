<?php

declare(strict_types=1);

use App\Database\Connection;
use Faker\Factory;

require_once __DIR__ . '/../../../App/bootstrap.php';

$conn  = Connection::get();
$faker = Factory::create('pt_BR');

$conn->executeStatement('DELETE FROM product');

$grupos   = ['Bebidas', 'Lanches', 'Pratos', 'Sobremesas', 'Petiscos', 'Massas', 'Grelhados', 'Saladas'];
$unidades = ['UN', 'KG', 'L', 'CX', 'PC'];

$produtos = [
    ['nome' => 'X-Burguer',         'grupo' => 'Lanches',    'preco_compra' => 8.00,  'preco_venda' => 18.90],
    ['nome' => 'X-Bacon',           'grupo' => 'Lanches',    'preco_compra' => 9.50,  'preco_venda' => 22.90],
    ['nome' => 'Coca-Cola 350ml',   'grupo' => 'Bebidas',    'preco_compra' => 2.50,  'preco_venda' => 6.00],
    ['nome' => 'Suco de Laranja',   'grupo' => 'Bebidas',    'preco_compra' => 3.00,  'preco_venda' => 7.50],
    ['nome' => 'Água Mineral',      'grupo' => 'Bebidas',    'preco_compra' => 0.80,  'preco_venda' => 3.00],
    ['nome' => 'Porcão de Fritas',  'grupo' => 'Petiscos',   'preco_compra' => 4.00,  'preco_venda' => 12.00],
    ['nome' => 'Pastel de Carne',   'grupo' => 'Petiscos',   'preco_compra' => 2.50,  'preco_venda' => 6.50],
    ['nome' => 'Frango Grelhado',   'grupo' => 'Grelhados',  'preco_compra' => 12.00, 'preco_venda' => 29.90],
    ['nome' => 'Picanha 300g',      'grupo' => 'Grelhados',  'preco_compra' => 35.00, 'preco_venda' => 69.90],
    ['nome' => 'Espaguete Bolonhesa','grupo' => 'Massas',    'preco_compra' => 8.00,  'preco_venda' => 24.90],
    ['nome' => 'Lasanha',           'grupo' => 'Massas',     'preco_compra' => 10.00, 'preco_venda' => 28.90],
    ['nome' => 'Salada Caesar',     'grupo' => 'Saladas',    'preco_compra' => 5.00,  'preco_venda' => 16.00],
    ['nome' => 'Petit Gateau',      'grupo' => 'Sobremesas', 'preco_compra' => 6.00,  'preco_venda' => 18.00],
    ['nome' => 'Sorvete 2 Bolas',   'grupo' => 'Sobremesas', 'preco_compra' => 3.50,  'preco_venda' => 9.00],
    ['nome' => 'Prato do Dia',      'grupo' => 'Pratos',     'preco_compra' => 10.00, 'preco_venda' => 22.90],
    ['nome' => 'Feijão Tropeiro',   'grupo' => 'Pratos',     'preco_compra' => 7.00,  'preco_venda' => 19.90],
    ['nome' => 'Cerveja Long Neck', 'grupo' => 'Bebidas',    'preco_compra' => 4.00,  'preco_venda' => 9.90],
    ['nome' => 'Caipirinha',        'grupo' => 'Bebidas',    'preco_compra' => 5.00,  'preco_venda' => 16.00],
    ['nome' => 'Batata Recheada',   'grupo' => 'Petiscos',   'preco_compra' => 6.00,  'preco_venda' => 17.90],
    ['nome' => 'Misto Quente',      'grupo' => 'Lanches',    'preco_compra' => 3.00,  'preco_venda' => 8.50],
];

foreach ($produtos as $produto) {
    $margem = round(($produto['preco_venda'] - $produto['preco_compra']) / $produto['preco_compra'] * 100, 2);

    $conn->insert('product', [
        'nome'                  => $produto['nome'],
        'codigo_barra'          => $faker->ean13(),
        'grupo'                 => $produto['grupo'],
        'unidade'               => 'UN',
        'imagem_url'            => null,
        'preco_compra'          => $produto['preco_compra'],
        'total_imposto'         => round($produto['preco_compra'] * 0.10, 4),
        'margem_lucro'          => $margem,
        'custo_operacional'     => round($produto['preco_compra'] * 0.05, 4),
        'valor_venda_sugerido'  => $produto['preco_venda'],
        'preco_venda'           => $produto['preco_venda'],
        'tempo_preparo'         => $faker->randomElement(['5 min', '10 min', '15 min', '20 min', '30 min']),
        'descricao'             => $faker->sentence(6),
        'ativo'                 => 'true',
        'excluido'              => 'false',
        'criado_em'             => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        'atualizado_em'         => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
    ]);
}

echo '✅ Seed product: ' . count($produtos) . " registros inseridos.\n";