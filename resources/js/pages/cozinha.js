import '../../css/cozinha.css';
import Requests from '../components/requests.js';

// ─── CONFIGURAÇÃO ────────────────────────────────────────────────
const POLL_INTERVAL = 8000;

// ─── REFERÊNCIAS DOM ─────────────────────────────────────────────
const grid           = document.getElementById('cz-grid');
const badgePendentes = document.getElementById('cz-badge-pendentes');
const badgePreparo   = document.getElementById('cz-badge-preparo');
const badgeProntos   = document.getElementById('cz-badge-prontos');
const printArea      = document.getElementById('cz-print-area');

// ─── ESTADO LOCAL ────────────────────────────────────────────────
const renderizados = new Set();

// ─── HELPERS ─────────────────────────────────────────────────────

/**
 * Calcula minutos desde criado_em.
 * O Postgres retorna strings como "2026-06-25 19:27:00" sem timezone.
 * Substituímos o espaço por 'T' e adicionamos 'Z' para garantir UTC,
 * mas se o servidor já estiver no fuso local, usamos sem 'Z'.
 * A forma mais segura: tentar com Z, se resultar em futuro, tentar sem.
 */
function minutosDesde(criadoEm) {
    if (!criadoEm) return 0;

    // Normaliza "2026-06-25 19:27:00" → "2026-06-25T19:27:00"
    const normalizado = criadoEm.toString().replace(' ', 'T');

    let data = new Date(normalizado);

    // Se a data for inválida, retorna 0
    if (isNaN(data.getTime())) return 0;

    const diff = Date.now() - data.getTime();

    // Se o diff for negativo (data no futuro), provavelmente é fuso local
    // Tenta interpretar como UTC adicionando Z
    if (diff < 0 && !normalizado.endsWith('Z')) {
        data = new Date(normalizado + 'Z');
        const diffZ = Date.now() - data.getTime();
        if (diffZ >= 0) return Math.floor(diffZ / 60000);
        return 0;
    }

    return Math.max(0, Math.floor(diff / 60000));
}

function infoStatus(status) {
    const mapa = {
        pendente:   { classe: 'cz-status--novo',    texto: 'NOVO',       dataBefore: 'pendente' },
        em_preparo: { classe: 'cz-status--preparo', texto: 'EM PREPARO', dataBefore: 'em_preparo' },
        pronto:     { classe: 'cz-status--pronto',  texto: 'PRONTO',     dataBefore: 'pronto' },
    };
    return mapa[status] ?? { classe: 'cz-status--novo', texto: status.toUpperCase(), dataBefore: 'pendente' };
}

function iconeCard(itens) {
    if (!itens || itens.length === 0) return '🍽️';
    const nomes = itens.map(i => (i.nome || '').toLowerCase()).join(' ');
    if (nomes.match(/pizza/))                           return '🍕';
    if (nomes.match(/hambur|burger|x-bacon|x-burguer/)) return '🍔';
    if (nomes.match(/frango|chicken/))                  return '🍗';
    if (nomes.match(/suco|refri|bebida|lata|[áa]gua/)) return '🥤';
    if (nomes.match(/sobremesa|torta|sorvete|pudim/))   return '🍰';
    if (nomes.match(/por[çc][ãa]o|batata|frit/))        return '🍟';
    return '🍽️';
}

