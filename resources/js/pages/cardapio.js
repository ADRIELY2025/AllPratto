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
        renderSaldoPagamento();
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
    renderSaldoPagamento();
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
const opcoesPagamento   = document.getElementById('forma-pagamento');
const blocoParcelamento = document.getElementById('bloco-parcelamento');

// Estado de parcelas
let _installmentsData    = [];
let _selectedInstallment = null;
let _selectedPaymentId   = null;

function pagamentoSelecionado() {
    // retorna um array com as formas selecionadas (até 2) — compatível com legacy retornando string
    const checks = Array.from(opcoesPagamento.querySelectorAll('input[name="pagamento_cb"]:checked'));
    const vals = checks.map(c => c.value);
    return vals.length === 1 ? vals[0] : vals; // string for single, array for multiple
}

// ── SALDO A ALOCAR (quando 2 formas de pagamento são escolhidas) ──
// Segue o mesmo raciocínio do split de pagamento do OSale: soma-se o
// que já foi digitado em cada forma e subtrai-se do total; o botão de
// "Fazer Pedido" só fica liberado quando esse saldo chega a zero.
function totalPedidoAtual() {
    const totalEl = document.getElementById('valor-total');
    if (!totalEl) return 0;
    return parseFloat((totalEl.textContent || 'R$ 0,00').replace(/[R$\.\s]/g, '').replace(',', '.')) || 0;
}

function somaValoresPagamentoDigitados() {
    const checked = Array.from(opcoesPagamento.querySelectorAll('input[name="pagamento_cb"]:checked'));
    let somaCentavos = 0;
    checked.forEach(chk => {
        const card = chk.closest('.pagamento-card');
        const input = card ? card.querySelector('.pagamento-valor-input') : null;
        const v = input ? input.value.trim() : '';
        if (!v) return;
        const n = parseFloat(v.replace(/\./g, '').replace(',', '.'));
        if (!isNaN(n)) somaCentavos += Math.round(n * 100);
    });
    return somaCentavos / 100;
}

function renderSaldoPagamento() {
    const checked   = Array.from(opcoesPagamento.querySelectorAll('input[name="pagamento_cb"]:checked'));
    const saldoBox  = document.getElementById('saldo-pagamento');
    const saldoVal  = document.getElementById('saldo-pagamento-valor');
    const btn       = document.getElementById('btn-finalizar');

    if (checked.length === 0) {
        // nenhuma forma escolhida ainda — pedido não pode ser enviado
        if (saldoBox) saldoBox.classList.add('oculto');
        if (btn) btn.disabled = true;
        return;
    }

    if (checked.length === 1) {
        // uma única forma cobre o total automaticamente — libera o botão
        if (saldoBox) saldoBox.classList.add('oculto');
        if (btn) btn.disabled = false;
        return;
    }

    // duas formas selecionadas: mostra o saldo e só libera quando ele zerar
    const total   = totalPedidoAtual();
    const soma    = somaValoresPagamentoDigitados();
    const saldo   = Math.round((total - soma) * 100) / 100;

    if (saldoBox) {
        saldoBox.classList.remove('oculto');
        saldoBox.classList.remove('saldo-pendente', 'saldo-ok', 'saldo-negativo');

        if (Math.abs(saldo) < 0.005) {
            saldoBox.classList.add('saldo-ok');
            saldoBox.querySelector('span').innerHTML = '<i class="fa-solid fa-circle-check"></i> Valores conferem, pode enviar:';
            if (saldoVal) saldoVal.textContent = formatBRL(0);
        } else if (saldo < 0) {
            saldoBox.classList.add('saldo-negativo');
            saldoBox.querySelector('span').innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Valor ultrapassou o total em:';
            if (saldoVal) saldoVal.textContent = formatBRL(Math.abs(saldo));
        } else {
            saldoBox.classList.add('saldo-pendente');
            saldoBox.querySelector('span').innerHTML = '<i class="fa-solid fa-scale-balanced"></i> Falta escolher o valor de:';
            if (saldoVal) saldoVal.textContent = formatBRL(saldo);
        }
    }

    // "Fazer Pedido" só aparece liberado quando o saldo restante for exatamente zero
    if (btn) btn.disabled = Math.abs(saldo) >= 0.005;
}

