/**
 * sale.js
 * Orquestra a página de Venda:
 *   - Inicialização do Select2 (cliente e produto)
 *   - Criação/garantia da venda no banco
 *   - Formulário de item (delega inserção ao itemsale.js)
 *   - Finalização: atualiza estado para VENDA e redireciona para /payment/detalhes?id_sale=X
 */

import 'select2';
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

    // Confirmar finalização
    const confirm = await Swal.fire({
        icon: 'question',
        title: 'Finalizar Venda?',
        text: `A venda #${currentSaleId} será finalizada e você será redirecionado para cadastrar a forma de pagamento.`,
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fa-solid fa-check me-1"></i>Sim, finalizar',
        cancelButtonText: 'Cancelar',
    });

    if (!confirm.isConfirmed) return;

    // Calcular totais
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
    fd.append('id',            currentSaleId);
    fd.append('total_bruto',   totalBruto);
    fd.append('total_liquido', totalLiquido);
    fd.append('desconto',      valDesc);
    fd.append('acrescimo',     valAcr);
    fd.append('observacao',    observacao);
    fd.append('estado_venda',  'VENDA');

    try {
        const result = await requests.setBody(fd).post('/sale/update');
        if (!result?.status) throw new Error(result?.msg || 'Erro ao finalizar venda.');

        const saleId = currentSaleId;
        clearSale();

        // Modal de sucesso igual ao padrão do sistema
        await Swal.fire({
            icon: 'success',
            title: 'Sucesso',
            text: 'Salvo com sucesso!',
            confirmButtonColor: '#6c63ff',
            confirmButtonText: 'OK',
        });

        // Redirecionar para payment/detalhes com id_sale pré-selecionado
        window.location.href = `/payment/detalhes?id_sale=${saleId}`;

    } catch (e) {
        console.error('finalizeSale:', e);
        Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro ao concluir: ' + e.message });
    }
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
});
