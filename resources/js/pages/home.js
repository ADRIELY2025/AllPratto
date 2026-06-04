import Chart from '../components/Chart.js';

// ── Relatório 1: Total de vendas por mês, organizado por ano (barras) ──
Chart.setId('graficoVendasMes').getData('/home/vendas-por-mes').BAR().render();

// ── Relatório 2: Curva ABC dos produtos mais vendidos (pizza interativa) ──
Chart.setId('graficoCurvaAbc').getData('/home/curva-abc').PIE().render();
