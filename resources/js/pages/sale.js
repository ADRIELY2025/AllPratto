import { addSaleItem, initializeSaleItems, updateTotals, clearSaleItems, getSaleItems } from './ItemSale.js';

// ─── Referências DOM 
const inputQuantidade = document.getElementById('quantidade');
const inputUnitarioLiquido = document.getElementById('unitario_liquido');
const inputValorTotal = document.getElementById('valor-total');
let currentSaleId = null;
let currentSaleClientId = null;

function initializeCustomerSelect() {
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
                    const searchTerm = params.data.q || '';
                    const result = await window.api.customer.find({ term: searchTerm, limit: 50, offset: 0 });

                    success({
                        results: result.data.map(item => ({
                            id: item.id,
                            text: `${item.nome}${item.cpf ? ' - CPF: ' + item.cpf : ''}`
                        }))
                    });
                } catch (error) {
                    console.error(error);
                    failure(error);
                }
            },
            delay: 250
        },
        minimumInputLength: 0
    });
}

function initializeProductSelect() {
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
                    const searchTerm = params.data.q || '';
                    const result = await window.api.product.find({ term: searchTerm, limit: 50, offset: 0 });

                    success({
                        results: result.data.map(item => ({
                            id: item.id,
                            text: `${item.nome}${item.codigo_barra ? ' - Cód. Barras: ' + item.codigo_barra : ''}`
                        }))
                    });
                } catch (error) {
                    console.error(error);
                    failure(error);
                }
            },
            delay: 250
        },
        minimumInputLength: 0
    });
}

// ─── Funções de Cálculo 

function stringParaFloat(valor) {
    if (!valor) return 0;
    // Remove R$, espaços e ajusta pontos/vírgulas para o padrão matemático
    let limpo = valor.toString()
        .replace('R$', '')
        .replace('R$ ', '')
        .replace('.', '')
        .replace(',', '.');

    return parseFloat(limpo) || 0;
}

