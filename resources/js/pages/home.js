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
//
//  MESAS
//    bar  → faturamento total (R$) por mesa    — BAR
//    pie  → status em tempo real               — DONUT  (cores fixas vindas do PHP)
//
//  CLIENTE
//    bar  → novos clientes por mês             — BAR
//    pie  → top 8 que mais compraram           — DONUT
//
//  PRODUTO
//    bar  → total de vendas em R$ por mês      — BAR
//    pie  → Curva ABC                          — ABC (DONUT + cores A/B/C)

const CHART_CONFIG = {
  mesas: {
    barId: 'chartBarMesas',   pieId: 'chartPieMesas',
    barUrl: '/home/grafico/mesas/bar',   pieUrl: '/home/grafico/mesas/pie',
    barType: 'BAR', pieType: 'DONUT',
  },
  cliente: {
    barId: 'chartBarCliente', pieId: 'chartPieCliente',
    barUrl: '/home/grafico/cliente/bar', pieUrl: '/home/grafico/cliente/pie',
    barType: 'BAR', pieType: 'DONUT',
  },
  produto: {
    barId: 'chartBarProduto', pieId: 'chartPieProduto',
    barUrl: '/home/grafico/produto/bar', pieUrl: '/home/grafico/produto/pie',
    barType: 'BAR', pieType: 'ABC',
  },
};

// Títulos atualizados conforme o conteúdo real de cada gráfico
const CHART_TITLES = {
  mesas:   { bar: 'Faturamento por Mesa (R$)',                pie: 'Status das Mesas — tempo real' },
  cliente: { bar: 'Novos Clientes por Mês',                  pie: 'Clientes que Mais Compraram' },
  produto: { bar: 'Total de Vendas por Mês',                 pie: 'Curva ABC — Participação dos Produtos' },
};

// Guarda quais abas já foram renderizadas (evita re-fetch desnecessário)
const rendered = new Set();

async function loadChartTab(tab) {
  // 1. Esconde todos os painéis
  document.querySelectorAll('.ap-chart-panel').forEach(p => p.classList.remove('active'));

  // 2. Mostra o painel correto
  const panel = document.getElementById(`panel-${tab}`);
  if (!panel) return;
  panel.classList.add('active');

  // 3. Atualiza os títulos no DOM
  const titles = CHART_TITLES[tab];
  if (titles) {
    const titleEls = panel.querySelectorAll('.ap-analytics__chart-title');
    if (titleEls[0]) titleEls[0].textContent = titles.bar;
    if (titleEls[1]) titleEls[1].textContent = titles.pie;
  }

  const cfg = CHART_CONFIG[tab];
  if (!cfg) return;

  if (rendered.has(tab)) {
    // Aba já renderizada: o painel ficou oculto (display:none) e voltou.
    // O ECharts perde as dimensões — força o recalculo.
    await _afterPaint();
    Chart.resize(cfg.barId);
    Chart.resize(cfg.pieId);
    return;
  }

  rendered.add(tab);

  // 4. ← CORREÇÃO PRINCIPAL
  //    Aguarda dois frames de animação para garantir que o browser
  //    já aplicou display:flex ao painel antes do ECharts ler o tamanho.
  await _afterPaint();

  await Promise.all([
    Chart.setId(cfg.barId).getData(cfg.barUrl)[cfg.barType]().render(),
    Chart.setId(cfg.pieId).getData(cfg.pieUrl)[cfg.pieType]().render(),
  ]);
}

// Aguarda o browser terminar de pintar (dois frames garantem layout completo)
function _afterPaint() {
  return new Promise(resolve =>
    requestAnimationFrame(() => requestAnimationFrame(resolve))
  );
}

// ─── Botões de aba ────────────────────────────────────────────────────────────
const tabBtns = document.querySelectorAll('[data-chart-tab]');
tabBtns.forEach(btn => {
  btn.addEventListener('click', (e) => {
    e.preventDefault();
    tabBtns.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    loadChartTab(btn.dataset.chartTab);
  });
});

// Carrega a primeira aba (mesas) ao abrir a página
if (tabBtns.length > 0) {
  tabBtns[0].classList.add('active');
  loadChartTab('mesas');
}

// ─── Destaques da Semana ──────────────────────────────────────────────────────

