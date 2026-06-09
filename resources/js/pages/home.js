import Chart from '../components/Chart.js';

// ─── Parallax hero ───────────────────────────────────────────────────────────
const heroWrap = document.querySelector('.ap-hero');
const heroImg  = document.querySelector('.ap-hero__img');

if (heroWrap && heroImg && window.matchMedia('(hover: hover)').matches) {
  heroWrap.addEventListener('mousemove', (e) => {
    const { left, top, width, height } = heroWrap.getBoundingClientRect();
    const cx = (e.clientX - left) / width  - 0.5;
    const cy = (e.clientY - top)  / height - 0.5;
    heroImg.style.transform = `scale(1.04) translate(${cx * 8}px, ${cy * 6}px)`;
  });
  heroWrap.addEventListener('mouseleave', () => {
    heroImg.style.transform = 'scale(1) translate(0,0)';
  });
}

// ─── Parallax banner cardápio ─────────────────────────────────────────────────
const banner    = document.querySelector('.ap-menu-banner');
const bannerImg = document.querySelector('.ap-menu-banner__img');

if (banner && bannerImg) {
  window.addEventListener('scroll', () => {
    const { top, height } = banner.getBoundingClientRect();
    const center = top + height / 2 - window.innerHeight / 2;
    const shift  = Math.min(Math.max(center * 0.08, -18), 18);
    bannerImg.style.transform = `translateY(${shift}px)`;
  }, { passive: true });
}

// ─── Gráficos ─────────────────────────────────────────────────────────────────
const CHART_CONFIG = {
  mesas:   { barId: 'chartBarMesas',   pieId: 'chartPieMesas',   barUrl: '/home/grafico/mesas/bar',   pieUrl: '/home/grafico/mesas/pie' },
  cliente: { barId: 'chartBarCliente', pieId: 'chartPieCliente', barUrl: '/home/grafico/cliente/bar', pieUrl: '/home/grafico/cliente/pie' },
  produto: { barId: 'chartBarProduto', pieId: 'chartPieProduto', barUrl: '/home/grafico/produto/bar', pieUrl: '/home/grafico/produto/pie' },
};

const chartInstances = {};

async function loadChartTab(tab) {
  document.querySelectorAll('.ap-chart-panel').forEach(p => p.classList.remove('active'));
  const panel = document.getElementById(`panel-${tab}`);
  if (!panel) return;
  panel.classList.add('active');

  const cfg = CHART_CONFIG[tab];
  if (!cfg || chartInstances[tab]) return;
  chartInstances[tab] = true;

  await Promise.all([
    Chart.setId(cfg.barId).getData(cfg.barUrl).BAR().render(),
    Chart.setId(cfg.pieId).getData(cfg.pieUrl).PIE().render(),
  ]);
}

const tabBtns = document.querySelectorAll('[data-chart-tab]');
tabBtns.forEach(btn => {
  btn.addEventListener('click', (e) => {
    e.preventDefault();
    tabBtns.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    loadChartTab(btn.dataset.chartTab);
  });
});

if (tabBtns.length > 0) {
  tabBtns[0].classList.add('active');
  loadChartTab('mesas');
}