function floatParaString(valor) {
    return valor.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function updateSaleStatus() {
    const badge = document.getElementById('sale-status');
    if (!badge) return;
    badge.textContent = currentSaleId ? `Em edição (Venda #${currentSaleId})` : 'Em edição';
}

async function createSaleForClient(clienteId) {
    const id = Number(clienteId) || null;
    const normalizedClientId = id !== null ? String(id) : '';
    const result = await window.api.sale.insert({
        id_cliente: id,
        total_bruto: 0,
        total_liquido: 0,
        desconto: 0,
        acrescimo: 0,
        observacao: document.getElementById('observacao')?.value || ''
    });

    if (!result.status) {
        return { status: false, msg: result.error || 'Não foi possível criar a venda.' };
    }

    currentSaleId = result.id;
    currentSaleClientId = normalizedClientId;
    updateSaleStatus();
    return { status: true, id: currentSaleId };
}

async function ensureSaleExists(clienteId) {
    if (!clienteId) {
        return { status: false, msg: 'Selecione um cliente antes de adicionar itens.' };
    }

    if (currentSaleId) {
        const currentClient = currentSaleClientId ? String(currentSaleClientId) : '';
        if (currentClient && currentClient !== clienteId) {
            return { status: false, msg: 'O cliente da venda já foi definido. Limpe a venda para mudar de cliente.' };
        }
        return { status: true, id: currentSaleId };
    }

    return await createSaleForClient(clienteId);
}

function executarCalculo() {
    try {
        // Tenta pegar o valor "desmascarado" do Inputmask primeiro, se não conseguir, limpa a string
        const precoBruto = inputUnitarioLiquido.inputmask ? inputUnitarioLiquido.inputmask.unmaskedvalue() : inputUnitarioLiquido.value;
        const qtdBruta = inputQuantidade.inputmask ? inputQuantidade.inputmask.unmaskedvalue() : inputQuantidade.value;

        const preco = stringParaFloat(precoBruto);
        const qtd = stringParaFloat(qtdBruta);

        const total = preco * qtd;

        // Atualiza o campo valor-total
        if (inputValorTotal) {
            inputValorTotal.value = floatParaString(total);
        }

        console.log('Cálculo: Preço =', preco, 'Qtd =', qtd, 'Total =', total);
    } catch (e) {
        console.error("Erro ao calcular total:", e);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // 1. DISPARA O CARREGAMENTO
    initializeCustomerSelect();
    initializeProductSelect();
    initializeSaleItems();
    updateSaleStatus();

    // ─── Ouvintes de Evento para Cálculo
    if (inputUnitarioLiquido && inputQuantidade) {
        inputUnitarioLiquido.addEventListener('input', executarCalculo);
        inputQuantidade.addEventListener('input', executarCalculo);

        executarCalculo();
    }

    // ─── Listeners para Desconto e Acréscimo ---
    const descontoInput = document.getElementById('desconto');
    const acrescimoInput = document.getElementById('acrescimo');
    
    if (descontoInput) {
        descontoInput.addEventListener('input', () => updateTotals());
    }
    
    if (acrescimoInput) {
        acrescimoInput.addEventListener('input', () => updateTotals());
    }

    // ─── Configuração de Máscaras 
    if (typeof Inputmask !== 'undefined') {
        Inputmask("currency", {
            radixPoint: ",",
            groupSeparator: ".",
            allowMinus: false,
            prefix: "R$ ",
            autoGroup: true,
            rightAlign: false,
            onBeforeMask: function (value) {
                return String(value).replace(".", ",");
            },
        }).mask(inputUnitarioLiquido);

        Inputmask("decimal", {
            radixPoint: ",",
            groupSeparator: ".",
            allowMinus: false,
            autoGroup: true,
            rightAlign: false,
            digits: 4,
            onBeforeMask: function (value) {
                return String(value).replace(".", ",");
            },
        }).mask(inputQuantidade);

        Inputmask("currency", {
            radixPoint: ",",
            groupSeparator: ".",
            allowMinus: false,
            prefix: "R$ ",
            autoGroup: true,
            rightAlign: false,
            onBeforeMask: function (value) {
                return String(value).replace(".", ",");
            },
        }).mask(inputValorTotal);
    }

    // ─── Evento ao selecionar Produto
    const produtoSelect2 = $('#id_produto');
    produtoSelect2.on('select2:select', async function (e) {
        const productId = e.params.data.id;
        try {
            const response = await window.api.product.findById(productId);

            if (response && response.preco_venda) {
                // Preenche o valor vindo do banco
                inputUnitarioLiquido.value = response.preco_venda;

                if (inputQuantidade) {
                    inputQuantidade.value = '1,00';
                }

                // CRUCIAL: Dispara o evento 'input' para o Inputmask formatar
                // e o executarCalculo() ser chamado automaticamente
                inputUnitarioLiquido.dispatchEvent(new Event('input'));
                if (inputQuantidade) {
                    inputQuantidade.dispatchEvent(new Event('input'));
                    inputQuantidade.focus();
                }
            }
        } catch (err) {
            console.error("Erro ao buscar detalhes do produto:", err);
        }
    });

    // --- Modal de Itens ---
    const btnOpenForm = document.getElementById('btn-open-form');
    const itemModalElement = document.getElementById('itemModal');
    const itemModal = itemModalElement ? new bootstrap.Modal(itemModalElement) : null;

    if (btnOpenForm && itemModal) {
        btnOpenForm.addEventListener('click', () => itemModal.show());
    }

    // --- Adicionar Item À Tabela ---
    const itemForm = document.getElementById('item-sale-form');
    if (itemForm) {
        itemForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const clienteSelect = document.getElementById('id_cliente');
            const productSelect = document.getElementById('id_produto');
            const clienteId = clienteSelect?.value ?? '';
            const productId = productSelect?.value ?? '';
            const quantity = document.getElementById('quantidade').value;
            const unitPrice = document.getElementById('unitario_liquido').value;

            if (!clienteId) {
                alert('Selecione um cliente antes de adicionar itens.');
                return;
            }

            const saleResult = await ensureSaleExists(clienteId);
            if (!saleResult.status) {
                alert(saleResult.msg);
                return;
            }

            if (!productId) {
                alert('Venda criada com sucesso. Agora selecione um produto para inserir.');
                return;
            }

            const inserted = await addItemToTable(productId, quantity, unitPrice);
            if (!inserted) {
                return;
            }

            // Resetar o formulário para adicionar próximo item
            itemForm.reset();
            const produtoSelect2Reset = $('#id_produto');
            if (produtoSelect2Reset?.length) {
                produtoSelect2Reset.val(null).trigger('change');
            }
            if (inputQuantidade) {
                inputQuantidade.value = '1,00';
                inputQuantidade.dispatchEvent(new Event('input'));
            }
            if (inputUnitarioLiquido) {
                inputUnitarioLiquido.value = '';
            }
            if (inputValorTotal) {
                inputValorTotal.value = '';
            }
            executarCalculo();
        });
    }

    // --- Botão Limpar Tudo ---
    const btnClearSale = document.getElementById('clear-sale');
    if (btnClearSale) {
        btnClearSale.addEventListener('click', () => {
            clearSaleItems();
            currentSaleId = null;
            updateSaleStatus();
            document.getElementById('id_cliente').value = '';
            document.getElementById('observacao').value = '';
            document.getElementById('desconto').value = '0.00';
            document.getElementById('acrescimo').value = '0.00';
            updateTotals();
        });
    }

    // --- Botão Finalizar Venda ---
    const btnFinalizeSale = document.getElementById('finalize-sale');
    if (btnFinalizeSale) {
        btnFinalizeSale.addEventListener('click', () => {
            finalizeSale();
        });
    }
});