const EMOJI_CATEGORIA = {
  pizza: '🍕', hamburguer: '🍔', burger: '🍔', massa: '🍝',
  salada: '🥗', sushi: '🍣', frango: '🍗', peixe: '🐟',
  sobremesa: '🍰', bebida: '🥤', default: '🍽️',
};

function _emoji(categoria) {
  if (!categoria) return EMOJI_CATEGORIA.default;
  const k = categoria.toLowerCase();
  for (const [word, e] of Object.entries(EMOJI_CATEGORIA)) {
    if (k.includes(word)) return e;
  }
  return EMOJI_CATEGORIA.default;
}

function _cardDestaque(p) {

    const emoji = _emoji(p.categoria);

    const preco = Number(p.preco || 0).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    });

    // Escolhe o selo
    let badge = '⭐ Destaque';

    if (p.mais_vendido) {
        badge = '🔥 Mais Pedido';
    }

    if (p.novidade) {
        badge = '🆕 Novidade';
    }

    const midia = p.imagem_url
        ? `
            <img
                src="${p.imagem_url}"
                alt="${p.nome}"
                class="ap-highlights__item-img"
                loading="lazy"
                onerror="
                    this.style.display='none';
                    this.nextElementSibling.style.display='flex';
                ">

            <div
                class="ap-highlights__item-emoji"
                style="display:none">

                ${emoji}

            </div>
        `
        : `
            <div class="ap-highlights__item-emoji">

                ${emoji}

            </div>
        `;

    const descricao = p.descricao
        ? `
            <p class="ap-highlights__item-desc">

                ${p.descricao}

            </p>
        `
        : '';

    return `

        <div class="ap-highlights__item">

            <div class="ap-highlights__item-media">

                <span class="ap-highlight-badge">

                    ${badge}

                </span>

                ${midia}

            </div>

            <div class="ap-highlights__item-body">

                <span class="ap-highlights__item-title">

                    ${p.nome}

                </span>

                ${descricao}

                <div class="ap-highlights__item-footer">

                    <span class="ap-highlights__item-price">

                        ${preco}

                    </span>

                    <a
                        href="/cardapio"
                        class="ap-highlights__item-btn">

                        Pedir

                    </a>

                </div>

            </div>

        </div>

    `;

}
async function initDestaques() {
  const container = document.getElementById('destaquesCards');
  if (!container) return;

  try {
    const res  = await fetch('/home/destaques');
    const json = await res.json();

    if (!json.success || !json.data.length) {
      container.innerHTML =
        '<div class="ap-highlights__empty">Nenhum destaque cadastrado ainda.</div>';
      return;
    }

    container.innerHTML = json.data.map(_cardDestaque).join('');

  } catch (e) {
    console.error('[destaques]', e);
    container.innerHTML =
      '<div class="ap-highlights__empty">Não foi possível carregar os destaques.</div>';
  }
}
// ─── Carrossel dos Destaques ────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {

    initDestaques();

    const cards = document.querySelector('.ap-highlights__cards');
    const next  = document.querySelector('.next');
    const prev  = document.querySelector('.prev');

    if (!cards || !next || !prev) {
        return;
    }

    // Botão próximo
    next.addEventListener('click', () => {
        cards.scrollBy({
            left: 320,
            behavior: 'smooth'
        });
    });

    // Botão anterior
    prev.addEventListener('click', () => {
        cards.scrollBy({
            left: -320,
            behavior: 'smooth'
        });
    });

    // Autoplay
    let autoplay = setInterval(moveNext, 5000);

    function moveNext() {

        const fim =
            cards.scrollLeft >=
            (cards.scrollWidth - cards.clientWidth - 10);

        if (fim) {

            cards.scrollTo({
                left: 0,
                behavior: 'smooth'
            });

        } else {

            cards.scrollBy({
                left: 320,
                behavior: 'smooth'
            });

        }
    }

    // Pausa quando o mouse estiver sobre o carrossel
    cards.addEventListener('mouseenter', () => {
        clearInterval(autoplay);
    });

    // Continua quando sair
    cards.addEventListener('mouseleave', () => {
        clearInterval(autoplay);
        autoplay = setInterval(moveNext, 5000);
    });

});