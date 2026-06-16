import '../../css/cardapio.css';
import * as bootstrap from 'bootstrap';
import Swal from 'sweetalert2';
import Requests from '../components/requests.js';


// ── ESTADO ──────────────────────────────────────────────────────
let produtos = {};  // { categoria: [...] } — vindo da API
let carrinho = [];  // [{ produto, qty }]
let catAtiva = 'todos';

// ── HELPERS ─────────────────────────────────────────────────────
function formatBRL(valor) {
    return 'R$ ' + Number(valor).toFixed(2).replace('.', ',');
}
function getMesaId() {
    return parseInt(document.body.dataset.mesaId || '0', 10) || null;
}
function todosOsProdutos() {
    return Object.values(produtos).flat();
}

// ── CARREGAR ITENS DA API ────────────────────────────────────────
async function carregarItens() {
    const lista = document.getElementById('lista-produtos');
    lista.innerHTML = '<div class="loading-itens"><i class="fa-solid fa-spinner fa-spin"></i> Carregando cardápio...</div>';

    try {
        const requests  = new Requests();
       const response = await requests.get('/cardapio/itens');

        if (!response.sucesso) {
            Swal.fire({
                icon:  'error',
                title: 'Erro',
                text:  response.erro || 'Erro ao carregar o cardápio.',
                timer: 3000,
                timerProgressBar: true,
            });
            lista.innerHTML = '<div class="alert alert-danger m-3"><i class="fa-solid fa-triangle-exclamation me-2"></i>Não foi possível carregar o cardápio.</div>';
            return;
        }

        produtos = response.dados;
        renderCategorias();
        renderProdutos();

    } catch (error) {
        Swal.fire({
            icon:  'error',
            title: 'Erro',
            text:  `Restrição: ${error.message}`,
            timer: 3000,
            timerProgressBar: true,
        });
    }
}

// ── RENDER CATEGORIAS ────────────────────────────────────────────
function renderCategorias() {
    const bar = document.getElementById('categorias-bar');
    bar.innerHTML = '<button class="cat-btn active" data-cat="todos">Todos</button>';

    Object.keys(produtos).forEach(cat => {
        const btn        = document.createElement('button');
        btn.className    = 'cat-btn';
        btn.dataset.cat  = cat;
        btn.textContent  = cat;
        bar.appendChild(btn);
    });

    bar.querySelectorAll('.cat-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            bar.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            catAtiva = btn.dataset.cat;
            renderProdutos();
        });
    });
}

// ── RENDER PRODUTOS ──────────────────────────────────────────────
function renderProdutos() {
    const lista = document.getElementById('lista-produtos');

    const filtrados = catAtiva === 'todos'
        ? todosOsProdutos()
        : (produtos[catAtiva] || []);

    if (filtrados.length === 0) {
        lista.innerHTML = '<div class="carrinho-vazio"><i class="fa-solid fa-bowl-food"></i>Nenhum item nesta categoria.</div>';
        return;
    }

    lista.innerHTML = filtrados.map(p => `
        <div class="produto-card" data-id="${p.id}">
            ${p.imagem_url
                ? `<img src="${p.imagem_url}" class="produto-img" alt="${p.nome}">`
                : `<div class="produto-img sem-foto"><i class="fa-solid fa-bowl-food"></i></div>`
            }
            <div class="produto-body">
                <div class="produto-nome">${p.nome}</div>
                <div class="produto-desc">${p.descricao || ''}</div>
                <div class="produto-preco">${formatBRL(p.preco_venda)}</div>
                <button class="btn-add btn-adicionar" data-id="${p.id}">
                    <i class="fa-solid fa-plus"></i> Adicionar
                </button>
            </div>
        </div>
    `).join('');

    document.querySelectorAll('.btn-adicionar').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            adicionarAoCarrinho(Number(btn.dataset.id));
        });
    });
}

// ── CARRINHO ────────────────────────────────────────────────────
function adicionarAoCarrinho(id) {
    const prod = todosOsProdutos().find(p => p.id === id);
    if (!prod) return;

    const item = carrinho.find(i => i.produto.id === id);
    if (item) item.qty++;
    else carrinho.push({ produto: prod, qty: 1 });

    atualizarUI();
    showToast();
}