async function addItemToTable(productId, quantity, unitPrice) {
    const clienteSelect = document.getElementById('id_cliente');
    const clienteId = clienteSelect?.value ?? '';
    const clienteNome = clienteSelect?.selectedOptions?.[0]?.text || '';

    const product = await window.api.product.findById(productId);
    if (!product) {
        alert('Produto não encontrado. Tente novamente.');
        return false;
    }

    const qty = stringParaFloat(quantity) || 0;
    const price = stringParaFloat(unitPrice) || 0;
    const total = parseFloat((qty * price).toFixed(2));

    if (!currentSaleId) {
        alert('Erro interno: venda não foi criada. Selecione o cliente e tente novamente.');
        return false;
    }

    const itemData = {
        id: currentSaleId,
        id_produto: product.id,
        quantidade: qty,
        total_bruto: total,
        unitario_bruto: price,
        total_liquido: total,
        unitario_liquido: price,
        desconto: 0,
        acrescimo: 0,
        nome: product.nome,
        preco_unitario: price
    };

    const itemResult = await window.api.sale.insertItem(itemData);
    if (!itemResult.status) {
        alert(itemResult.msg || 'Não foi possível inserir o item na venda.');
        return false;
    }

    const item = {
        id_produto: product.id,
        nome_produto: product.nome,
        id_cliente: clienteId,
        nome_cliente: clienteNome,
        nome_grupo: product.grupo || '-',
        quantidade: qty,
        preco_unitario: price,
        total: total
    };

    addSaleItem(item);
    return true;
}

// ═══════════════════════════════════════════════════════════════════
//  MODAL DE FINALIZAÇÃO DE VENDA
//  Substitua a função finalizeSale() no sale.js por este bloco inteiro
// ═══════════════════════════════════════════════════════════════════

// ── Estado do modal ──────────────────────────────────────────────────
let _fsmSaleId      = null;
let _fsmTotalVenda  = 0;
let _fsmPagamentos  = [];   // { titulo, parcelaLabel, valor }

