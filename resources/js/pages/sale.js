import 'select2';
import * as bootstrap from 'bootstrap';
import Requests from '../components/requests.js';
import {
    saleItems,
    insertItem,
    renderItems,
    clearItems,
    updateTotals,
    stringParaFloat,
    floatParaBR,
} from './itemsale.js';

// ─── Estado global da venda ───────────────────────────────────────────────────

let currentSaleId   = null;
let currentClientId = null;

// ─── Referências DOM ──────────────────────────────────────────────────────────

const inputQuantidade      = document.getElementById('quantidade');
const inputUnitarioLiquido = document.getElementById('unitario_liquido');
const inputValorTotal      = document.getElementById('valor-total');
const saleIdInput          = document.getElementById('sale-id');

// ─── Status da venda ──────────────────────────────────────────────────────────

function updateSaleStatus() {
    const badge = document.getElementById('sale-status');
    if (!badge) return;
    badge.textContent = currentSaleId ? `Em edição (Venda #${currentSaleId})` : 'Em edição';
}

// ─── Select2: Cliente ─────────────────────────────────────────────────────────

function initCustomerSelect() {
    $('#id_cliente').select2({
        theme: 'bootstrap-5',
        placeholder: 'Selecione um cliente',
        allowClear: true,
        language: 'pt-BR',
        minimumInputLength: 0,
        ajax: {
            url: '/sale/find-customer',
            type: 'POST',
            delay: 250,
            cache: false,
            data: function (params) {
                return { term: params.term || '', limit: 50, offset: 0 };
            },
            processResults: function (json) {
                return {
                    results: (json.data || []).map(function (item) {
                        return {
                            id: item.id,
                            text: '#' + item.id + ' — ' + item.nome + (item.cpf ? ' (' + item.cpf + ')' : ''),
                        };
                    }),
                };
            },
        },
    });
}

// ─── Select2: Produto ─────────────────────────────────────────────────────────

function initProductSelect() {
    const $select = $('#id_produto');
    if (!$select.length || !$.fn.select2) return;

    $select.select2({
        theme: 'bootstrap-5',
        placeholder: 'Buscar produto...',
        allowClear: true,
        language: 'pt-BR',
        minimumInputLength: 0,
        ajax: {
            url: '/sale/find-product',
            type: 'POST',
            delay: 250,
            cache: false,
            data: function (params) {
                return { term: params.term || '', limit: 50, offset: 0 };
            },
            processResults: function (json) {
                return {
                    results: (json.data || []).map(function (item) {
                        return {
                            id: item.id,
                            text: '#' + item.id + ' — ' + item.nome + (item.codigo_barra ? ' [' + item.codigo_barra + ']' : ''),
                            preco_venda: item.preco_venda,
                        };
                    }),
                };
            },
        },
    });
}

// ─── Cálculo do total do item ─────────────────────────────────────────────────

function calcularTotal() {
    try {
        // Usa o value diretamente — stringParaFloat já lida com formato BR
        const precoRaw = inputUnitarioLiquido?.value ?? '0';
        const qtdRaw   = inputQuantidade?.value ?? '0';

        const preco = stringParaFloat(precoRaw);
        const qtd   = stringParaFloat(qtdRaw);
        const total = preco * qtd;

        if (inputValorTotal) {
            inputValorTotal.value = floatParaBR(total);
        }
    } catch (e) {
        console.error('Erro ao calcular total do item:', e);
    }
}

// ─── Criar venda no banco ─────────────────────────────────────────────────────

async function criarVenda(clienteId) {
    const id       = Number(clienteId) || null;
    const requests = new Requests();
    const fd       = new FormData();

    if (id) fd.append('id_cliente', id);
    fd.append('total_bruto',   0);
    fd.append('total_liquido', 0);
    fd.append('desconto',      0);
    fd.append('acrescimo',     0);
    fd.append('observacao',    document.getElementById('observacao')?.value || '');
    fd.append('estado_venda',  'PRE_VENDA');

    try {
        const response = await requests.setBody(fd).post('/sale/insert');
        if (!response?.status) {
            return { status: false, msg: response?.msg || 'Não foi possível criar a venda.' };
        }
        currentSaleId   = response.id;
        currentClientId = id ? String(id) : '';
        updateSaleStatus();
        return { status: true, id: currentSaleId };
    } catch (e) {
        return { status: false, msg: e.message };
    }
}

