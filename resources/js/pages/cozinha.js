import '../../css/cozinha.css';
import Requests from '../components/requests.js';

// ─── CONFIGURAÇÃO ────────────────────────────────────────────────
const POLL_INTERVAL = 8000; // 8 s — intervalo de atualização automática

// ─── REFERÊNCIAS DOM ─────────────────────────────────────────────
const grid         = document.getElementById('cz-grid');
const badgePedidos = document.getElementById('cz-badge-pedidos');
const badgePreparo = document.getElementById('cz-badge-preparo');
const badgeProntos = document.getElementById('cz-badge-prontos');

// ─── ESTADO LOCAL ────────────────────────────────────────────────
// Guarda os IDs já renderizados para não recriar cards que já existem
const renderizados = new Set();

// ─── HELPERS ─────────────────────────────────────────────────────

/** Retorna quantos minutos passaram desde `criado_em` (string ISO/Postgres) */
function minutosDesde(criadoEm) {
    const diff = Date.now() - new Date(criadoEm).getTime();
    return Math.floor(diff / 60000);
}

/** Mapeia status do banco para classe CSS e texto legível */
function infoStatus(status) {
    const mapa = {
        pendente:   { classe: 'cz-status--novo',    texto: 'NOVO' },
        em_preparo: { classe: 'cz-status--preparo',  texto: 'EM PREPARO' },
        pronto:     { classe: 'cz-status--pronto',   texto: 'PRONTO' },
    };
    return mapa[status] ?? { classe: 'cz-status--novo', texto: status.toUpperCase() };
}

/** Cria o HTML de um card de pedido */
function criarCardHTML(pedido) {
    const { classe, texto } = infoStatus(pedido.status);
    const min   = minutosDesde(pedido.criado_em);
    const mesa  = pedido.mesa_numero ? `Mesa ${pedido.mesa_numero}` : 'Delivery';
    const num   = String(pedido.id).padStart(3, '0');

    const itensHTML = (pedido.itens ?? []).map(item => `
        <div class="cz-item">
            <div class="cz-qtd">${item.quantidade}</div>
            <div class="cz-nome-prato">${item.nome}</div>
        </div>
    `).join('');

    const obsHTML = pedido.observacao
        ? `<div class="cz-obs"><strong>Observação:</strong> ${pedido.observacao}</div>`
        : '';

    return `
        <article class="cz-card" data-id="${pedido.id}">
            <div class="cz-card__topo">
                <div class="cz-card__numero">Pedido nº ${num}</div>
                <div class="cz-card__mesa">${mesa}</div>
                <div class="cz-card__info">
                    <div class="cz-status ${classe}">${texto}</div>
                    <div class="cz-tempo">${min} min</div>
                </div>
            </div>

            <div class="cz-itens">
                <h4>Itens do Pedido</h4>
                ${itensHTML}
                ${obsHTML}
            </div>

            <div class="cz-acoes">
                <button class="cz-btn cz-btn--imprimir" data-acao="imprimir">
                    🖨 Imprimir
                </button>
                <button class="cz-btn cz-btn--pronto" data-acao="pronto">
                    ✔ Pedido Pronto
                </button>
            </div>
        </article>
    `;
}

/** Atualiza os badges do header com os contadores reais */
function atualizarBadges(pedidos) {
    const total   = pedidos.length;
    const preparo = pedidos.filter(p => p.status === 'em_preparo').length;
    const prontos = pedidos.filter(p => p.status === 'pronto').length;

    badgePedidos.textContent = `${total} Pedido${total !== 1 ? 's' : ''}`;
    badgePreparo.textContent = `${preparo} Em preparo`;
    badgeProntos.textContent = `${prontos} Pronto${prontos !== 1 ? 's' : ''}`;
}

/**
 * Renderiza apenas os pedidos que ainda não estão no DOM.
 * Pedidos da mesma mesa chegam em ordem cronológica (API ordena por criado_em ASC),
 * portanto são inseridos na sequência natural — novos ficam abaixo dos anteriores.
 */
function renderizarPedidos(pedidos) {
    if (pedidos.length === 0) {
        grid.innerHTML = `
            <div class="cz-vazio">
                <span>🍽️</span>
                Nenhum pedido pendente no momento.
            </div>
        `;
        renderizados.clear();
        return;
    }

    // Remove o estado vazio se existir
    const vazio = grid.querySelector('.cz-vazio');
    if (vazio) vazio.remove();

    // IDs que chegaram nesta rodada
    const idsAtuais = new Set(pedidos.map(p => String(p.id)));

    // Remove cards de pedidos que saíram da fila (marcados como prontos/entregues)
    grid.querySelectorAll('.cz-card').forEach(card => {
        if (!idsAtuais.has(card.dataset.id)) {
            card.remove();
            renderizados.delete(card.dataset.id);
        }
    });

    // Adiciona apenas cards ainda não renderizados
    pedidos.forEach(pedido => {
        const idStr = String(pedido.id);
        if (renderizados.has(idStr)) return;

        grid.insertAdjacentHTML('beforeend', criarCardHTML(pedido));
        renderizados.add(idStr);
    });

    atualizarBadges(pedidos);
}

// ─── AÇÕES DOS CARDS (delegação de evento) ───────────────────────

grid.addEventListener('click', async e => {
    const btn   = e.target.closest('[data-acao]');
    if (!btn) return;

    const card  = btn.closest('.cz-card');
    const id    = card?.dataset.id;
    const acao  = btn.dataset.acao;

    if (acao === 'imprimir') {
        document.querySelectorAll('.cz-card').forEach(c => c.classList.remove('cz-card--imprimindo'));
        card.classList.add('cz-card--imprimindo');
        window.print();
        return;
    }

    if (acao === 'pronto') {
        btn.disabled = true;

        try {
            const fd = new FormData();
            fd.append('id', id);
            fd.append('status', 'pronto');
            const req = new Requests();
            const res = await req.setBody(fd).post('/pedido/update-status');

            if (res.status) {
                card.remove();
                renderizados.delete(String(id));
                // Dispara nova leitura imediata para atualizar badges
                await buscarPedidos();
            } else {
                alert(res.msg ?? 'Erro ao marcar pedido como pronto.');
                btn.disabled = false;
            }
        } catch (err) {
            alert('Erro de comunicação: ' + err.message);
            btn.disabled = false;
        }
    }
});

// ─── POLLING ─────────────────────────────────────────────────────

async function buscarPedidos() {
    try {
        const req  = new Requests();
        const data = await req.get('/pedido/cozinha/listar');

        if (data.status && Array.isArray(data.pedidos)) {
            renderizarPedidos(data.pedidos);
        }
    } catch (err) {
        // Falha silenciosa — não interrompe o painel
        console.warn('[Cozinha] Falha ao buscar pedidos:', err.message);
    }
}

// Carrega imediatamente e depois a cada POLL_INTERVAL
buscarPedidos();
setInterval(buscarPedidos, POLL_INTERVAL);