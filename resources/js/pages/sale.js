import Requests from '../components/requests.js';

// ─── Estado global da venda ───────────────────────────────────────────────────

let currentSaleId   = null;
let currentClientId = null;

/** @type {Array<{id_item_sale: number, id_produto: number, nome_produto: string, quantidade: number, preco_unitario: number, total: number}>} */
const saleItems = [];

// ─── Referências DOM ──────────────────────────────────────────────────────────

const inputQuantidade      = document.getElementById('quantidade');
const inputUnitarioLiquido = document.getElementById('unitario_liquido');
const inputValorTotal      = document.getElementById('valor-total');
const saleIdInput          = document.getElementById('sale-id');

// ─── Utilitários de formatação ────────────────────────────────────────────────

function stringParaFloat(valor) {
    if (!valor) return 0;
    return parseFloat(
        valor.toString()
            .replace('R$', '')
            .replace(/\s/g, '')
            .replace('.', '')
            .replace(',', '.')
    ) || 0;
}

function floatParaBR(valor) {
    return valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// ─── Status da venda ──────────────────────────────────────────────────────────

function updateSaleStatus() {
    const badge = document.getElementById('sale-status');
    if (!badge) return;
    badge.textContent = currentSaleId ? `Em edição (Venda #${currentSaleId})` : 'Em edição';
}

// ─── Select2: Cliente ─────────────────────────────────────────────────────────

function initCustomerSelect() {
    const select = $('#id_cliente');
    if (!select.length) return;

    select.select2({
        theme: 'bootstrap-5',
        placeholder: 'Selecione um cliente',
        allowClear: true,
        language: 'pt-BR',
        ajax: {
            transport: async function (params, success, failure) {
                try {
                    const response = await fetch('/sale/find-customer', {
                        method: 'POST',
                        headers: { Accept: 'application/json' },
                        body: (() => {
                            const fd = new FormData();
                            fd.append('term',   params.data.q || '');
                            fd.append('limit',  50);
                            fd.append('offset', 0);
                            return fd;
                        })(),
                        credentials: 'same-origin',
                        cache: 'no-store',
                    });
                    const json = await response.json();
                    success({
                        results: (json.data || []).map(item => ({
                            id:   item.id,
                            text: `${item.nome}${item.cpf ? ' — ' + item.cpf : ''}`,
                        })),
                    });
                } catch (error) {
                    console.error(error);
                    failure(error);
                }
            },
            delay: 250,
        },
        minimumInputLength: 0,
    });
}

// ─── Select2: Produto ─────────────────────────────────────────────────────────

function initProductSelect() {
    const select = $('#id_produto');
    if (!select.length) return;

    select.select2({
        theme: 'bootstrap-5',
        placeholder: 'Buscar produto...',
        allowClear: true,
        language: 'pt-BR',
        ajax: {
            transport: async function (params, success, failure) {
                try {
                    const response = await fetch('/sale/find-product', {
                        method: 'POST',
                        headers: { Accept: 'application/json' },
                        body: (() => {
                            const fd = new FormData();
                            fd.append('term',   params.data.q || '');
                            fd.append('limit',  50);
                            fd.append('offset', 0);
                            return fd;
                        })(),
                        credentials: 'same-origin',
                        cache: 'no-store',
                    });
                    const json = await response.json();
                    success({
                        results: (json.data || []).map(item => ({
                            id:   item.id,
                            text: `${item.nome}${item.codigo_barra ? ' — Cód: ' + item.codigo_barra : ''}`,
                            preco_venda: item.preco_venda,
                        })),
                    });
                } catch (error) {
                    console.error(error);
                    failure(error);
                }
            },
            delay: 250,
        },
        minimumInputLength: 0,
    });
}

// ─── Cálculo do total do item ─────────────────────────────────────────────────

function calcularTotal() {
    try {
        const precoBruto = inputUnitarioLiquido?.inputmask
            ? inputUnitarioLiquido.inputmask.unmaskedvalue()
            : inputUnitarioLiquido?.value ?? '0';
        const qtdBruta = inputQuantidade?.inputmask
            ? inputQuantidade.inputmask.unmaskedvalue()
            : inputQuantidade?.value ?? '0';

        const preco = stringParaFloat(precoBruto);
        const qtd   = stringParaFloat(qtdBruta);
        const total = preco * qtd;

        if (inputValorTotal) {
            inputValorTotal.value = floatParaBR(total);
        }
    } catch (e) {
        console.error('Erro ao calcular total do item:', e);
    }
}

// ─── Renderização da tabela de itens ─────────────────────────────────────────

function renderItems() {
    const tbody = document.querySelector('#sale-items-table tbody');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (saleItems.length === 0) {
        tbody.innerHTML = `
            <tr id="empty-row">
                <td colspan="5" class="text-center text-muted py-4">
                    <i class="fa-solid fa-inbox fa-lg mb-1 d-block"></i>
                    Nenhum item adicionado.
                </td>
            </tr>`;
        updateTotals();
        return;
    }

    saleItems.forEach((item, index) => {
        const tr = document.createElement('tr');
        tr.dataset.index = index;
        tr.innerHTML = `
            <td>${item.nome_produto}</td>
            <td class="text-end">${floatParaBR(item.quantidade)}</td>
            <td class="text-end">R$ ${floatParaBR(item.preco_unitario)}</td>
            <td class="text-end">R$ ${floatParaBR(item.total)}</td>
            <td class="text-center"></td>
        `;

        const btnRemover = document.createElement('button');
        btnRemover.type      = 'button';
        btnRemover.className = 'btn btn-sm btn-danger';
        btnRemover.innerHTML = '<i class="fa-solid fa-trash"></i>';
        btnRemover.addEventListener('click', () => removeItem(index));
        tr.querySelector('td:last-child').appendChild(btnRemover);

        tbody.appendChild(tr);
    });

    updateTotals();
}

// ─── Atualiza totais laterais ─────────────────────────────────────────────────

function updateTotals() {
    const subtotal         = saleItems.reduce((sum, item) => sum + (item.total || 0), 0);
    const descontoPercent  = parseFloat(document.getElementById('desconto')?.value  || 0) || 0;
    const acrescimoPercent = parseFloat(document.getElementById('acrescimo')?.value || 0) || 0;
    const valorDesconto    = (subtotal * descontoPercent)  / 100;
    const valorAcrescimo   = (subtotal * acrescimoPercent) / 100;
    const totalLiquido     = subtotal - valorDesconto + valorAcrescimo;

    const elBruto   = document.getElementById('total_bruto');
    const elLiquido = document.getElementById('total_liquido');
    if (elBruto)   elBruto.textContent   = floatParaBR(subtotal);
    if (elLiquido) elLiquido.textContent = floatParaBR(totalLiquido);
}

// ─── Criar venda no banco (chamada única por sessão) ──────────────────────────

async function criarVenda(clienteId) {
    const id   = Number(clienteId) || null;
    const requests = new Requests();

    const fd = new FormData();
    if (id) fd.append('id_cliente', id);
    fd.append('total_bruto',   0);
    fd.append('total_liquido', 0);
    fd.append('desconto',      0);
    fd.append('acrescimo',     0);
    fd.append('observacao',    document.getElementById('observacao')?.value || '');
    fd.append('estado_venda',  'aberto');

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

// ─── Inserir item na venda ────────────────────────────────────────────────────

async function addItemToTable(productId, quantity, unitPrice) {
    const clienteSelect = document.getElementById('id_cliente');
    const clienteId     = clienteSelect?.value ?? '';

    // Busca dados completos do produto
    let product;
    try {
        const res = await fetch(`/sale/find-product/${productId}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        product = await res.json();
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Erro', text: 'Produto não encontrado.' });
        return false;
    }

    if (!product || product.status === false) {
        Swal.fire({ icon: 'error', title: 'Erro', text: 'Produto não encontrado.' });
        return false;
    }

    const qty   = stringParaFloat(quantity)  || 0;
    const price = stringParaFloat(unitPrice) || parseFloat(product.preco_venda) || 0;
    const total = parseFloat((qty * price).toFixed(4));

    if (!currentSaleId) {
        Swal.fire({ icon: 'error', title: 'Erro', text: 'Venda não criada. Selecione o cliente primeiro.' });
        return false;
    }

    // Persiste o item no banco
    const requests = new Requests();
    const fd = new FormData();
    fd.append('id_venda',         currentSaleId);
    fd.append('id_produto',       product.id);
    fd.append('nome',             product.nome);
    fd.append('descricao',        product.descricao || '');
    fd.append('quantidade',       qty);
    fd.append('total_bruto',      total);
    fd.append('unitario_bruto',   price);
    fd.append('total_liquido',    total);
    fd.append('unitario_liquido', price);
    fd.append('desconto',         0);
    fd.append('acrescimo',        0);

    let itemResult;
    try {
        itemResult = await requests.setBody(fd).post('/sale/item/insert');
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Erro', text: e.message });
        return false;
    }

    if (!itemResult?.status) {
        Swal.fire({ icon: 'error', title: 'Erro', text: itemResult?.msg || 'Não foi possível inserir o item.' });
        return false;
    }

    // Adiciona à lista local
    saleItems.unshift({
        id_item_sale:  itemResult.id,
        id_produto:    product.id,
        nome_produto:  product.nome,
        quantidade:    qty,
        preco_unitario: price,
        total,
    });

    renderItems();
    return true;
}

function removeItem(index) {
    if (index < 0 || index >= saleItems.length) return;
    saleItems.splice(index, 1);
    renderItems();
}

// ─── Limpar venda ─────────────────────────────────────────────────────────────

function clearSale() {
    saleItems.length = 0;
    currentSaleId    = null;
    currentClientId  = null;
    updateSaleStatus();

    const clienteEl = document.getElementById('id_cliente');
    if (clienteEl) $(clienteEl).val(null).trigger('change');

    const observacaoEl = document.getElementById('observacao');
    if (observacaoEl) observacaoEl.value = '';

    const descontoEl = document.getElementById('desconto');
    if (descontoEl) descontoEl.value = '0.00';

    const acrescimoEl = document.getElementById('acrescimo');
    if (acrescimoEl) acrescimoEl.value = '0.00';

    renderItems();
    updateTotals();
}

// ═══════════════════════════════════════════════════════════════════════════════
//  MODAL DE FINALIZAÇÃO
// ═══════════════════════════════════════════════════════════════════════════════

let _fsmSaleId     = null;
let _fsmTotal      = 0;
let _fsmPagamentos = [];

function fsmFmt(valor) {
    return valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function fsmParseInput(str) {
    return parseFloat((str || '0').replace(/\./g, '').replace(',', '.')) || 0;
}

function fsmUpdateTotals() {
    const totalPago = _fsmPagamentos.reduce((s, p) => s + p.valor, 0);
    const dif       = _fsmTotal - totalPago;

    document.getElementById('fsm-total-venda').textContent = fsmFmt(_fsmTotal);
    document.getElementById('fsm-total-pago').textContent  = fsmFmt(totalPago);

    const difEl       = document.getElementById('fsm-diferenca');
    difEl.textContent = fsmFmt(Math.abs(dif));
    difEl.className   = 'fw-bold fs-5 ' + (
        dif < -0.005 ? 'text-danger' :
        dif <  0.005 ? 'text-success' :
                       'text-warning'
    );

    document.getElementById('fsm-conclude-btn').disabled = dif > 0.005;
}

function fsmRenderList() {
    const tbody = document.getElementById('fsm-payments-tbody');
    const table = document.getElementById('fsm-payments-table');
    const empty = document.getElementById('fsm-empty-payments');
    tbody.innerHTML = '';

    if (_fsmPagamentos.length === 0) {
        table.style.display = 'none';
        empty.style.display = 'block';
        fsmUpdateTotals();
        return;
    }

    table.style.display = '';
    empty.style.display = 'none';

    _fsmPagamentos.forEach((p, idx) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="fw-semibold">${p.titulo}</td>
            <td class="text-muted small">${p.parcelaLabel || '—'}</td>
            <td class="text-end text-success fw-semibold">${fsmFmt(p.valor)}</td>
            <td class="text-center">
                <button class="btn btn-sm btn-outline-danger py-0 px-2 fsm-remove-btn"
                        data-idx="${idx}" title="Remover">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    tbody.querySelectorAll('.fsm-remove-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            _fsmPagamentos.splice(parseInt(btn.dataset.idx), 1);
            fsmRenderList();
        });
    });

    fsmUpdateTotals();
}

async function fsmLoadPaymentTerms() {
    const sel = document.getElementById('fsm-payment-term');
    sel.innerHTML = '<option value="">Carregando...</option>';
    try {
        const res   = await fetch('/sale/payment-terms', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const json  = await res.json();
        sel.innerHTML = '<option value="">Selecione...</option>';
        (json.data || []).forEach(t => {
            const opt       = document.createElement('option');
            opt.value       = t.id;
            opt.textContent = t.titulo + (t.codigo ? ` (${t.codigo})` : '');
            sel.appendChild(opt);
        });
    } catch (e) {
        sel.innerHTML = '<option value="">Erro ao carregar</option>';
        console.error('fsmLoadPaymentTerms:', e);
    }
}

async function fsmOnTermChange() {
    const termId      = document.getElementById('fsm-payment-term').value;
    const parcelasCol = document.getElementById('fsm-parcelas-col');
    const parcelaSel  = document.getElementById('fsm-parcela');
    const valorInput  = document.getElementById('fsm-valor');

    parcelasCol.style.display = 'none';
    parcelaSel.innerHTML      = '<option value="">Selecione...</option>';

    if (!termId) return;

    try {
        const res  = await fetch(`/sale/installments/${termId}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const json = await res.json();
        const rows = json.data || [];

        if (rows.length > 0) {
            parcelaSel.innerHTML = '<option value="">Selecione a parcela...</option>';
            rows.forEach(inst => {
                const opt           = document.createElement('option');
                opt.value           = inst.id;
                opt.dataset.parcela = inst.parcela;
                opt.textContent     = `${inst.parcela}x` + (inst.intervalo ? ` — ${inst.intervalo} dias` : '');
                parcelaSel.appendChild(opt);
            });
            parcelasCol.style.display = 'block';
            valorInput.value          = '';
        } else {
            parcelasCol.style.display = 'none';
            const totalPago = _fsmPagamentos.reduce((s, p) => s + p.valor, 0);
            const restante  = Math.max(0, _fsmTotal - totalPago);
            valorInput.value = restante > 0 ? restante.toFixed(2).replace('.', ',') : '';
            valorInput.focus();
        }
    } catch (e) {
        console.error('fsmOnTermChange:', e);
        parcelasCol.style.display = 'none';
    }
}

function fsmAddPayment() {
    const termSel    = document.getElementById('fsm-payment-term');
    const parcelaSel = document.getElementById('fsm-parcela');
    const valorInput = document.getElementById('fsm-valor');

    const termId  = termSel.value;
    const termTxt = termSel.selectedOptions[0]?.textContent || '';
    const valor   = fsmParseInput(valorInput.value);

    if (!termId) { alert('Selecione uma forma de pagamento.'); return; }
    if (valor <= 0) { alert('Informe um valor válido.'); valorInput.focus(); return; }

    const parcelasVisiveis = document.getElementById('fsm-parcelas-col').style.display !== 'none';
    let parcelaLabel = null;
    if (parcelasVisiveis) {
        if (!parcelaSel.value) { alert('Selecione a parcela.'); parcelaSel.focus(); return; }
        parcelaLabel = parcelaSel.selectedOptions[0]?.textContent || null;
    }

    _fsmPagamentos.push({ titulo: termTxt, parcelaLabel, valor });

    termSel.value = '';
    parcelaSel.innerHTML = '<option value="">Selecione...</option>';
    document.getElementById('fsm-parcelas-col').style.display = 'none';
    valorInput.value = '';

    fsmRenderList();
}

async function fsmOpen(saleId, totalLiquido, nomeCliente) {
    _fsmSaleId     = saleId;
    _fsmTotal      = parseFloat(totalLiquido) || 0;
    _fsmPagamentos = [];

    document.getElementById('fsm-cliente-nome').textContent =
        nomeCliente ? `Cliente: ${nomeCliente}` : `Venda #${saleId}`;

    document.getElementById('fsm-payment-term').value         = '';
    document.getElementById('fsm-parcela').innerHTML          = '<option value="">Selecione...</option>';
    document.getElementById('fsm-parcelas-col').style.display = 'none';
    document.getElementById('fsm-valor').value                = '';

    fsmRenderList();
    fsmUpdateTotals();
    await fsmLoadPaymentTerms();

    bootstrap.Modal.getOrCreateInstance(
        document.getElementById('finalizeSaleModal')
    ).show();
}

async function fsmConclude() {
    if (_fsmPagamentos.length === 0) {
        alert('Adicione pelo menos um pagamento antes de concluir.');
        return;
    }

    const btn     = document.getElementById('fsm-conclude-btn');
    btn.disabled  = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processando...';

    try {
        const descontoPct  = parseFloat(document.getElementById('desconto')?.value  || 0) || 0;
        const acrescimoPct = parseFloat(document.getElementById('acrescimo')?.value || 0) || 0;
        const totalBruto   = saleItems.reduce((s, i) => s + i.total, 0);
        const valDesc      = (totalBruto * descontoPct)  / 100;
        const valAcr       = (totalBruto * acrescimoPct) / 100;
        const totalLiquido = totalBruto - valDesc + valAcr;
        const observacao   = document.getElementById('observacao')?.value || '';

        const requests = new Requests();
        const fd = new FormData();
        fd.append('id',            _fsmSaleId);
        fd.append('total_bruto',   totalBruto);
        fd.append('total_liquido', totalLiquido);
        fd.append('desconto',      valDesc);
        fd.append('acrescimo',     valAcr);
        fd.append('observacao',    observacao);
        fd.append('estado_venda',  'finalizado');

        const result = await requests.setBody(fd).post('/sale/update');
        if (!result?.status) throw new Error(result?.msg || 'Erro ao finalizar venda.');

        bootstrap.Modal.getInstance(document.getElementById('finalizeSaleModal')).hide();
        clearSale();

        Swal.fire({
            icon: 'success',
            title: 'Venda Finalizada!',
            text: `Venda #${_fsmSaleId} concluída com sucesso.`,
            timer: 3000,
            timerProgressBar: true,
        });
    } catch (e) {
        console.error('fsmConclude:', e);
        Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro ao concluir: ' + e.message });
        btn.disabled  = false;
        btn.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i>Concluir Venda';
    }
}

function finalizeSale() {
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

    const nomeCliente  = clienteSelect.selectedOptions[0]?.text || '';
    const descPct      = parseFloat(document.getElementById('desconto')?.value  || 0) || 0;
    const acrPct       = parseFloat(document.getElementById('acrescimo')?.value || 0) || 0;
    const totalBruto   = saleItems.reduce((s, i) => s + i.total, 0);
    const valDesc      = (totalBruto * descPct)  / 100;
    const valAcr       = (totalBruto * acrPct)   / 100;
    const totalLiquido = totalBruto - valDesc + valAcr;

    fsmOpen(currentSaleId, totalLiquido, nomeCliente);
}

// ═══════════════════════════════════════════════════════════════════════════════
//  INICIALIZAÇÃO
// ═══════════════════════════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', function () {

    // Se estiver em modo edição, carrega id do hidden
    if (saleIdInput?.value) {
        currentSaleId = parseInt(saleIdInput.value);
        updateSaleStatus();
    }

    initCustomerSelect();
    initProductSelect();
    renderItems();
    updateSaleStatus();

    // Máscaras via Inputmask
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
    inputQuantidade?.addEventListener('input',      calcularTotal);
    calcularTotal();

    // Desconto / Acréscimo
    document.getElementById('desconto')?.addEventListener('input',  updateTotals);
    document.getElementById('acrescimo')?.addEventListener('input', updateTotals);

    // Ao selecionar produto no Select2 → preenche preço
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

    // Formulário de item
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

        const inserted = await addItemToTable(productId, quantity, unitPrice);
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

    // Botão: Limpar Tudo
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

    // Botão: Finalizar Venda
    document.getElementById('finalize-sale')?.addEventListener('click', finalizeSale);

    // ─── Binds do modal ───────────────────────────────────────────────────────

    document.getElementById('fsm-payment-term')
        ?.addEventListener('change', fsmOnTermChange);

    document.getElementById('fsm-add-payment')
        ?.addEventListener('click', fsmAddPayment);

    document.getElementById('fsm-valor')
        ?.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); fsmAddPayment(); }
        });

    // Máscara simples no campo valor do modal
    document.getElementById('fsm-valor')
        ?.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '');
            if (!v) { this.value = ''; return; }
            v = (parseInt(v) / 100).toFixed(2);
            this.value = v.replace('.', ',');
        });

    document.getElementById('fsm-conclude-btn')
        ?.addEventListener('click', fsmConclude);
});
