import '../../css/cardapio.css';
import Swal from 'sweetalert2';
import Requests from '../components/requests.js';

// ── ESTADO ──────────────────────────────────────────────────────
let produtos = {};
let carrinho = [];
let catAtiva = 'todos';

// ── CATEGORIAS ───────────────────────────────────────────────────
const ORDEM_CATEGORIAS = ['entrada', 'prato principal', 'principal', 'sobremesa', 'bebida'];
const ICONES_CATEGORIA = {
    'entrada':         'fa-solid fa-seedling',
    'prato principal': 'fa-solid fa-utensils',
    'principal':       'fa-solid fa-utensils',
    'sobremesa':        'fa-solid fa-ice-cream',
    'bebida':           'fa-solid fa-wine-glass',
};

function normalizar(texto) {
    return (texto || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
}
function iconeCategoria(nome) {
    return ICONES_CATEGORIA[normalizar(nome)] || 'fa-solid fa-bowl-food';
}
function categoriasOrdenadas() {
    return Object.keys(produtos).sort((a, b) => {
        const ia = ORDEM_CATEGORIAS.indexOf(normalizar(a));
        const ib = ORDEM_CATEGORIAS.indexOf(normalizar(b));
        if (ia === -1 && ib === -1) return a.localeCompare(b);
        if (ia === -1) return 1;
        if (ib === -1) return -1;
        return ia - ib;
    });
}

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

// ── CARREGAR ITENS ───────────────────────────────────────────────
async function carregarItens() {
    const lista = document.getElementById('lista-produtos');
    lista.innerHTML = '<div class="loading-itens"><i class="fa-solid fa-spinner fa-spin"></i>Carregando cardápio...</div>';
    try {
        const requests = new Requests();
        const response = await requests.get('/cardapio/itens');
        if (!response.sucesso) {
            Swal.fire({ icon: 'error', title: 'Erro', text: response.erro || 'Erro ao carregar o cardápio.', timer: 3000, timerProgressBar: true });
            lista.innerHTML = '<div class="estado-erro"><i class="fa-solid fa-triangle-exclamation"></i>Não foi possível carregar o cardápio.</div>';
            return;
        }
        produtos = response.dados;
        renderCategorias();
        renderDestaques();
        renderProdutos();
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: `Restrição: ${error.message}`, timer: 3000, timerProgressBar: true });
        lista.innerHTML = '<div class="estado-erro"><i class="fa-solid fa-triangle-exclamation"></i>Não foi possível carregar o cardápio.</div>';
    }
}