function criarCardHTML(pedido) {
    const { classe, texto, dataBefore } = infoStatus(pedido.status);
    const min    = minutosDesde(pedido.criado_em);
    const mesa   = pedido.mesa_numero ? `Mesa ${pedido.mesa_numero}` : 'Delivery';
    const num    = String(pedido.id).padStart(3, '0');
    const icone  = iconeCard(pedido.itens);
    const urgente = min > 20;

    const itensHTML = (pedido.itens ?? []).map(item => `
        <div class="cz-item">
            <div class="cz-qtd">${item.quantidade}</div>
            <div class="cz-nome-prato">${item.nome}</div>
        </div>
    `).join('');

    const obsHTML = pedido.observacao
        ? `<div class="cz-obs">⚠️ <strong>Obs:</strong> ${pedido.observacao}</div>`
        : '';

    // Botão de ação muda conforme status atual
    const btnProximo = pedido.status === 'pendente'
        ? `<button class="cz-btn cz-btn--acao" data-acao="preparo">🔥 Iniciar Preparo</button>`
        : `<button class="cz-btn cz-btn--pronto" data-acao="pronto">✔ Pedido Pronto</button>`;

    return `
        <article class="cz-card" data-id="${pedido.id}" data-status="${pedido.status ?? 'pendente'}">
            <div class="cz-card__topo">
                <div class="cz-topo-icone" aria-hidden="true">${icone}</div>
                <div class="cz-card__numero">Pedido nº ${num}</div>
                <div class="cz-card__mesa">🪑 ${mesa}</div>
                <div class="cz-card__info">
                    <div class="cz-status ${classe}">${texto}</div>
                    <div class="cz-tempo${urgente ? ' cz-tempo--urgente' : ''}">
                        ${urgente ? '🔥' : '⏱'} ${min} min
                    </div>
                </div>
            </div>

            <div class="cz-itens">
                <div class="cz-itens__titulo">Itens do Pedido</div>
                ${itensHTML}
                ${obsHTML}
            </div>

            <div class="cz-divisor"></div>

            <div class="cz-acoes">
                <button class="cz-btn cz-btn--imprimir" data-acao="imprimir">
                    🖨️ Imprimir
                </button>
                ${btnProximo}
            </div>
        </article>
    `;
}

// ─── HTML DE IMPRESSÃO (limpo, sem o estilo do painel) ────────────

function htmlParaImprimir(pedido) {
    const mesa = pedido.mesa_numero ? `Mesa ${pedido.mesa_numero}` : 'Delivery';
    const num  = String(pedido.id).padStart(3, '0');
    const agora = new Date().toLocaleString('pt-BR');

    const itensHTML = (pedido.itens ?? []).map(item => `
        <tr>
            <td style="padding:6px 0;font-size:14px;">${item.quantidade}x</td>
            <td style="padding:6px 0;font-size:14px;">${item.nome}</td>
            <td style="padding:6px 0;font-size:14px;text-align:right;">R$ ${Number(item.subtotal ?? 0).toFixed(2)}</td>
        </tr>
    `).join('');

    const obsHTML = pedido.observacao
        ? `<div style="margin-top:12px;padding:10px;background:#fff8e1;border-left:4px solid #f59e0b;border-radius:4px;font-size:13px;">
               <strong>Observação:</strong> ${pedido.observacao}
           </div>`
        : '';

    return `
        <div style="font-family:Arial,sans-serif;max-width:380px;margin:0 auto;padding:20px;">
            <div style="text-align:center;border-bottom:2px solid #333;padding-bottom:12px;margin-bottom:16px;">
                <div style="font-size:11px;color:#666;">${agora} &nbsp;|&nbsp; Painel da Cozinha</div>
                <div style="font-size:22px;font-weight:bold;margin-top:6px;">PEDIDO Nº ${num}</div>
                <div style="font-size:14px;margin-top:4px;">🪑 ${mesa}</div>
            </div>

            <div style="margin-bottom:16px;">
                <div style="font-size:11px;font-weight:bold;letter-spacing:1.5px;color:#666;text-transform:uppercase;margin-bottom:8px;border-bottom:1px solid #eee;padding-bottom:6px;">Itens do Pedido</div>
                <table style="width:100%;border-collapse:collapse;">
                    ${itensHTML}
                    <tr style="border-top:1px solid #ddd;">
                        <td colspan="2" style="padding:8px 0;font-weight:bold;font-size:14px;">Total</td>
                        <td style="padding:8px 0;font-weight:bold;font-size:14px;text-align:right;">R$ ${Number(pedido.total ?? 0).toFixed(2)}</td>
                    </tr>
                </table>
            </div>

            ${obsHTML}

            <div style="text-align:center;margin-top:20px;font-size:11px;color:#999;border-top:1px dashed #ccc;padding-top:12px;">
                AllPratto — Gestão de Pedidos
            </div>
        </div>
    `;
}

// ─── BADGES ──────────────────────────────────────────────────────

function atualizarBadges(pedidos) {
    const pendentes = pedidos.filter(p => p.status === 'pendente').length;
    const preparo   = pedidos.filter(p => p.status === 'em_preparo').length;
    const prontos   = pedidos.filter(p => p.status === 'pronto').length;

    if (badgePendentes) badgePendentes.textContent = `${pendentes} Pendente${pendentes !== 1 ? 's' : ''}`;
    if (badgePreparo)   badgePreparo.textContent   = `${preparo} Em Preparo`;
    if (badgeProntos)   badgeProntos.textContent   = `${prontos} Pronto${prontos !== 1 ? 's' : ''}`;
}