function alterarQty(id, delta) {
    const idx = carrinho.findIndex(i => i.produto.id === id);
    if (idx === -1) return;

    carrinho[idx].qty += delta;
    if (carrinho[idx].qty <= 0) carrinho.splice(idx, 1);

    atualizarUI();
    renderCarrinho();
}
window.alterarQty = alterarQty;

function atualizarUI() {
    const total = carrinho.reduce((s, i) => s + i.qty, 0);
    document.getElementById('qty-total').textContent = total;
    document.getElementById('btn-carrinho').classList.toggle('oculto', total === 0);
}

function renderCarrinho() {
    const el = document.getElementById('itens-carrinho');

    if (carrinho.length === 0) {
        el.innerHTML = `
            <div class="carrinho-vazio">
                <i class="fa-solid fa-basket-shopping"></i>
                Nenhum item ainda.
            </div>`;
        document.getElementById('valor-total').textContent = 'R$ 0,00';
        return;
    }

    const totalVal = carrinho.reduce((s, i) => s + i.produto.preco_venda * i.qty, 0);

    el.innerHTML = carrinho.map(i => `
        <div class="item-carrinho">
            <div class="item-nome">${i.produto.nome}</div>
            <div class="qty-control">
                <button onclick="alterarQty(${i.produto.id}, -1)">−</button>
                <span class="qty-val">${i.qty}</span>
                <button onclick="alterarQty(${i.produto.id}, +1)">+</button>
            </div>
            <div class="item-preco">${formatBRL(i.produto.preco_venda * i.qty)}</div>
        </div>
    `).join('');

    document.getElementById('valor-total').textContent = formatBRL(totalVal);
}

// ── TOAST ────────────────────────────────────────────────────────
let toastTimer;
function showToast() {
    const t = document.getElementById('toast-add');
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 1800);
}

// ── FINALIZAR PEDIDO ─────────────────────────────────────────────
async function finalizarPedido() {
    if (carrinho.length === 0) {
        Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Adicione itens ao pedido.', timer: 2000, timerProgressBar: true });
        return;
    }

    const pgto = document.getElementById('forma-pagamento').value;
    if (!pgto) {
        Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Selecione a forma de pagamento.', timer: 2000, timerProgressBar: true });
        return;
    }

    const mesaId = getMesaId();
    if (!mesaId) {
        Swal.fire({ icon: 'error', title: 'Mesa não identificada', text: 'Utilize o QR Code da sua mesa para fazer pedidos.', timer: 3000, timerProgressBar: true });
        return;
    }

    const btnFinalizar = document.getElementById('btn-finalizar');
    btnFinalizar.disabled = true;
    btnFinalizar.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando...';

    try {
        const requests = new Requests();
        const response = await requests.setJson({
            mesa_id:   mesaId,
            pagamento: pgto,
            itens:     carrinho.map(i => ({
                produto_id: i.produto.id,
                quantidade: i.qty,
                preco:      i.produto.preco_venda,
            })),
        }).post('/pedido');

        if (!response.sucesso) {
            Swal.fire({
                icon:  'error',
                title: 'Erro',
                text:  response.erro || 'Erro ao salvar pedido.',
                timer: 3000,
                timerProgressBar: true,
            });
            return;
        }

        Swal.fire({
            icon:  'success',
            title: 'Pedido enviado!',
            text:  `${response.mensagem} — Total: ${formatBRL(response.total)}`,
            timer: 3000,
            timerProgressBar: true,
        });

        carrinho = [];
        atualizarUI();
        bootstrap.Modal.getInstance(document.getElementById('modalCarrinho')).hide();

    } catch (error) {
        Swal.fire({
            icon:  'error',
            title: 'Erro',
            text:  `Restrição: ${error.message}`,
            timer: 3000,
            timerProgressBar: true,
        });
    } finally {
        btnFinalizar.disabled = false;
        btnFinalizar.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Fazer Pedido';
    }
}

// ── EVENTOS ──────────────────────────────────────────────────────
document.getElementById('btn-carrinho').addEventListener('click', () => {
    renderCarrinho();
    new bootstrap.Modal(document.getElementById('modalCarrinho')).show();
});

document.getElementById('btn-finalizar').addEventListener('click', finalizarPedido);

// ── INIT ─────────────────────────────────────────────────────────
carregarItens();