// ── Utilitários de formatação ────────────────────────────────────────
function fsmFmt(valor) {
    return valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function fsmParseInput(str) {
    // Aceita "1.234,56" ou "1234.56"
    return parseFloat(
        (str || '0')
            .replace(/\./g, '')
            .replace(',', '.')
    ) || 0;
}

// ── Atualiza barra de totais ─────────────────────────────────────────
function fsmUpdateTotals() {
    const totalPago = _fsmPagamentos.reduce((s, p) => s + p.valor, 0);
    const dif       = _fsmTotalVenda - totalPago;

    document.getElementById('fsm-total-venda').textContent = fsmFmt(_fsmTotalVenda);
    document.getElementById('fsm-total-pago').textContent  = fsmFmt(totalPago);

    const difEl = document.getElementById('fsm-diferenca');
    difEl.textContent = fsmFmt(Math.abs(dif));
    difEl.className   = 'fw-bold fs-5 ' + (
        dif < -0.005 ? 'text-danger' :   // pago a mais
        dif <  0.005 ? 'text-success' :  // zerado ✓
                       'text-warning'    // ainda falta
    );

    // Libera "Concluir" apenas quando diferença ≤ 0 (zerado ou troco)
    document.getElementById('fsm-conclude-btn').disabled = dif > 0.005;
}

// ── Renderiza lista de pagamentos ────────────────────────────────────
function fsmRenderList() {
    const tbody   = document.getElementById('fsm-payments-tbody');
    const table   = document.getElementById('fsm-payments-table');
    const empty   = document.getElementById('fsm-empty-payments');

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
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    // Listeners de remoção
    tbody.querySelectorAll('.fsm-remove-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            _fsmPagamentos.splice(parseInt(btn.dataset.idx), 1);
            fsmRenderList();
        });
    });

    fsmUpdateTotals();
}

// ── Carrega formas de pagamento no select ────────────────────────────
async function fsmLoadPaymentTerms() {
    const sel = document.getElementById('fsm-payment-term');
    sel.innerHTML = '<option value="">Carregando...</option>';

    try {
        // Usa a rota IPC já existente: paymentTerms:findAll → PaymentTerms.findAll()
        const terms = await window.api.paymentTerms.findAll();
        sel.innerHTML = '<option value="">Selecione...</option>';
        (terms || []).forEach(t => {
            const opt = document.createElement('option');
            opt.value       = t.id;
            opt.textContent = t.titulo + (t.codigo ? ` (${t.codigo})` : '');
            sel.appendChild(opt);
        });
    } catch (e) {
        sel.innerHTML = '<option value="">Erro ao carregar</option>';
        console.error('fsmLoadPaymentTerms:', e);
    }
}

// ── Ao trocar a forma de pagamento ───────────────────────────────────
async function fsmOnTermChange() {
    const termId      = document.getElementById('fsm-payment-term').value;
    const parcelasCol = document.getElementById('fsm-parcelas-col');
    const parcelaSel  = document.getElementById('fsm-parcela');
    const valorInput  = document.getElementById('fsm-valor');

    // Esconde parcelas enquanto carrega
    parcelasCol.style.display = 'none';
    parcelaSel.innerHTML      = '<option value="">Selecione...</option>';

    if (!termId) return;

    try {
        // Usa a rota IPC já existente: paymentTerms:findInstallments
        const result = await window.api.paymentTerms.findInstallments(termId);
        const rows   = result?.data || result || [];

        if (rows.length > 0) {
            // Forma COM parcelamento (cartão, etc.) → mostra select de parcelas
            parcelaSel.innerHTML = '<option value="">Selecione a parcela...</option>';
            rows.forEach(inst => {
                const opt = document.createElement('option');
                opt.value = inst.id;
                opt.dataset.parcela   = inst.parcela;
                opt.dataset.intervalo = inst.intervalo || 0;
                opt.textContent = `${inst.parcela}x` + (inst.intervalo ? ` — ${inst.intervalo} dias` : '');
                parcelaSel.appendChild(opt);
            });
            parcelasCol.style.display = 'block';
            valorInput.value = '';
        } else {
            // Forma SEM parcelamento (dinheiro, pix, etc.) → esconde parcelas
            // e sugere o valor restante automaticamente
            parcelasCol.style.display = 'none';
            const totalPago  = _fsmPagamentos.reduce((s, p) => s + p.valor, 0);
            const restante   = Math.max(0, _fsmTotalVenda - totalPago);
            valorInput.value = restante > 0
                ? restante.toFixed(2).replace('.', ',')
                : '';
            valorInput.focus();
        }
    } catch (e) {
        console.error('fsmOnTermChange:', e);
        parcelasCol.style.display = 'none';
    }
}