// ─── RENDER ───────────────────────────────────────────────────────

function renderizarPedidos(pedidos) {
    if (pedidos.length === 0) {
        grid.innerHTML = `
            <div class="cz-vazio">
                <span class="cz-vazio__icone">🍽️</span>
                <div class="cz-vazio__titulo">Cozinha Livre!</div>
                <div class="cz-vazio__sub">Nenhum pedido pendente no momento.</div>
            </div>
        `;
        renderizados.clear();
        atualizarBadges([]);
        return;
    }

    const vazio = grid.querySelector('.cz-vazio');
    if (vazio) vazio.remove();

    const idsAtuais = new Set(pedidos.map(p => String(p.id)));

    // Remove cards que saíram da fila
    grid.querySelectorAll('.cz-card').forEach(card => {
        if (!idsAtuais.has(card.dataset.id)) {
            card.remove();
            renderizados.delete(card.dataset.id);
        }
    });

    // Adiciona novos cards
    pedidos.forEach(pedido => {
        const idStr = String(pedido.id);
        if (renderizados.has(idStr)) return;
        grid.insertAdjacentHTML('beforeend', criarCardHTML(pedido));
        renderizados.add(idStr);
    });

    atualizarBadges(pedidos);
}

// ─── AÇÕES ────────────────────────────────────────────────────────

// Guarda dados dos pedidos para usar na impressão
let dadosPedidos = [];

grid.addEventListener('click', async e => {
    const btn  = e.target.closest('[data-acao]');
    if (!btn) return;

    const card = btn.closest('.cz-card');
    const id   = card?.dataset.id;
    const acao = btn.dataset.acao;

    // ── IMPRIMIR ──────────────────────────────────────────────────
    if (acao === 'imprimir') {
        const pedido = dadosPedidos.find(p => String(p.id) === String(id));
        if (!pedido) { window.print(); return; }

        // Injeta HTML limpo na área de impressão dedicada
        printArea.innerHTML = htmlParaImprimir(pedido);
        printArea.classList.add('cz-print-ativo');
        window.print();
        // Após imprimir, limpa
        setTimeout(() => {
            printArea.innerHTML = '';
            printArea.classList.remove('cz-print-ativo');
        }, 1000);
        return;
    }

    // ── INICIAR PREPARO ───────────────────────────────────────────
    if (acao === 'preparo') {
        await mudarStatus(btn, card, id, 'em_preparo');
        return;
    }

    // ── PEDIDO PRONTO ─────────────────────────────────────────────
    if (acao === 'pronto') {
        await mudarStatus(btn, card, id, 'pronto');
        return;
    }
});

async function mudarStatus(btn, card, id, novoStatus) {
    btn.disabled = true;
    const textoOriginal = btn.innerHTML;
    btn.innerHTML = '⏳ Aguarde...';

    try {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('status', novoStatus);
        const req = new Requests();
        const res = await req.setBody(fd).post('/pedido/update-status');

        if (res.status) {
            card.style.transition = 'opacity .3s, transform .3s';
            card.style.opacity = '0';
            card.style.transform = 'scale(.95)';
            setTimeout(() => {
                card.remove();
                renderizados.delete(String(id));
                buscarPedidos();
            }, 300);
        } else {
            alert(res.msg ?? 'Erro ao atualizar status.');
            btn.disabled = false;
            btn.innerHTML = textoOriginal;
        }
    } catch (err) {
        alert('Erro de comunicação: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = textoOriginal;
    }
}

// ─── POLLING ─────────────────────────────────────────────────────

async function buscarPedidos() {
    try {
        const req  = new Requests();
        const data = await req.get('/pedido/cozinha/listar');
        if (data.status && Array.isArray(data.pedidos)) {
            dadosPedidos = data.pedidos;
            renderizarPedidos(data.pedidos);
        }
    } catch (err) {
        console.warn('[Cozinha] Falha ao buscar pedidos:', err.message);
    }
}

buscarPedidos();
setInterval(buscarPedidos, POLL_INTERVAL);