/** Busca payment_terms de crédito e carrega installments no dropdown */
async function carregarInstallmentsCredito() {
    const selectParcelas = document.getElementById('select-parcelas-credito');
    if (!selectParcelas) return;

    selectParcelas.innerHTML = '<option value="">Carregando...</option>';
    selectParcelas.disabled  = true;
    _selectedInstallment     = null;

    try {
        const resPt  = await fetch('/sale/payment-terms', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const jsonPt = await resPt.json();
        const terms  = jsonPt.data || [];

        const ptCredito = terms.find(t =>
            ['credito', 'cartao_credito', 'cartao de credito', 'credit'].includes(
                (t.codigo || '').toLowerCase().replace(/[àáâãäéêíóôõúüç\s]/g, c =>
                    ({ à:'a',á:'a',â:'a',ã:'a',ä:'a',é:'e',ê:'e',í:'i',ó:'o',ô:'o',õ:'o',ú:'u',ü:'u',ç:'c',' ':'_' }[c] || c))
            )
        ) || terms.find(t => (t.titulo || '').toLowerCase().includes('cr'));

        if (!ptCredito) {
            selectParcelas.innerHTML = '<option value="">Nenhuma condição cadastrada</option>';
            return;
        }
        _selectedPaymentId = ptCredito.id;

        const resInst      = await fetch(`/sale/installments/${ptCredito.id}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const jsonInst     = await resInst.json();
        _installmentsData  = jsonInst.data || [];

        if (!_installmentsData.length) {
            selectParcelas.innerHTML = '<option value="">Sem parcelas cadastradas</option>';
            return;
        }

        renderSelectParcelas(selectParcelas);
        selectParcelas.disabled = false;
    } catch (err) {
        console.error('Erro ao carregar parcelas:', err);
        selectParcelas.innerHTML = '<option value="">Erro ao carregar</option>';
    }
}

/** Monta as opções "Nx de R$ X,XX" no select */
function renderSelectParcelas(selectEl) {
    const totalVal    = carrinho.reduce((s, i) => s + i.produto.preco_venda * i.qty, 0);
    const maxParcelas = Math.max(..._installmentsData.map(i => parseInt(i.parcela) || 1));

    selectEl.innerHTML = '';
    for (let n = 1; n <= maxParcelas; n++) {
        const inst = _installmentsData.find(i => parseInt(i.parcela) === n);
        if (!inst) continue;
        const opt              = document.createElement('option');
        opt.value              = n;
        opt.dataset.installmentId = inst.id;
        opt.dataset.intervalo     = inst.intervalo;
        opt.textContent        = `${n}x de ${formatBRL(totalVal > 0 ? totalVal / n : 0)}`;
        if (n === 1) opt.selected = true;
        selectEl.appendChild(opt);
    }
    syncInstallmentSelecionado(selectEl);
}

function syncInstallmentSelecionado(selectEl) {
    const opt = selectEl.options[selectEl.selectedIndex];
    _selectedInstallment = opt
        ? { id: parseInt(opt.dataset.installmentId) || null, parcela: parseInt(opt.value) || 1, intervalo: parseInt(opt.dataset.intervalo) || 30 }
        : null;
}

opcoesPagamento.querySelectorAll('input[name="pagamento_cb"]').forEach(input => {
        input.addEventListener('change', () => {
            // Limita seleção a 2 checkboxes
            const checked = Array.from(opcoesPagamento.querySelectorAll('input[name="pagamento_cb"]:checked'));
            if (checked.length > 2) {
                // desmarca o último selecionado
                input.checked = false;
                Swal.fire({ icon: 'warning', title: 'Limite', text: 'Você pode escolher no máximo 2 formas de pagamento.' });
                return;
            }

            // atualiza visual dos cards; o campo de valor só aparece quando
            // EXATAMENTE 2 formas estão marcadas (com 1 forma o total inteiro
            // vai pra ela automaticamente, sem precisar escolher valor)
            opcoesPagamento.querySelectorAll('.pagamento-card').forEach(card => {
                const chk = card.querySelector('input[name="pagamento_cb"]');
                const isChecked = !!(chk && chk.checked);
                card.classList.toggle('selecionado', isChecked);
                const valorBox = card.querySelector('.pagamento-valor');
                if (valorBox) valorBox.classList.toggle('oculto', !(isChecked && checked.length === 2));
            });

            // Se apenas crédito selecionado, carregar parcelas
            const selectedVals = checked.map(c => c.value);
            const isCredito = selectedVals.includes('credito');
            blocoParcelamento.classList.toggle('oculto', !isCredito);

            if (isCredito) {
                carregarInstallmentsCredito();
            } else {
                _selectedInstallment = null;
                _selectedPaymentId   = null;
            }

            if (checked.length === 2) {
                // A primeira forma (na ordem dos cards) é editável — a pessoa digita o valor.
                // A segunda é calculada automaticamente: total - valor digitado na primeira.
                const cardsChecked = Array.from(opcoesPagamento.querySelectorAll('.pagamento-card'))
                    .filter(c => c.querySelector('input[name="pagamento_cb"]').checked);
                const [cardEditavel, cardCalculado] = cardsChecked;
                const inputEditavel  = cardEditavel  && cardEditavel.querySelector('.pagamento-valor-input');
                const inputCalculado = cardCalculado && cardCalculado.querySelector('.pagamento-valor-input');

                if (inputEditavel) {
                    inputEditavel.readOnly = false;
                    inputEditavel.classList.remove('pagamento-valor-input--calculado');
                    inputEditavel.value = '';
                }
                if (inputCalculado) {
                    inputCalculado.readOnly = true;
                    inputCalculado.classList.add('pagamento-valor-input--calculado');
                    inputCalculado.value = totalPedidoAtual().toFixed(2).replace('.', ',');
                }
            }

            renderSaldoPagamento();
        });
    });

// Enquanto a pessoa digita o valor na forma editável, a outra forma marcada
// recalcula sozinha o restante (total - valor digitado) em tempo real.
opcoesPagamento.addEventListener('input', (e) => {
    if (!e.target.classList || !e.target.classList.contains('pagamento-valor-input')) return;
    if (e.target.readOnly) { renderSaldoPagamento(); return; }

    const checked = Array.from(opcoesPagamento.querySelectorAll('input[name="pagamento_cb"]:checked'));
    if (checked.length === 2) {
        const inputCalculado = Array.from(opcoesPagamento.querySelectorAll('.pagamento-valor-input'))
            .find(i => i.readOnly && i !== e.target);

        if (inputCalculado) {
            const total    = totalPedidoAtual();
            const raw      = e.target.value.trim().replace(/\./g, '').replace(',', '.');
            let digitado   = parseFloat(raw);
            if (isNaN(digitado) || digitado < 0) digitado = 0;

            let restante = Math.round((total - digitado) * 100) / 100;
            if (restante < 0) restante = 0; // não deixa a forma calculada ficar negativa

            inputCalculado.value = restante.toFixed(2).replace('.', ',');
        }
    }

    renderSaldoPagamento();
});

// Formata valores dos inputs de pagamento ao perder foco
opcoesPagamento.addEventListener('blur', (e) => {
    if (!e.target.classList) return;
    if (e.target.classList.contains('pagamento-valor-input')) {
        const v = e.target.value.trim();
        if (!v) return;
        const n = parseFloat(v.replace('.', '').replace(',', '.'));
        if (isNaN(n)) {
            e.target.value = '';
        } else {
            e.target.value = n.toFixed(2).replace('.', ',');
        }
        renderSaldoPagamento();
    }
}, true);

blocoParcelamento.addEventListener('change', e => {
    if (e.target && e.target.id === 'select-parcelas-credito') {
        syncInstallmentSelecionado(e.target);
    }
});

// ── IDENTIFICAÇÃO DO CLIENTE ──────────────────────────────────────
// Guarda dados do cliente identificado na sessão (reload apaga, como esperado)
let clienteIdentificado = null;  // { id_cliente, nome }

async function identificarClienteSeNecessario() {
    if (clienteIdentificado) return true;  // já identificado

    const { value: formValues } = await Swal.fire({
        title: 'Identificação',
        html: `
            <p style="margin-bottom:12px;color:#555;font-size:.95rem;">Para fazermos seu pedido, precisamos de algumas informações.</p>
            <input id="swal-nome"  class="swal2-input" placeholder="Seu nome *" autocomplete="name">
            <input id="swal-cpf"   class="swal2-input" placeholder="CPF *" inputmode="numeric">
            <input id="swal-email" class="swal2-input" placeholder="E-mail (opcional)" type="email">
        `,
        confirmButtonText: 'Continuar',
        showCancelButton:  true,
        cancelButtonText:  'Cancelar',
        focusConfirm: false,
        didOpen: () => {
            const nomeEl = document.getElementById('swal-nome');
            const cpfEl  = document.getElementById('swal-cpf');
            if (nomeEl) nomeEl.focus();
            try {
                if (typeof Inputmask !== 'undefined') {
                    Inputmask({ mask: '999.999.999-99' }).mask(cpfEl);
                }
            } catch (e) {
                // ignore if Inputmask not loaded
            }
        },
        preConfirm: () => {
            const nome  = document.getElementById('swal-nome').value.trim();
            const cpfRaw   = document.getElementById('swal-cpf').value.trim();
            const cpfDigits = cpfRaw.replace(/\D/g, '');
            const email = document.getElementById('swal-email').value.trim();
            if (!nome) {
                Swal.showValidationMessage('Nome é obrigatório');
                return false;
            }
            if (!cpfDigits || cpfDigits.length !== 11) {
                Swal.showValidationMessage('CPF inválido. Use formato 000.000.000-00');
                return false;
            }
            return { nome, cpf: cpfDigits, email };
        },
    });

    if (!formValues) return false;  // cancelou

    try {
        const requests = new Requests();
        requests.headers['Content-Type'] = 'application/json';
        const res = await requests.setBody(JSON.stringify(formValues)).post('/cardapio/identificar');
        if (!res.sucesso) {
            Swal.fire({ icon: 'error', title: 'Erro', text: res.erro || 'Não foi possível identificar o cliente.', timer: 3000, timerProgressBar: true });
            return false;
        }
        clienteIdentificado = { id_cliente: res.id_cliente, nome: res.nome };
        return true;
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Erro', text: err.message, timer: 3000, timerProgressBar: true });
        return false;
    }
}

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

    // Identifica o cliente (nome/CPF/email) antes de prosseguir
    const identificado = await identificarClienteSeNecessario();
    if (!identificado) return;

    // Constrói o payload de pagamentos a partir dos checkboxes e inputs visíveis
    let pagamentosPayload = null;
    const totalPedido = carrinho.reduce((s, i) => s + (Number(i.qty) * Number(i.produto.preco_venda || i.produto.preco || 0)), 0);
    const selectedChecks = Array.from(opcoesPagamento.querySelectorAll('input[name="pagamento_cb"]:checked'));
    if (selectedChecks.length === 0) {
        Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Selecione a forma de pagamento.', timer: 2000, timerProgressBar: true });
        return;
    }

    // coleta valores dos inputs correspondentes
    pagamentosPayload = [];
    let soma = 0;
    for (const chk of selectedChecks) {
        const card = chk.closest('.pagamento-card');
        const inputValor = card ? card.querySelector('.pagamento-valor-input') : null;
        let valor = totalPedido;
        if (inputValor && inputValor.value.trim() !== '') {
            valor = parseFloat(inputValor.value.replace('.', '').replace(',', '.'));
            if (isNaN(valor) || valor < 0) valor = 0;
        }
        soma += Math.round(valor * 100) / 100;

        pagamentosPayload.push({ forma: chk.value, valor: Math.round(valor * 100) / 100, parcelas: 1, intervalo: 0 });
    }

    // Se apenas 1 forma selecionada, ok — se 2, valida soma igual ao total (com tolerância de 0.01)
    if (selectedChecks.length === 2) {
        if (Math.abs(soma - Math.round(totalPedido * 100) / 100) > 0.01) {
            Swal.fire({ icon: 'warning', title: 'Valores divergentes', text: 'A soma dos valores informados deve ser igual ao total do pedido.', timer: 2500 });
            return;
        }
    } else {
        // single: garante que o slice cubra todo o total
        pagamentosPayload[0].valor = Math.round(totalPedido * 100) / 100;
    }

    // Ajusta parcelas/intervalo para crédito caso haja crédito selecionado
    for (const p of pagamentosPayload) {
        if (p.forma === 'credito') {
            if (_selectedInstallment) {
                p.parcelas = _selectedInstallment.parcela || 1;
                p.intervalo = _selectedInstallment.intervalo || 30;
            } else {
                const sel = document.getElementById('select-parcelas-credito');
                if (sel && sel.value) {
                    p.parcelas = parseInt(sel.value, 10) || 1;
                    p.intervalo = parseInt(sel.options[sel.selectedIndex]?.dataset.intervalo) || 30;
                }
            }
        }
    }

    // Captura parcelas/intervalo só quando crédito
    const isCredito = pgto === 'credito';
    let parcelas  = 1;
    let intervalo = 0;

    if (isCredito) {
        if (_selectedInstallment) {
            parcelas  = _selectedInstallment.parcela;
            intervalo = _selectedInstallment.intervalo;
        } else {
            const sel = document.getElementById('select-parcelas-credito');
            if (sel) {
                parcelas  = parseInt(sel.value) || 1;
                intervalo = parseInt(sel.options[sel.selectedIndex]?.dataset.intervalo) || 30;
            }
        }
    }

    const btnFinalizar = document.getElementById('btn-finalizar');
    btnFinalizar.disabled = true;
    btnFinalizar.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando...';

    try {
        const requests = new Requests();
        requests.headers['Content-Type'] = 'application/json';
        const payload = {
            mesa_id:    mesaId,
            id_cliente: clienteIdentificado?.id_cliente ?? null,
            itens: carrinho.map(i => ({
                id:         i.produto.id,
                nome:       i.produto.nome,
                quantidade: i.qty,
                preco:      i.produto.preco_venda || i.produto.preco,
            })),
        };

        if (pagamentosPayload) {
            payload.pagamentos = pagamentosPayload;
        } else {
            // formato legado
            payload.pagamento  = pgto;
            payload.parcelas   = parcelas;
            payload.intervalo  = intervalo;
        }

        const response = await requests.setBody(JSON.stringify(payload)).post('/cardapio/pedido');

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
        carregarMeusPedidos();

    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: `Restrição: ${error.message}`, timer: 3000, timerProgressBar: true });
    } finally {
        btnFinalizar.disabled = false;
        btnFinalizar.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Fazer Pedido';
    }
}

document.getElementById('btn-finalizar').addEventListener('click', finalizarPedido);

// ── MEUS PEDIDOS (histórico enviado à cozinha) ─────────────────────
const MAPA_STATUS_ITEM = {
    Awaiting:  { texto: 'Aguardando', classe: 'badge-status--aguardando', cancelavel: true  },
    Preparing: { texto: 'Em preparo', classe: 'badge-status--preparo',    cancelavel: true  },
    Ready:     { texto: 'Pronto',     classe: 'badge-status--pronto',     cancelavel: false },
    Delivered: { texto: 'Entregue',   classe: 'badge-status--entregue',  cancelavel: false },
    Cancelled: { texto: 'Cancelado',  classe: 'badge-status--cancelado', cancelavel: false },
};

const MAPA_STATUS_PEDIDO = {
    pendente:   'Recebido',
    em_preparo: 'Em preparo',
    pronto:     'Pronto',
    entregue:   'Entregue',
    cancelado:  'Cancelado',
    pago:       'Pago',
};

let meusPedidosCache = [];

function infoStatusItem(status) {
    return MAPA_STATUS_ITEM[status] || MAPA_STATUS_ITEM.Awaiting;
}

async function carregarMeusPedidos() {
    const mesaId = getMesaId();
    const badge  = document.getElementById('badge-meus-pedidos');

    if (!mesaId) {
        meusPedidosCache = [];
        badge.classList.add('oculto');
        return;
    }

    try {
        const requests = new Requests();
        const response  = await requests.get(`/cardapio/pedidos/mesa/${mesaId}`);
        if (!response.sucesso) return;

        meusPedidosCache = response.pedidos || [];

        // Badge = qtde de itens ainda não cancelados em pedidos abertos
        const qtdAtiva = meusPedidosCache.reduce((soma, pedido) => {
            return soma + pedido.itens
                .filter(i => i.status_cozinha !== 'Cancelled')
                .reduce((s, i) => s + Number(i.quantidade), 0);
        }, 0);

        if (qtdAtiva > 0) {
            badge.textContent = qtdAtiva;
            badge.classList.remove('oculto');
        } else {
            badge.classList.add('oculto');
        }
    } catch (error) {
        console.warn('Erro ao carregar meus pedidos:', error.message);
    }
}

function renderMeusPedidos() {
    const container = document.getElementById('lista-meus-pedidos');
    const totalRow   = document.getElementById('total-row-meus-pedidos');
    const btnImprimir = document.getElementById('btn-imprimir-nota');

    if (meusPedidosCache.length === 0) {
        container.innerHTML = `<div class="carrinho-vazio"><i class="fa-solid fa-receipt"></i>Você ainda não enviou nenhum pedido para a cozinha.</div>`;
        totalRow.style.display = 'none';
        btnImprimir.style.display = 'none';
        return;
    }

    totalRow.style.display = '';
    btnImprimir.style.display = '';

    let totalGeral = 0;

    container.innerHTML = meusPedidosCache.map(pedido => {
        const itensHTML = pedido.itens.map(item => {
            const info = infoStatusItem(item.status_cozinha);
            const podeCancel = info.cancelavel;
            if (item.status_cozinha !== 'Cancelled') {
                totalGeral += Number(item.subtotal);
            }
            return `
                <div class="item-pedido-status">
                    <div class="item-pedido-status__qtd">${item.quantidade}x</div>
                    <div class="item-pedido-status__info">
                        <div class="item-pedido-status__nome${item.status_cozinha === 'Cancelled' ? ' cancelado' : ''}">${item.nome}</div>
                        <div class="item-pedido-status__preco">${formatBRL(item.subtotal)}</div>
                    </div>
                    <span class="badge-status ${info.classe}">${info.texto}</span>
                    ${podeCancel ? `<button class="btn-cancelar-item" title="Cancelar item" data-order-item-id="${item.order_item_id}"><i class="fa-solid fa-xmark"></i></button>` : ''}
                </div>
            `;
        }).join('');

        return `
            <div class="pedido-bloco">
                <div class="pedido-bloco__topo">
                    <div>
                        <div class="pedido-bloco__numero">Pedido #${String(pedido.id).padStart(3, '0')}</div>
                        <div class="pedido-bloco__hora">${pedido.criado_em || ''}</div>
                    </div>
                    <span class="status-pedido-geral">${MAPA_STATUS_PEDIDO[pedido.status] || pedido.status}</span>
                </div>
                ${itensHTML}
            </div>
        `;
    }).join('');

    document.getElementById('valor-total-meus-pedidos').textContent = formatBRL(totalGeral);

    container.querySelectorAll('.btn-cancelar-item').forEach(btn => {
        btn.addEventListener('click', () => cancelarItemPedido(Number(btn.dataset.orderItemId)));
    });
}

async function cancelarItemPedido(orderItemId) {
    const confirmacao = await Swal.fire({
        icon: 'warning',
        title: 'Cancelar item?',
        text: 'Esse item ainda não começou a ser preparado e será removido do seu pedido.',
        showCancelButton: true,
        confirmButtonText: 'Sim, cancelar',
        cancelButtonText: 'Voltar',
        confirmButtonColor: '#6b1f2a',
    });
    if (!confirmacao.isConfirmed) return;

    try {
        const requests = new Requests();
        requests.headers['Content-Type'] = 'application/json';
        const response = await requests.setBody(JSON.stringify({ order_item_id: orderItemId })).post('/cardapio/pedido/cancelar-item');

        if (!response.sucesso) {
            Swal.fire({ icon: 'error', title: 'Não foi possível cancelar', text: response.erro, timer: 3000, timerProgressBar: true });
            return;
        }

        Swal.fire({ icon: 'success', title: 'Item cancelado!', timer: 1800, timerProgressBar: true });
        await carregarMeusPedidos();
        renderMeusPedidos();
    } catch (error) {
        // Mensagem mais amigável quando o pedido já está finalizado
        const isFinalStatus = error && (error.status === 409 || (error.body && typeof error.body.erro === 'string' && error.body.erro.toLowerCase().includes('pedido com status')));
        if (isFinalStatus) {
            Swal.fire({ icon: 'warning', title: 'Pedido finalizado', text: 'O seu pedido já está pronto, não é possível cancelá-lo.', timer: 3000, timerProgressBar: true });
            await carregarMeusPedidos();
            renderMeusPedidos();
            return;
        }

        Swal.fire({ icon: 'error', title: 'Erro', text: error.message || 'Erro ao cancelar item.', timer: 3000, timerProgressBar: true });
    }
}

// ── NOTA DE IMPRESSÃO ────────────────────────────────────────────
function gerarNotaHTML() {
    const mesaNumero = document.body.dataset.mesaNumero || '-';
    const agora = new Date().toLocaleString('pt-BR');

    let totalGeral = 0;
    const clienteNome = clienteIdentificado?.nome
        || meusPedidosCache.find(p => p.cliente_nome)?.cliente_nome
        || 'Consumidor';

    const blocosHTML = meusPedidosCache.map(pedido => {
        const itensAtivos = pedido.itens.filter(i => i.status_cozinha !== 'Cancelled');
        if (itensAtivos.length === 0) return '';

        const subtotalPedido = itensAtivos.reduce((s, i) => s + Number(i.subtotal), 0);
        totalGeral += subtotalPedido;

        const linhasHTML = itensAtivos.map(item => `
            <tr>
                <td style="padding:4px 0;font-size:13px;">${item.quantidade}x</td>
                <td style="padding:4px 0;font-size:13px;">${item.nome}</td>
                <td style="padding:4px 0;font-size:13px;text-align:right;">${formatBRL(item.subtotal)}</td>
            </tr>
        `).join('');

        return `
            <div style="margin-bottom:14px;">
                <div style="font-size:12px;font-weight:bold;border-bottom:1px solid #ccc;padding-bottom:4px;margin-bottom:4px;display:flex;justify-content:space-between;">
                    <span>Pedido #${String(pedido.id).padStart(3, '0')}</span>
                    <span>${pedido.pagamento || '-'}</span>
                </div>
                <table style="width:100%;border-collapse:collapse;">
                    ${linhasHTML}
                </table>
                <div style="text-align:right;font-size:12px;margin-top:2px;">Subtotal: <strong>${formatBRL(subtotalPedido)}</strong></div>
            </div>
        `;
    }).join('');

    return `
        <div style="font-family:Arial,sans-serif;max-width:380px;margin:0 auto;padding:20px;">
            <div style="text-align:center;border-bottom:2px solid #333;padding-bottom:12px;margin-bottom:16px;">
                <div style="font-size:16px;font-weight:bold;">AllPratto</div>
                <div style="font-size:11px;color:#666;margin-top:2px;">${agora}</div>
                <div style="font-size:13px;margin-top:6px;">🪑 Mesa ${mesaNumero} &nbsp;|&nbsp; ${clienteNome}</div>
            </div>

            ${blocosHTML}

            <div style="border-top:2px solid #333;margin-top:10px;padding-top:10px;display:flex;justify-content:space-between;font-size:16px;font-weight:bold;">
                <span>TOTAL</span>
                <span>${formatBRL(totalGeral)}</span>
            </div>

            <div style="text-align:center;margin-top:20px;font-size:11px;color:#999;border-top:1px dashed #ccc;padding-top:12px;">
                Obrigado pela preferência!
            </div>
        </div>
    `;
}

function imprimirNota() {
    const area = document.getElementById('area-impressao');
    area.innerHTML = gerarNotaHTML();
    window.print();
    setTimeout(() => { area.innerHTML = ''; }, 1000);
}

// ── PAINEL "MEUS PEDIDOS" — abrir/fechar ────────────────────────────
const painelPedidos        = document.getElementById('modalMeusPedidos');
const painelOverlayPedidos = document.getElementById('painel-overlay-pedidos');

async function abrirPainelMeusPedidos() {
    painelPedidos.classList.add('aberto');
    painelOverlayPedidos.classList.add('aberto');
    painelPedidos.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';

    document.getElementById('lista-meus-pedidos').innerHTML =
        '<div class="carrinho-vazio"><i class="fa-solid fa-spinner fa-spin"></i>Carregando seus pedidos...</div>';

    await carregarMeusPedidos();
    renderMeusPedidos();
}

function fecharPainelMeusPedidos() {
    painelPedidos.classList.remove('aberto');
    painelOverlayPedidos.classList.remove('aberto');
    painelPedidos.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

document.getElementById('btn-meus-pedidos').addEventListener('click', abrirPainelMeusPedidos);
document.getElementById('btn-fechar-meus-pedidos').addEventListener('click', fecharPainelMeusPedidos);
document.getElementById('btn-imprimir-nota').addEventListener('click', imprimirNota);
painelOverlayPedidos.addEventListener('click', fecharPainelMeusPedidos);
document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharPainelMeusPedidos(); });

// ── INIT ─────────────────────────────────────────────────────────
carregarItens();
carregarMeusPedidos();