// ── Máscara simples de valor monetário ──────────────────────────────
function fsmMaskValor(input) {
    input.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '');
        if (!v) { this.value = ''; return; }
        v = (parseInt(v) / 100).toFixed(2);
        this.value = v.replace('.', ',');
    });
}

// ── Adiciona pagamento à lista ────────────────────────────────────────
function fsmAddPayment() {
    const termSel    = document.getElementById('fsm-payment-term');
    const parcelaSel = document.getElementById('fsm-parcela');
    const valorInput = document.getElementById('fsm-valor');

    const termId    = termSel.value;
    const termTxt   = termSel.selectedOptions[0]?.textContent || '';
    const valor     = fsmParseInput(valorInput.value);

    if (!termId) {
        alert('Selecione uma forma de pagamento.');
        return;
    }
    if (valor <= 0) {
        alert('Informe um valor válido.');
        valorInput.focus();
        return;
    }

    // Verifica se parcelas estão visíveis e se foi selecionada
    const parcelasVisiveis = document.getElementById('fsm-parcelas-col').style.display !== 'none';
    let parcelaLabel = null;
    if (parcelasVisiveis) {
        if (!parcelaSel.value) {
            alert('Selecione a parcela.');
            parcelaSel.focus();
            return;
        }
        parcelaLabel = parcelaSel.selectedOptions[0]?.textContent || null;
    }

    _fsmPagamentos.push({
        titulo: termTxt,
        parcelaLabel,
        valor
    });

    // Limpa campos para próximo pagamento
    termSel.value                                              = '';
    parcelaSel.innerHTML                                       = '<option value="">Selecione...</option>';
    document.getElementById('fsm-parcelas-col').style.display = 'none';
    valorInput.value                                           = '';

    fsmRenderList();
}

// ── Abre o modal e inicializa ─────────────────────────────────────────
async function fsmOpen(saleId, totalLiquido, nomeCliente) {
    _fsmSaleId     = saleId;
    _fsmTotalVenda = parseFloat(totalLiquido) || 0;
    _fsmPagamentos = [];

    // Seta nome do cliente no header
    document.getElementById('fsm-cliente-nome').textContent =
        nomeCliente ? `Cliente: ${nomeCliente}` : `Venda #${saleId}`;

    // Limpa estado visual
    document.getElementById('fsm-payment-term').value         = '';
    document.getElementById('fsm-parcela').innerHTML          = '<option value="">Selecione...</option>';
    document.getElementById('fsm-parcelas-col').style.display = 'none';
    document.getElementById('fsm-valor').value                = '';

    fsmRenderList(); // renderiza lista vazia
    fsmUpdateTotals();
    await fsmLoadPaymentTerms();

    bootstrap.Modal.getOrCreateInstance(
        document.getElementById('finalizeSaleModal')
    ).show();
}