async function garantirVenda(clienteId) {
    if (!clienteId) {
        return { status: false, msg: 'Selecione um cliente antes de adicionar itens.' };
    }

    if (currentSaleId) {
        const cId = currentClientId ? String(currentClientId) : '';
        if (cId && cId !== String(clienteId)) {
            return { status: false, msg: 'O cliente da venda já foi definido. Limpe a venda para trocar de cliente.' };
        }
        return { status: true, id: currentSaleId };
    }

    return await criarVenda(clienteId);
}

// ─── Limpar venda ─────────────────────────────────────────────────────────────

function clearSale() {
    clearItems();
    currentSaleId   = null;
    currentClientId = null;
    updateSaleStatus();

    const clienteEl = document.getElementById('id_cliente');
    if (clienteEl) $(clienteEl).val(null).trigger('change');

    const observacaoEl = document.getElementById('observacao');
    if (observacaoEl) observacaoEl.value = '';

    const descontoEl = document.getElementById('desconto');
    if (descontoEl) descontoEl.value = '0.00';

    const acrescimoEl = document.getElementById('acrescimo');
    if (acrescimoEl) acrescimoEl.value = '0.00';

    updateTotals();
}

// ─── Modal de Condição de Pagamento ──────────────────────────────────────────

let selectedPaymentTermId   = null;
let selectedPaymentTermName = '';
let currentTotalLiquido     = 0;

