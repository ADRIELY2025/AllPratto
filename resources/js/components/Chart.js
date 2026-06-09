class Chart {
  setId(id)    { this._id   = id;    return this; }
  getData(url) { this._url  = url;   return this; }
  BAR()        { this._type = 'bar'; return this; }
  PIE()        { this._type = 'pie'; return this; }

  async render() {
    const data  = await fetch(this._url).then(r => r.json());
    const chart = window.echarts.init(document.getElementById(this._id));
    chart.setOption(this._type === 'pie' ? buildPie(data) : buildBar(data));
  }
}

function buildBar(data) {
  return {
    tooltip: {},
    xAxis: { data: data.categories },
    yAxis: {},
    series: [{ type: 'bar', data: data.values }]
  };
}

function buildPie(data) {
  return {
    tooltip: { trigger: 'item' },
    series: [{ type: 'pie', radius: ['40%', '70%'], data: data.series }]
  };
}

export default new Proxy(Chart, {
  get(target, prop) {
    return (...args) => new target()[prop](...args);
  }
});