// ── Conclui a venda ───────────────────────────────────────────────────
async function fsmConclude() {
    if (_fsmPagamentos.length === 0) {
        alert('Adicione pelo menos um pagamento antes de concluir.');
        return;
    }

    const btn = document.getElementById('fsm-conclude-btn');
    btn.disabled     = true;
    btn.innerHTML    = '<span class="spinner-border spinner-border-sm me-2"></span>Processando...';

    try {
        // Recalcula totais finais
        const totalPago    = _fsmPagamentos.reduce((s, p) => s + p.valor, 0);
        const observacao   = document.getElementById('observacao')?.value || '';
        const descontoPct  = parseFloat(document.getElementById('desconto')?.value  || 0) || 0;
        const acrescimoPct = parseFloat(document.getElementById('acrescimo')?.value || 0) || 0;
        const totalBruto   = getSaleItems().reduce((s, i) => s + i.total, 0);
        const valDesc      = (totalBruto * descontoPct)  / 100;
        const valAcr       = (totalBruto * acrescimoPct) / 100;
        const totalLiquido = totalBruto - valDesc + valAcr;

        // Atualiza a venda com totais finais e marca como finalizada
        const result = await window.api.sale.update(_fsmSaleId, {
            total_bruto:   totalBruto,
            total_liquido: totalLiquido,
            desconto:      valDesc,
            acrescimo:     valAcr,
            observacao,
            estado_venda: 'finalizado'   // enum stock_movement_venda
        });

        if (!result.status) {
            throw new Error(result.error || result.msg || 'Erro ao finalizar venda.');
        }

        // Fecha modal e limpa tela
        bootstrap.Modal.getInstance(document.getElementById('finalizeSaleModal')).hide();

        clearSaleItems();
        currentSaleId = null;
        updateSaleStatus();

        const idCliente = document.getElementById('id_cliente');
        if (idCliente) { $(idCliente).val(null).trigger('change'); }

        document.getElementById('observacao').value  = '';
        document.getElementById('desconto').value    = '0.00';
        document.getElementById('acrescimo').value   = '0.00';
        updateTotals();

        alert(`Venda #${_fsmSaleId} finalizada com sucesso!`);

    } catch (e) {
        console.error('fsmConclude:', e);
        alert('Erro ao concluir: ' + e.message);
        btn.disabled  = false;
        btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Concluir Venda';
    }
}

function finalizeSale() {
    const items = getSaleItems();

    if (items.length === 0) {
        alert('Adicione pelo menos um item à venda.');
        return;
    }

    const clienteSelect = document.getElementById('id_cliente');
    const clienteId     = clienteSelect?.value;

    if (!clienteId) {
        alert('Selecione um cliente antes de finalizar a venda.');
        return;
    }

    if (!currentSaleId) {
        alert('Não há venda criada. Adicione um item primeiro.');
        return;
    }

    // Pega o nome já exibido no Select2
    const nomeCliente = clienteSelect?.selectedOptions?.[0]?.text || '';

    // Calcula total líquido para exibir no modal
    const descPct      = parseFloat(document.getElementById('desconto')?.value  || 0) || 0;
    const acrPct       = parseFloat(document.getElementById('acrescimo')?.value || 0) || 0;
    const totalBruto   = items.reduce((s, i) => s + i.total, 0);
    const valDesc      = (totalBruto * descPct)  / 100;
    const valAcr       = (totalBruto * acrPct)   / 100;
    const totalLiquido = totalBruto - valDesc + valAcr;

    fsmOpen(currentSaleId, totalLiquido, nomeCliente);
}

// ── Binds de eventos do modal ────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {

    // Troca de forma de pagamento
    document.getElementById('fsm-payment-term')
        ?.addEventListener('change', fsmOnTermChange);

    // Botão Adicionar
    document.getElementById('fsm-add-payment')
        ?.addEventListener('click', fsmAddPayment);

    // Enter no campo valor
    document.getElementById('fsm-valor')
        ?.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); fsmAddPayment(); }
        });

    // Máscara no campo valor
    const valorEl = document.getElementById('fsm-valor');
    if (valorEl) fsmApplyMask(valorEl);

    // Botão Concluir
    document.getElementById('fsm-conclude-btn')
        ?.addEventListener('click', fsmConclude);
});