async function loadPaymentTerms() {
    const container = document.getElementById('payment-terms-list');
    if (!container) return;

    container.innerHTML = '<div class="text-muted"><span class="spinner-border spinner-border-sm me-1"></span> Carregando...</div>';

    try {
        const res   = await fetch('/sale/payment-terms', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const json  = await res.json();
        const terms = json.data || [];

        if (!terms.length) {
            container.innerHTML = '<div class="alert alert-warning mb-0">Nenhuma condição de pagamento cadastrada.</div>';
            return;
        }

        container.innerHTML = '';
        terms.forEach(term => {
            const btn = document.createElement('button');
            btn.type      = 'button';
            btn.className = 'btn btn-outline-success';
            btn.dataset.id     = term.id;
            btn.dataset.title  = term.titulo;
            btn.dataset.codigo = term.codigo || '';
            btn.innerHTML = `<i class="fa-solid fa-credit-card me-1"></i> ${term.titulo}`;
            if (term.atalho) btn.innerHTML += ` <small class="text-muted">(${term.atalho})</small>`;
            btn.addEventListener('click', () => selectPaymentTerm(term.id, term.titulo, term.codigo || ''));
            container.appendChild(btn);
        });

    } catch (e) {
        container.innerHTML = '<div class="alert alert-danger mb-0">Erro ao carregar condições de pagamento.</div>';
    }
}

// Parcelas carregadas da condição selecionada (usadas para recalcular ao mudar qtd)
let _loadedInstallments = [];

// Códigos que NÃO permitem parcelamento (à vista)
const SEM_PARCELAMENTO = ['01', '04', '17']; // Dinheiro, Cartão de Débito, PIX

async function selectPaymentTerm(termId, termTitle, termCodigo = '') {
    // Destaca botão selecionado
    document.querySelectorAll('#payment-terms-list .btn').forEach(b => {
        b.classList.toggle('btn-success',         b.dataset.id == termId);
        b.classList.toggle('btn-outline-success', b.dataset.id != termId);
    });

    selectedPaymentTermId   = termId;
    selectedPaymentTermName = termTitle;

    const section    = document.getElementById('installments-section');
    const nameEl     = document.getElementById('selected-term-name');
    const tbody      = document.getElementById('installments-tbody');
    const totalEl    = document.getElementById('installments-total');
    const confirmBtn = document.getElementById('confirm-payment-btn');

    if (nameEl) nameEl.textContent = termTitle;
    section?.classList.remove('d-none');
    if (tbody) tbody.innerHTML = '<tr><td colspan="3" class="text-center"><span class="spinner-border spinner-border-sm"></span></td></tr>';
    confirmBtn.disabled = true;

    // Remove seletor de parcelas anterior, se houver
    document.getElementById('parcelas-selector-row')?.remove();

    try {
        const res          = await fetch(`/sale/installments/${termId}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const json         = await res.json();
        const installments = json.data || [];
        _loadedInstallments = installments;

        if (!installments.length || SEM_PARCELAMENTO.includes(termCodigo)) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-muted text-center">Pagamento à vista.</td></tr>';
            if (totalEl) totalEl.textContent = formatBRL(currentTotalLiquido);
            confirmBtn.disabled = false;
            return;
        }

        // Debug: mostra no console o que veio do banco
        console.log('[Parcelas recebidas]', installments);

        // Pega o maior valor de "parcela" cadastrado — ex: se cadastrou 11, maxParcelas = 11
        const maxParcelas = Math.max(...installments.map(i => parseInt(i.parcela) || 1));
        // Intervalo padrão (usa o do primeiro registro ou 30 dias)
        const intervalo   = parseInt(installments[0]?.intervalo) || 30;

        console.log('[maxParcelas]', maxParcelas, '[intervalo]', intervalo);

        // Sempre mostra o seletor quando há registro de parcela
        // (mesmo que maxParcelas=1, o usuário pode querer confirmar à vista)
        const selectorRow = document.createElement('div');
        selectorRow.id        = 'parcelas-selector-row';
        selectorRow.className = 'mb-3';

        const options = Array.from({ length: maxParcelas }, (_, i) => {
            const n = i + 1;
            return `<option value="${n}">${n}x de ${formatBRL(currentTotalLiquido / n)}</option>`;
        }).join('');

        selectorRow.innerHTML = `
            <label class="form-label fw-bold small">
                <i class="fa-solid fa-hashtag me-1"></i> Número de parcelas
            </label>
            <select id="parcelas-qty-select" class="form-select form-select-sm" style="max-width:240px;">
                ${options}
            </select>
            ${maxParcelas === 1 ? `<div class="text-warning small mt-1"><i class="fa-solid fa-triangle-exclamation me-1"></i>Cadastre mais parcelas em <a href="/payment/lista" target="_blank">Condições de Pagamento</a> para liberar parcelamento.</div>` : ''}
        `;

        const table = document.getElementById('installments-table');
        table?.parentElement?.insertBefore(selectorRow, table);

        document.getElementById('parcelas-qty-select')?.addEventListener('change', function () {
            renderInstallmentRows(parseInt(this.value), intervalo);
        });

        // Renderiza com a quantidade máxima selecionada por padrão
        renderInstallmentRows(maxParcelas > 1 ? maxParcelas : 1, intervalo);
        confirmBtn.disabled = false;

    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-danger text-center">Erro ao carregar parcelas.</td></tr>';
    }
}

function renderInstallmentRows(qty, intervalo) {
    const tbody   = document.getElementById('installments-tbody');
    const totalEl = document.getElementById('installments-total');
    if (!tbody) return;

    const parcelValue = currentTotalLiquido / qty;
    let soma = 0;

    tbody.innerHTML = '';
    for (let i = 0; i < qty; i++) {
        soma += parcelValue;
        const dueDate = new Date();
        dueDate.setDate(dueDate.getDate() + (intervalo || 30) * (i + 1));
        const dateStr = dueDate.toLocaleDateString('pt-BR');

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${i + 1}</td>
            <td>${dateStr}</td>
            <td class="text-end">${formatBRL(parcelValue)}</td>
        `;
        tbody.appendChild(tr);
    }

    if (totalEl) totalEl.textContent = formatBRL(soma);
}

function formatBRL(value) {
    return 'R$ ' + value.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

async function openPaymentModal(totalLiquido) {
    selectedPaymentTermId   = null;
    selectedPaymentTermName = '';
    currentTotalLiquido     = totalLiquido;

    const modalTotalEl = document.getElementById('modal-total-venda');
    if (modalTotalEl) modalTotalEl.textContent = totalLiquido.toFixed(2).replace('.', ',');

    const section = document.getElementById('installments-section');
    section?.classList.add('d-none');

    const confirmBtn = document.getElementById('confirm-payment-btn');
    if (confirmBtn) confirmBtn.disabled = true;

    await loadPaymentTerms();

    const modalEl = document.getElementById('modalPaymentTerms');
    const modal   = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

// ─── Finalizar Venda ──────────────────────────────────────────────────────────

async function finalizeSale() {
    if (saleItems.length === 0) {
        Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Adicione pelo menos um item à venda.' });
        return;
    }

    const clienteSelect = document.getElementById('id_cliente');
    if (!clienteSelect?.value) {
        Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Selecione um cliente antes de finalizar.' });
        return;
    }

    if (!currentSaleId) {
        Swal.fire({ icon: 'error', title: 'Erro', text: 'Não há venda criada. Adicione um item primeiro.' });
        return;
    }

    // Calcular totais
    const descPct      = parseFloat(document.getElementById('desconto')?.value  || 0) || 0;
    const acrPct       = parseFloat(document.getElementById('acrescimo')?.value || 0) || 0;
    const totalBruto   = saleItems.reduce((s, i) => s + i.total, 0);
    const valDesc      = (totalBruto * descPct)  / 100;
    const valAcr       = (totalBruto * acrPct)   / 100;
    const totalLiquido = totalBruto - valDesc + valAcr;

    // Abre o modal de condição de pagamento
    await openPaymentModal(totalLiquido);
}

async function confirmPaymentAndFinalize() {
    if (!selectedPaymentTermId) {
        Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Selecione uma condição de pagamento.' });
        return;
    }

    // Fechar modal
    const modalEl = document.getElementById('modalPaymentTerms');
    bootstrap.Modal.getOrCreateInstance(modalEl).hide();

    // Calcular totais novamente para garantir
    const descPct      = parseFloat(document.getElementById('desconto')?.value  || 0) || 0;
    const acrPct       = parseFloat(document.getElementById('acrescimo')?.value || 0) || 0;
    const totalBruto   = saleItems.reduce((s, i) => s + i.total, 0);
    const valDesc      = (totalBruto * descPct)  / 100;
    const valAcr       = (totalBruto * acrPct)   / 100;
    const totalLiquido = totalBruto - valDesc + valAcr;
    const observacao   = document.getElementById('observacao')?.value || '';

    // Atualizar venda no banco com estado VENDA
    const requests = new Requests();
    const fd       = new FormData();
    const parcelasQty = parseInt(document.getElementById('parcelas-qty-select')?.value || '1');

    fd.append('id',                currentSaleId);
    fd.append('total_bruto',       totalBruto);
    fd.append('total_liquido',     totalLiquido);
    fd.append('desconto',          valDesc);
    fd.append('acrescimo',         valAcr);
    fd.append('observacao',        observacao);
    fd.append('estado_venda',      'VENDA');
    fd.append('id_payment_terms',  selectedPaymentTermId);
    fd.append('num_parcelas',      parcelasQty);

    try {
        const result = await requests.setBody(fd).post('/sale/update');
        if (!result?.status) throw new Error(result?.msg || 'Erro ao finalizar venda.');

        clearSale();

        await Swal.fire({
            icon: 'success',
            title: 'Sucesso',
            text: `Venda finalizada com "${selectedPaymentTermName}"!`,
            confirmButtonColor: '#198754',
            confirmButtonText: 'OK',
        });

        window.location.href = '/sale/lista';

    } catch (e) {
        console.error('confirmPaymentAndFinalize:', e);
        Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro ao concluir: ' + e.message });
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
//  INICIALIZAÇÃO
// ═══════════════════════════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', function () {

    // Se estiver em modo edição, carrega id do hidden e busca os itens existentes
    if (saleIdInput?.value) {
        currentSaleId = parseInt(saleIdInput.value);
        updateSaleStatus();

        // Carrega itens já cadastrados na venda
        (async () => {
            try {
                const res  = await fetch(`/sale/${currentSaleId}/itens`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                const json = await res.json();
                if (json.status && Array.isArray(json.data)) {
                    // Limpa array e popula com os itens do servidor
                    saleItems.length = 0;
                    json.data.forEach(item => {
                        saleItems.push({
                            id_item_sale:   item.id,
                            id_produto:     item.id_produto,
                            nome_produto:   item.nome,
                            quantidade:     parseFloat(item.quantidade)     || 1,
                            preco_unitario: parseFloat(item.unitario_liquido || item.unitario_bruto) || 0,
                            total:          parseFloat(item.total_liquido   || item.total_bruto)    || 0,
                        });
                    });
                    renderItems();
                    updateTotals();
                }
            } catch (err) {
                console.error('Erro ao carregar itens da venda:', err);
            }
        })();
    }

    initCustomerSelect();
    initProductSelect();
    renderItems();
    updateSaleStatus();

    // ── Máscaras via Inputmask ────────────────────────────────────────────────
    if (typeof Inputmask !== 'undefined') {
        Inputmask('currency', {
            radixPoint: ',', groupSeparator: '.', allowMinus: false,
            prefix: 'R$ ', autoGroup: true, rightAlign: false,
            onBeforeMask: v => String(v).replace('.', ','),
        }).mask(inputUnitarioLiquido);

        Inputmask('decimal', {
            radixPoint: ',', groupSeparator: '.', allowMinus: false,
            autoGroup: true, rightAlign: false, digits: 4,
            onBeforeMask: v => String(v).replace('.', ','),
        }).mask(inputQuantidade);

        Inputmask('currency', {
            radixPoint: ',', groupSeparator: '.', allowMinus: false,
            prefix: 'R$ ', autoGroup: true, rightAlign: false,
            onBeforeMask: v => String(v).replace('.', ','),
        }).mask(inputValorTotal);
    }

    // Cálculo automático ao digitar
    inputUnitarioLiquido?.addEventListener('input', calcularTotal);
    inputQuantidade?.addEventListener('input', calcularTotal);
    calcularTotal();

    // Desconto / Acréscimo
    document.getElementById('desconto')?.addEventListener('input',   updateTotals);
    document.getElementById('acrescimo')?.addEventListener('input',  updateTotals);

    // ── Ao selecionar produto → preenche preço ────────────────────────────────
    $('#id_produto').on('select2:select', async function (e) {
        const productId = e.params.data.id;
        try {
            const res     = await fetch(`/sale/find-product/${productId}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const product = await res.json();
            if (product?.preco_venda) {
                inputUnitarioLiquido.value = product.preco_venda;
                if (inputQuantidade) inputQuantidade.value = '1,00';
                inputUnitarioLiquido.dispatchEvent(new Event('input'));
                inputQuantidade?.dispatchEvent(new Event('input'));
                inputQuantidade?.focus();
            }
        } catch (err) {
            console.error('Erro ao buscar produto:', err);
        }
    });

    // ── Formulário de item ────────────────────────────────────────────────────
    document.getElementById('item-sale-form')?.addEventListener('submit', async function (e) {
        e.preventDefault();

        const clienteId = document.getElementById('id_cliente')?.value ?? '';
        const productId = document.getElementById('id_produto')?.value ?? '';
        const quantity  = inputQuantidade?.value ?? '0';
        const unitPrice = inputUnitarioLiquido?.value ?? '0';

        if (!clienteId) {
            Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Selecione um cliente antes de adicionar itens.' });
            return;
        }

        const saleResult = await garantirVenda(clienteId);
        if (!saleResult.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: saleResult.msg });
            return;
        }

        if (!productId) {
            Swal.fire({ icon: 'info', title: 'Venda criada!', text: 'Agora selecione um produto para inserir.' });
            return;
        }

        let product;
        try {
            const res = await fetch(`/sale/find-product/${productId}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            product = await res.json();
        } catch (ex) {
            Swal.fire({ icon: 'error', title: 'Erro', text: 'Produto não encontrado.' });
            return;
        }

        if (!product || product.status === false) {
            Swal.fire({ icon: 'error', title: 'Erro', text: 'Produto não encontrado.' });
            return;
        }

        const inserted = await insertItem(product, currentSaleId, quantity, unitPrice);
        if (!inserted) return;

        // Reset do formulário de item
        this.reset();
        $('#id_produto').val(null).trigger('change');
        if (inputQuantidade) {
            inputQuantidade.value = '1,00';
            inputQuantidade.dispatchEvent(new Event('input'));
        }
        if (inputUnitarioLiquido) inputUnitarioLiquido.value = '';
        if (inputValorTotal)      inputValorTotal.value      = '';
        calcularTotal();
    });

    // ── Botão: Limpar Tudo ────────────────────────────────────────────────────
    document.getElementById('clear-sale')?.addEventListener('click', () => {
        Swal.fire({
            title: 'Limpar venda?',
            text: 'Todos os itens serão removidos da tela. Esta ação não exclui itens já salvos no banco.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, limpar',
            cancelButtonText: 'Cancelar',
        }).then(result => {
            if (result.isConfirmed) clearSale();
        });
    });

    // ── Botão: Finalizar Venda ────────────────────────────────────────────────
    document.getElementById('finalize-sale')?.addEventListener('click', finalizeSale);

    // ── Botão: Confirmar condição de pagamento no modal ───────────────────────
    document.getElementById('confirm-payment-btn')?.addEventListener('click', confirmPaymentAndFinalize);
});