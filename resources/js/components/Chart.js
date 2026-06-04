// Classe que constrói e renderiza gráficos usando ECharts
class Chart {
  setId(id)    { this._id   = id;    return this; } // define o id do elemento HTML
  getData(url) { this._url  = url;   return this; } // define a URL da API
  BAR()        { this._type = 'bar'; return this; } // define o tipo como barra
  PIE()        { this._type = 'pie'; return this; } // define o tipo como pizza

  // busca os dados da API e renderiza o gráfico
  async render() {
    const data  = await fetch(this._url).then(r => r.json());
    const el    = document.getElementById(this._id);
    const chart = window.echarts.init(el);
    chart.setOption(this._type === 'pie' ? buildPie(data) : buildBar(data));

    // ── Responsividade: redimensiona com a janela ──────────────
    window.addEventListener('resize', () => chart.resize());
  }
}

// ── Gráfico de Barras — Vendas por Mês/Ano ────────────────────
// API deve retornar:
//   { anos: [2024, 2025], meses: ['Jan','Fev',...], series: [{ ano: 2024, values: [...] }] }
function buildBar(data) {
  const series = (data.series || []).map(s => ({
    name: String(s.ano),
    type: 'bar',
    data: s.values,
    barMaxWidth: 40,
    emphasis: { focus: 'series' },
    label: { show: false },
  }));

  return {
    tooltip: {
      trigger: 'axis',
      axisPointer: { type: 'shadow' },
      formatter(params) {
        let out = `<b>${params[0].axisValue}</b><br/>`;
        params.forEach(p => {
          const val = Number(p.value).toLocaleString('pt-BR', {
            style: 'currency', currency: 'BRL'
          });
          out += `${p.marker} ${p.seriesName}: ${val}<br/>`;
        });
        return out;
      },
    },
    legend: { top: 8 },
    grid: { left: '3%', right: '4%', bottom: 40, containLabel: true },
    xAxis: {
      type: 'category',
      data: data.meses || [],
      axisLabel: { rotate: 30 },
    },
    yAxis: {
      type: 'value',
      axisLabel: {
        formatter: v => 'R$ ' + (v / 1000).toFixed(0) + 'k',
      },
    },
    series,
  };
}

// ── Gráfico de Pizza — Curva ABC dos Produtos ─────────────────
// API deve retornar:
//   { series: [{ name: 'Produto X', value: 1234.56, classe: 'A' }] }
function buildPie(data) {
  const COLORS = { A: '#ef4444', B: '#f59e0b', C: '#3b82f6' };

  const seriesData = (data.series || []).map(item => ({
    name : item.name,
    value: item.value,
    classe: item.classe,
    itemStyle: { color: COLORS[item.classe] || '#6b7280' },
  }));

  return {
    tooltip: {
      trigger: 'item',
      formatter(p) {
        const val = Number(p.value).toLocaleString('pt-BR', {
          style: 'currency', currency: 'BRL'
        });
        return `${p.marker} <b>${p.name}</b><br/>
                Classe: <b>${p.data.classe}</b><br/>
                Receita: ${val} (${p.percent}%)`;
      },
    },
    legend: {
      orient: 'vertical',
      right: 10,
      top: 'center',
      formatter: name => {
        const item = seriesData.find(s => s.name === name);
        return item ? `[${item.classe}] ${name}` : name;
      },
    },
    series: [{
      type      : 'pie',
      radius    : ['35%', '65%'],   // rosca
      center    : ['40%', '50%'],
      data      : seriesData,
      emphasis  : {
        itemStyle: { shadowBlur: 10, shadowOffsetX: 0, shadowColor: 'rgba(0,0,0,0.5)' },
      },
      label: {
        formatter: '{b}\n{d}%',
        fontSize : 11,
      },
    }],
  };
}

// Proxy que cria uma nova instância a cada chamada de método estático
export default new Proxy(Chart, {
  get(target, prop) {
    return (...args) => new target()[prop](...args);
  }
});
