<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\DB;

final class Home extends Base
{
    public function home($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('home'), [
                'titulo' => 'Início',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    // ─────────────────────────────────────────────────────────────
    //  Relatório 1 — Vendas totais por mês, agrupadas por ano
    //  Consulta a VIEW: vw_vendas_por_mes
    //  Retorna: { meses, anos, series: [{ ano, values }] }
    // ─────────────────────────────────────────────────────────────
    public function vendasPorMes($request, $response)
    {
        $rows = DB::getConnection()
            ->executeQuery('SELECT ano, mes, label, total_vendas FROM vw_vendas_por_mes')
            ->fetchAllAssociative();

        // Monta estrutura agrupada por ano para séries múltiplas no gráfico
        $anos   = [];
        $mesesLabels = [];
        $porAno = [];

        foreach ($rows as $r) {
            $ano   = (int)$r['ano'];
            $mes   = (int)$r['mes'];
            $label = $r['label'];
            $total = (float)$r['total_vendas'];

            if (!in_array($ano, $anos, true)) {
                $anos[] = $ano;
            }
            if (!in_array($label, $mesesLabels, true)) {
                $mesesLabels[$mes] = $label;
            }
            $porAno[$ano][$mes] = $total;
        }

        ksort($mesesLabels);
        sort($anos);

        // Garante que todos os meses existam para cada ano (0 se não houver venda)
        $series = [];
        foreach ($anos as $ano) {
            $values = [];
            foreach (array_keys($mesesLabels) as $mes) {
                $values[] = $porAno[$ano][$mes] ?? 0;
            }
            $series[] = ['ano' => $ano, 'values' => $values];
        }

        return $this->json($response, [
            'meses'  => array_values($mesesLabels),
            'anos'   => $anos,
            'series' => $series,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Relatório 2 — Curva ABC dos produtos mais vendidos
    //  Consulta a VIEW: vw_curva_abc
    //  Retorna: { series: [{ name, value, classe }] }
    // ─────────────────────────────────────────────────────────────
    public function curvaAbc($request, $response)
    {
        $rows = DB::getConnection()
            ->executeQuery('SELECT produto, receita, pct, classe_abc FROM vw_curva_abc')
            ->fetchAllAssociative();

        return $this->json($response, [
            'series' => array_map(fn($r) => [
                'name'   => $r['produto'],
                'value'  => (float)$r['receita'],
                'pct'    => (float)$r['pct'],
                'classe' => $r['classe_abc'],
            ], $rows),
        ]);
    }

    // ── Endpoints antigos mantidos para compatibilidade ──────────

    public function mesaMaisPedida($request, $response)
    {
        $rows = DB::select('mesa', 'COUNT(*) as total')
            ->from('"order"')
            ->groupBy('mesa')
            ->orderBy('total', 'DESC')
            ->setMaxResults(10)
            ->fetchAllAssociative();

        return $this->json($response, [
            'categories' => array_map(fn($r) => 'Mesa ' . $r['mesa'], $rows),
            'values'     => array_map(fn($r) => (int)$r['total'], $rows),
        ]);
    }

    public function produtoMaisMenos($request, $response)
    {
        $rows = DB::select('oi.nome', 'SUM(oi.quantidade) as total')
            ->from('order_item', 'oi')
            ->groupBy('oi.nome')
            ->orderBy('total', 'DESC')
            ->setMaxResults(10)
            ->fetchAllAssociative();

        return $this->json($response, [
            'categories' => array_map(fn($r) => $r['nome'], $rows),
            'values'     => array_map(fn($r) => (int)$r['total'], $rows),
        ]);
    }

    public function clienteMaisCompra($request, $response)
    {
        $rows = DB::select('c.nome', 'SUM(s.total_liquido) as total')
            ->from('sale', 's')
            ->join('s', 'customer', 'c', 's.id_cliente = c.id')
            ->groupBy('c.nome')
            ->orderBy('total', 'DESC')
            ->setMaxResults(8)
            ->fetchAllAssociative();

        return $this->json($response, [
            'series' => array_map(fn($r) => [
                'name'  => $r['nome'],
                'value' => (float)$r['total'],
            ], $rows),
        ]);
    }
}