// ── RENDER CATEGORIAS ────────────────────────────────────────────
function renderCategorias() {
    const bar = document.getElementById('categorias-bar');
    bar.innerHTML = '<button class="cat-btn active" data-cat="todos"><i class="fa-solid fa-circle-check"></i> Todos</button>';
    categoriasOrdenadas().forEach(cat => {
        const btn = document.createElement('button');
        btn.className = 'cat-btn';
        btn.dataset.cat = cat;
        btn.innerHTML = `<i class="${iconeCategoria(cat)}"></i> ${cat}`;
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

// ── RENDER DESTAQUES ─────────────────────────────────────────────
function renderDestaques() {
    const secao = document.getElementById('secao-destaques');
    const lista = document.getElementById('destaques-lista');
    const destacados = todosOsProdutos().filter(p => p.destaque);
    if (destacados.length === 0) {
        secao.classList.add('oculto');
        lista.innerHTML = '';
        return;
    }
    secao.classList.remove('oculto');
    lista.innerHTML = destacados.map(p => `
        <div class="destaque-card" data-id="${p.id}">
            <span class="destaque-ribbon"><i class="fa-solid fa-star"></i> Mais pedido</span>
            ${p.imagem_url
                ? `<img src="${p.imagem_url}" class="destaque-img" alt="${p.nome}">`
                : `<div class="destaque-img sem-foto"><i class="fa-solid fa-bowl-food"></i></div>`
            }
            <div class="destaque-corpo">
                <div class="destaque-nome">${p.nome}</div>
                <div class="destaque-preco">${formatBRL(p.preco_venda)}</div>
            </div>
        </div>
    `).join('');
    lista.querySelectorAll('.destaque-card').forEach(card => {
        card.addEventListener('click', () => adicionarAoCarrinho(Number(card.dataset.id)));
    });
}

// ── RENDER PRODUTOS ──────────────────────────────────────────────
function renderProdutos() {
    const lista = document.getElementById('lista-produtos');
    const filtrados = catAtiva === 'todos' ? todosOsProdutos() : (produtos[catAtiva] || []);
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

// ── CARRINHO ─────────────────────────────────────────────────────
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
        el.innerHTML = `<div class="carrinho-vazio"><i class="fa-solid fa-basket-shopping"></i>Nenhum item ainda.</div>`;
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

// ── PAINEL LATERAL ────────────────────────────────────────────────
const painel        = document.getElementById('modalCarrinho');
const painelOverlay = document.getElementById('painel-overlay');

function abrirPainel() {
    renderCarrinho();
    painel.classList.add('aberto');
    painelOverlay.classList.add('aberto');
    painel.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

function fecharPainel() {
    painel.classList.remove('aberto');
    painelOverlay.classList.remove('aberto');
    painel.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

document.getElementById('btn-carrinho').addEventListener('click', abrirPainel);
document.getElementById('btn-fechar-painel').addEventListener('click', fecharPainel);
painelOverlay.addEventListener('click', fecharPainel);
document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharPainel(); });

// ── FORMA DE PAGAMENTO ────────────────────────────────────────────
const opcoesPagamento = document.getElementById('forma-pagamento');

// Bloco de parcelamento (só visível em crédito/débito)
const blocoParcelamento = document.getElementById('bloco-parcelamento');
const inputParcelas     = document.getElementById('input-parcelas');
const inputIntervalo    = document.getElementById('input-intervalo');

function pagamentoSelecionado() {
    const marcado = opcoesPagamento.querySelector('input[name="pagamento"]:checked');
    return marcado ? marcado.value : '';
}

opcoesPagamento.querySelectorAll('input[name="pagamento"]').forEach(input => {
    input.addEventListener('change', () => {
        // Marca visual do card
        opcoesPagamento.querySelectorAll('.pagamento-card').forEach(card => {
            card.classList.toggle('selecionado', card.contains(input) && input.checked);
        });

        // Mostra/oculta bloco de parcelamento
        const exigeParcela = ['credito', 'debito'].includes(input.value);
        blocoParcelamento.classList.toggle('oculto', !exigeParcela);

        // Reseta valores ao ocultar
        if (!exigeParcela) {
            inputParcelas.value  = 1;
            inputIntervalo.value = 30;
        }
    });
});

// ── FINALIZAR PEDIDO ─────────────────────────────────────────────
async function finalizarPedido() {
    if (carrinho.length === 0) {
        Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Adicione itens ao pedido.', timer: 2000, timerProgressBar: true });
        return;
    }

    const pgto = pagamentoSelecionado();
    if (!pgto) {
        Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Selecione a forma de pagamento.', timer: 2000, timerProgressBar: true });
        return;
    }

    const mesaId = getMesaId();
    if (!mesaId) {
        Swal.fire({ icon: 'error', title: 'Mesa não identificada', text: 'Utilize o QR Code da sua mesa para fazer pedidos.', timer: 3000, timerProgressBar: true });
        return;
    }

    // Captura parcelas/intervalo só quando crédito ou débito
    const exigeParcela = ['credito', 'debito'].includes(pgto);
    const parcelas  = exigeParcela ? Math.max(1, parseInt(inputParcelas.value)  || 1)  : 1;
    const intervalo = exigeParcela ? Math.max(0, parseInt(inputIntervalo.value) || 30) : 0;

    if (exigeParcela && parcelas < 1) {
        Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Informe a quantidade de parcelas.', timer: 2000, timerProgressBar: true });
        return;
    }

    const btnFinalizar = document.getElementById('btn-finalizar');
    btnFinalizar.disabled = true;
    btnFinalizar.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando...';

    try {
        const requests = new Requests();
        requests.headers['Content-Type'] = 'application/json';
        const response = await requests.setBody(JSON.stringify({
            mesa_id:   mesaId,
            pagamento: pgto,
            parcelas,
            intervalo,
            itens: carrinho.map(i => ({
                id:         i.produto.id,
                nome:       i.produto.nome,
                quantidade: i.qty,
                preco:      i.produto.preco_venda,
            })),
        })).post('/cardapio/pedido');

        if (!response.sucesso) {
            Swal.fire({ icon: 'error', title: 'Erro', text: response.erro || 'Erro ao salvar pedido.', timer: 3000, timerProgressBar: true });
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
        fecharPainel();

    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: `Restrição: ${error.message}`, timer: 3000, timerProgressBar: true });
    } finally {
        btnFinalizar.disabled = false;
        btnFinalizar.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Fazer Pedido';
    }
}

document.getElementById('btn-finalizar').addEventListener('click', finalizarPedido);

// ── INIT ─────────────────────────────────────────────────────────
carregarItens();
// ── MODAL DE IDENTIFICAÇÃO ────────────────────────────────────────

(function initIdentificacao() {
    const modal    = document.getElementById('modal-identificacao');
    const inputNome  = document.getElementById('id-nome');
    const inputEmail = document.getElementById('id-email');
    const inputCpf   = document.getElementById('id-cpf');
    const erroEl     = document.getElementById('id-cpf-erro');
    const btnOk      = document.getElementById('btn-identificar');

    if (!modal) return;

    // Verifica se já identificou nesta sessão
    const clienteSalvo = sessionStorage.getItem('ap_cliente');
    if (clienteSalvo) {
        modal.style.display = 'none';
        return;
    }

    // Máscara CPF
    inputCpf.addEventListener('input', () => {
        let v = inputCpf.value.replace(/\D/g, '').slice(0, 11);
        if (v.length > 9) v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
        else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
        else if (v.length > 3) v = v.replace(/(\d{3})(\d{1,3})/, '$1.$2');
        inputCpf.value = v;
        erroEl.style.display = 'none';
        inputCpf.style.borderColor = '#e0d9c8';
    });

    btnOk.addEventListener('click', async () => {
        const nome  = inputNome.value.trim();
        const email = inputEmail.value.trim();
        const cpf   = inputCpf.value.replace(/\D/g, '');

        // Validações básicas
        if (!nome) { inputNome.focus(); return; }
        if (!email || !email.includes('@')) { inputEmail.focus(); return; }
        if (cpf.length !== 11) {
            erroEl.style.display = 'block';
            inputCpf.style.borderColor = '#dc3545';
            inputCpf.focus();
            return;
        }

        btnOk.disabled = true;
        btnOk.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Aguarde...';

        try {
            const requests = new Requests();
            requests.headers['Content-Type'] = 'application/json';
            const resp = await requests.setBody(JSON.stringify({ nome, email, cpf })).post('/cardapio/identificar');

            // Salva na sessão (com ou sem erro — o cliente identificou)
            sessionStorage.setItem('ap_cliente', JSON.stringify({ nome, email, cpf, id: resp?.cliente_id ?? null }));
            modal.style.display = 'none';
        } catch (err) {
            // Mesmo com erro no servidor, fecha o modal para não bloquear o cardápio
            sessionStorage.setItem('ap_cliente', JSON.stringify({ nome, email, cpf, id: null }));
            modal.style.display = 'none';
        }
    });
})();
