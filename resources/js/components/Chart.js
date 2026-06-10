const _instances = {};   // { elementId: echartsInstance }

class Chart {
  setId(id)    { this._id   = id;      return this; }
  getData(url) { this._url  = url;     return this; }
  BAR()        { this._type = 'bar';   return this; }
  PIE()        { this._type = 'pie';   return this; }
  DONUT()      { this._type = 'donut'; return this; }
  ABC()        { this._type = 'abc';   return this; }
  LINE()       { this._type = 'line';  return this; }

  // ── Renderiza no DOM ──────────────────────────────────────────────────────
  async render() {
    const data = await fetch(this._url).then(r => r.json());
    const el   = document.getElementById(this._id);
    if (!el) return;

    // Se já foi inicializado antes (troca de aba), só atualiza os dados
    if (_instances[this._id]) {
      _instances[this._id].setOption(this._buildOption(data));
      return;
    }

    const instance = window.echarts.init(el);
    instance.setOption(this._buildOption(data));
    _instances[this._id] = instance;

    window.addEventListener('resize', () => instance.resize(), { passive: true });
  }

  // ── Força o ECharts a recalcular o tamanho do container ──────────────────
  //  Chamar após tornar o painel visível (display:flex/block).
  //  Uso: Chart.resize('chartBarMesas')
  static resize(id) {
    if (_instances[id]) _instances[id].resize();
  }

  // ── Retorna base64 PNG para PDF ───────────────────────────────────────────
  async toPng(width = 800, height = 380) {
    const data = await fetch(this._url).then(r => r.json());
    const div  = document.createElement('div');
    div.style.cssText = `position:fixed;left:-9999px;top:-9999px;width:${width}px;height:${height}px`;
    document.body.appendChild(div);

    const instance = window.echarts.init(div, null, { width, height });
    instance.setOption(this._buildOption(data));
    await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));

    const png = instance.getDataURL({ type: 'png', pixelRatio: 2, backgroundColor: '#fff' });
    instance.dispose();
    document.body.removeChild(div);
    return png;
  }

  // ── Monta option do ECharts conforme o tipo ───────────────────────────────
  _buildOption(data) {
    switch (this._type) {
      case 'bar':   return _buildBar(data, 'bar');
      case 'line':  return _buildBar(data, 'line');
      case 'pie':
      case 'donut':
      case 'abc':   return _buildPie(data);
      default:      return _buildBar(data, 'bar');
    }
  }
}

// ─── Builders ────────────────────────────────────────────────────────────────

function _buildBar(data, seriesType = 'bar') {
  const isMonetary = Array.isArray(data.values) && data.values.some(v => v >= 100);
  return {
    tooltip: {
      trigger: 'axis',
      formatter: params => {
        const p   = params[0];
        const val = isMonetary
          ? 'R$ ' + Number(p.value).toLocaleString('pt-BR', { minimumFractionDigits: 2 })
          : p.value;
        return `${p.name}<br/><b>${val}</b>`;
      }
    },
    grid: { left: '3%', right: '4%', bottom: '14%', containLabel: true },
    xAxis: {
      data: data.categories ?? [],
      axisLabel: { rotate: (data.categories?.length ?? 0) > 6 ? 30 : 0, fontSize: 11 }
    },
    yAxis: {
      axisLabel: {
        formatter: v => isMonetary
          ? (v >= 1000 ? 'R$' + (v / 1000).toFixed(0) + 'k' : 'R$' + v)
          : v
      }
    },
    series: [{
      type: seriesType,
      data: data.values ?? [],
      barMaxWidth: 48,
      smooth: true,
      itemStyle: {
        borderRadius: seriesType === 'bar' ? [4, 4, 0, 0] : 0,
        color: '#378ADD'
      },
      areaStyle: seriesType === 'line' ? { color: 'rgba(55,138,221,0.12)' } : undefined,
    }]
  };
}

function _buildPie(data) {
  return {
    tooltip: {
      trigger: 'item',
      formatter: p => {
        const val = p.value >= 100
          ? 'R$ ' + Number(p.value).toLocaleString('pt-BR', { minimumFractionDigits: 2 })
          : p.value;
        return `${p.name}<br/><b>${val}</b> (${p.percent.toFixed(1)}%)`;
      }
    },
    legend: {
      orient: 'horizontal',
      bottom: 0,
      itemWidth: 10,
      itemHeight: 10,
      textStyle: { fontSize: 11 },
    },
    series: [{
      type: 'pie',
      radius: ['45%', '70%'],
      center: ['50%', '44%'],
      data: data.series ?? [],
      label: { show: false },
      emphasis: { itemStyle: { shadowBlur: 6, shadowColor: 'rgba(0,0,0,0.2)' } },
    }]
  };
}

// ─── Export via Proxy (interface fluente de fábrica) ─────────────────────────
export default new Proxy(Chart, {
  get(target, prop) {
    // Métodos estáticos (ex: Chart.resize) passam direto
    if (prop in target) return target[prop].bind(target);
    // Métodos de instância criam nova instância
    return (...args) => new target()[prop](...args);
  }
});
