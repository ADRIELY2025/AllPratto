import 'select2';
import Requests from '../components/requests.js';

// ─── Estado do carrinho ───────────────────────────────────────────────────────

const carrinho = [];   // [{ id, nome, preco, quantidade, subtotal }]
let paymentTerms = [];
let selectedPaymentTermId = null;
let selectedPaymentTermCode = '';
const NO_INSTALLMENT_CODES = ['01', '04', '17'];

// ─── Utilitários ─────────────────────────────────────────────────────────────

function strParaFloat(v) {
    if (!v && v !== 0) return 0;
    let s = v.toString().trim().replace(/R\$\s*/g, '').trim();
    if (/^-?\d+(\.\d+)?$/.test(s)) return parseFloat(s) || 0;
    s = s.replace(/\./g, '').replace(',', '.');
    return parseFloat(s) || 0;
}

function floatParaBR(v) {
    return Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function maskDecimal(input) {
    input.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '');
        v = (parseInt(v || '0', 10) / 100).toFixed(2);
        this.value = v.replace('.', ',');
    });
}

// ─── Select2: Cliente ─────────────────────────────────────────────────────────

function initClienteSelect() {
    $('#id_cliente').select2({
        theme: 'bootstrap-5',
        placeholder: 'Buscar cliente pelo nome ou CPF...',
        allowClear: true,
        language: 'pt-BR',
        minimumInputLength: 0,
        ajax: {
            url: '/sale/find-customer',
            type: 'POST',
            delay: 250,
            cache: false,
            data: params => ({ term: params.term || '', limit: 50, offset: 0 }),
            processResults: json => ({
                results: (json.data || []).map(item => ({
                    id: item.id,
                    text: '#' + item.id + ' — ' + item.nome + (item.cpf ? ' (' + item.cpf + ')' : ''),
                })),
            }),
        },
    });

    $('#id_cliente').on('select2:select', function (e) {
        const idCliente = e.params.data.id;
        carregarEnderecosCliente(idCliente);
    });

    $('#id_cliente').on('select2:clear', function () {
        limparEnderecos();
    });

    document.getElementById('id_endereco_salvo').addEventListener('change', function () {
        if (!this.value) {
            limparCamposEndereco();
            return;
        }

        const selected = this.options[this.selectedIndex];
        preencherCamposEndereco({
            logradouro: selected.dataset.logradouro || '',
            numero: selected.dataset.numero || '',
            complemento: selected.dataset.complemento || '',
            bairro: selected.dataset.bairro || '',
            cidade: selected.dataset.cidade || '',
            cep: selected.dataset.cep || '',
            referencia: selected.dataset.referencia || '',
        });
    });
}

const PAYMENT_METHOD_TERM_KEY = {
    dinheiro: ['dinheiro', '01', 'din'],
    pix:      ['pix', '17'],
    credito:  ['credito', 'cartao de credito', 'cartão de crédito', '03'],
    debito:   ['debito', 'cartao de debito', 'cartão de débito', '04'],
};

function findPaymentTermByKey(key) {
    const normalized = key?.toString().trim().toLowerCase();
    if (!normalized) return null;

    const exact = paymentTerms.find(term => {
        const matches = [term.codigo, term.titulo, term.atalho]
            .filter(Boolean)
            .map(value => value.toString().trim().toLowerCase());
        return matches.some(value => value.includes(normalized) || normalized.includes(value));
    });
    if (exact) return exact;

    const fallbackKeys = PAYMENT_METHOD_TERM_KEY[normalized] || [normalized];
    return paymentTerms.find(term => {
        const matches = [term.codigo, term.titulo, term.atalho]
            .filter(Boolean)
            .map(value => value.toString().trim().toLowerCase());
        return fallbackKeys.some(keyValue => matches.includes(keyValue));
    }) || null;
}

async function loadPaymentTerms() {
    try {
        const requests = new Requests();
        const response = await requests.get('/sale/payment-terms');
        if (!response?.status || !Array.isArray(response.data)) {
            return;
        }
        paymentTerms = response.data;
    } catch (error) {
        console.error('Erro ao carregar condições de pagamento:', error);
    }
}

function setPaymentTerm(term) {
    selectedPaymentTermId = term?.id ? parseInt(term.id, 10) : null;
    selectedPaymentTermCode = term?.codigo ? term.codigo.toString().trim().toLowerCase() : '';
    document.getElementById('id_payment_terms').value = selectedPaymentTermId || '';
}

async function selectPaymentMethod(paymentValue) {
    if (!paymentTerms.length) {
        await loadPaymentTerms();
    }

    const termo = findPaymentTermByKey(paymentValue);
    setPaymentTerm(termo);

    const selectParcelas = document.getElementById('num_parcelas');
    if (!selectParcelas) return;

    const termId = termo?.id;
    const isCredito = paymentValue === 'credito';
    const isNoInstallment = termo?.codigo && NO_INSTALLMENT_CODES.includes(termo.codigo.toString().trim());

    if (termId) {
        try {
            const requests = new Requests();
            const response = await requests.get(`/sale/installments/${termId}`);
            if (response?.status && Array.isArray(response.data)) {
                const maxParcelas = Math.max(...response.data.map(i => parseInt(i.parcela, 10) || 1));
                selectParcelas.innerHTML = Array.from({ length: maxParcelas }, (_, index) => {
                    const n = index + 1;
                    return `<option value="${n}">${n}x</option>`;
                }).join('');
                selectParcelas.value = '1';
                selectParcelas.disabled = !isCredito || isNoInstallment;
                return;
            }
        } catch (error) {
            console.error('Erro ao carregar parcelas:', error);
        }
    }

    // Fallback: use default one-installment behavior
    selectParcelas.innerHTML = '<option value="1">1x à vista</option>';
    selectParcelas.value = '1';
    selectParcelas.disabled = !isCredito || isNoInstallment;
}

async function carregarEnderecosCliente(idCliente) {
    const select = document.getElementById('id_endereco_salvo');
    select.innerHTML = '<option value="">Novo endereço</option>';

    if (!idCliente) {
        return;
    }

    try {
        const requests = new Requests();
        const response = await requests.get(`/cliente/enderecos/${idCliente}`);

        if (!response?.status || !Array.isArray(response.data)) {
            return;
        }

        response.data.forEach(endereco => {
            const option = document.createElement('option');
            option.value = endereco.id;
            option.textContent = `${endereco.logradouro || ''}${endereco.numero ? ', ' + endereco.numero : ''}${endereco.bairro ? ' - ' + endereco.bairro : ''}`.trim();
            option.dataset.logradouro = endereco.logradouro || '';
            option.dataset.numero = endereco.numero || '';
            option.dataset.complemento = endereco.complemento || '';
            option.dataset.bairro = endereco.bairro || '';
            option.dataset.cidade = endereco.cidade || '';
            option.dataset.cep = endereco.cep || '';
            option.dataset.referencia = endereco.referencia || '';
            select.appendChild(option);
        });
    } catch (error) {
        console.error(error);
    }
}

function limparEnderecos() {
    const select = document.getElementById('id_endereco_salvo');
    select.innerHTML = '<option value="">Novo endereço</option>';
    limparCamposEndereco();
}

function limparCamposEndereco() {
    document.getElementById('endereco_rua').value = '';
    document.getElementById('endereco_numero').value = '';
    document.getElementById('endereco_complemento').value = '';
    document.getElementById('endereco_bairro').value = '';
    document.getElementById('endereco_cidade').value = '';
    document.getElementById('endereco_cep').value = '';
    document.getElementById('endereco_referencia').value = '';
}

function preencherCamposEndereco(endereco) {
    document.getElementById('endereco_rua').value = endereco.logradouro || '';
    document.getElementById('endereco_numero').value = endereco.numero || '';
    document.getElementById('endereco_complemento').value = endereco.complemento || '';
    document.getElementById('endereco_bairro').value = endereco.bairro || '';
    document.getElementById('endereco_cidade').value = endereco.cidade || '';
    document.getElementById('endereco_cep').value = endereco.cep || '';
    document.getElementById('endereco_referencia').value = endereco.referencia || '';
}

// ─── Select2: Produto ─────────────────────────────────────────────────────────

function initProdutoSelect() {
    const $select = $('#id_produto');
    if (!$select.length) return;

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
            data: params => ({ term: params.term || '', limit: 100, offset: 0 }),
            processResults: json => ({
                results: (json.data || []).map(item => ({
                    id: item.id,
                    text: item.nome + (item.codigo ? ' [' + item.codigo + ']' : ''),
                    preco: item.preco_venda ?? item.preco ?? 0,
                })),
            }),
        },
    });

    // Ao selecionar produto, preenche preço
    $select.on('select2:select', function (e) {
        const data  = e.params.data;
        const preco = strParaFloat(data.preco ?? 0);
        const qty   = strParaFloat(document.getElementById('quantidade').value || '1');

        document.getElementById('unitario_liquido').value = floatParaBR(preco);
        document.getElementById('valor-total').value      = floatParaBR(preco * qty);
    });

    $select.on('select2:clear', function () {
        document.getElementById('unitario_liquido').value = '';
        document.getElementById('valor-total').value      = '';
    });
}

// ─── Quantidade: recalcula total ao digitar ───────────────────────────────────

function initQuantidade() {
    const inputQtd   = document.getElementById('quantidade');
    const inputPreco = document.getElementById('unitario_liquido');
    const inputTotal = document.getElementById('valor-total');

    maskDecimal(inputQtd);

    inputQtd.addEventListener('input', () => {
        const qty   = strParaFloat(inputQtd.value);
        const preco = strParaFloat(inputPreco.value);
        inputTotal.value = floatParaBR(preco * qty);
    });
}

// ─── Carrinho ─────────────────────────────────────────────────────────────────

function adicionarItem() {
    const $select = $('#id_produto');
    const selected = $select.select2('data')[0];

    if (!selected || !selected.id) {
        Swal.fire({ icon: 'warning', title: 'Selecione um produto', timer: 1800, timerProgressBar: true });
        return;
    }

    const preco = strParaFloat(document.getElementById('unitario_liquido').value);
    const qty   = strParaFloat(document.getElementById('quantidade').value || '1');

    if (qty <= 0) {
        Swal.fire({ icon: 'warning', title: 'Quantidade inválida', timer: 1800, timerProgressBar: true });
        return;
    }

    // Se já existe no carrinho, soma quantidade
    const idx = carrinho.findIndex(i => i.id === parseInt(selected.id));
    if (idx >= 0) {
        carrinho[idx].quantidade += qty;
        carrinho[idx].subtotal    = round2(carrinho[idx].preco * carrinho[idx].quantidade);
    } else {
        carrinho.push({
            id:         parseInt(selected.id),
            nome:       selected.text,
            preco:      preco,
            quantidade: qty,
            subtotal:   round2(preco * qty),
        });
    }

    renderCarrinho();
    limparCamposItem();
}

function removerItem(idx) {
    carrinho.splice(idx, 1);
    renderCarrinho();
}

function round2(v) { return Math.round(v * 100) / 100; }

function renderCarrinho() {
    const tbody = document.getElementById('tbody-itens');
    tbody.innerHTML = '';

    let subtotal = 0;
    carrinho.forEach((item, idx) => {
        subtotal += item.subtotal;
        tbody.innerHTML += `
            <tr>
                <td>${item.nome}</td>
                <td class="text-center">${floatParaBR(item.quantidade)}</td>
                <td class="text-end">R$ ${floatParaBR(item.preco)}</td>
                <td class="text-end">R$ ${floatParaBR(item.subtotal)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger py-0"
                        onclick="removerItemCarrinho(${idx})">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </td>
            </tr>`;
    });

    document.getElementById('total-itens').textContent = 'R$ ' + floatParaBR(subtotal);
    document.getElementById('card-itens').style.display = carrinho.length ? '' : 'none';

    atualizarResumo();
}

function limparCamposItem() {
    $('#id_produto').val(null).trigger('change');
    document.getElementById('quantidade').value      = '1,00';
    document.getElementById('unitario_liquido').value = '';
    document.getElementById('valor-total').value      = '';
}

// ─── Resumo lateral ───────────────────────────────────────────────────────────

function atualizarResumo() {
    const subtotal = carrinho.reduce((s, i) => s + i.subtotal, 0);
    const taxa     = strParaFloat(document.getElementById('taxa_entrega').value || '0');
    const total    = subtotal + taxa;

    document.getElementById('resumo-subtotal').textContent = 'R$ ' + floatParaBR(subtotal);
    document.getElementById('resumo-taxa').textContent     = 'R$ ' + floatParaBR(taxa);
    document.getElementById('resumo-total').textContent    = 'R$ ' + floatParaBR(total);

    calcularTroco();
}

// ─── Forma de pagamento ───────────────────────────────────────────────────────

function initPagamento() {
    document.querySelectorAll('.pagamento-btn').forEach(btn => {
        btn.addEventListener('click', async function () {
            document.querySelectorAll('.pagamento-btn').forEach(b => {
                b.classList.remove('btn-primary', 'btn-success', 'btn-info', 'btn-warning');
                b.classList.add('btn-outline-secondary');
            });
            this.classList.remove('btn-outline-secondary');
            this.classList.add('btn-primary');
            document.getElementById('pagamento').value = this.dataset.valor;

            await selectPaymentMethod(this.dataset.valor);

            const blocoTroco = document.getElementById('bloco-troco');
            if (this.dataset.valor === 'dinheiro') {
                blocoTroco.classList.remove('d-none');
            } else {
                blocoTroco.classList.add('d-none');
                document.getElementById('troco_para').value = '';
                document.getElementById('troco-calculado').textContent = '';
            }
        });
    });
}

function calcularTroco() {
    const pagamento = document.getElementById('pagamento').value;
    if (pagamento !== 'dinheiro') return;

    const subtotal = carrinho.reduce((s, i) => s + i.subtotal, 0);
    const taxa     = strParaFloat(document.getElementById('taxa_entrega').value || '0');
    const total    = subtotal + taxa;
    const trocoPara = strParaFloat(document.getElementById('troco_para').value || '0');

    const elCalc = document.getElementById('troco-calculado');
    if (trocoPara > 0) {
        const troco = trocoPara - total;
        if (troco >= 0) {
            elCalc.textContent = `Troco: R$ ${floatParaBR(troco)}`;
            elCalc.className   = 'form-text text-success fw-bold';
        } else {
            elCalc.textContent = 'Valor informado menor que o total!';
            elCalc.className   = 'form-text text-danger';
        }
    } else {
        elCalc.textContent = '';
    }
}

// ─── Tipo de entrega ──────────────────────────────────────────────────────────

function initTipoEntrega() {
    document.querySelectorAll('input[name="tipo_entrega"]').forEach(radio => {
        radio.addEventListener('change', function () {
            const blocoTaxa     = document.getElementById('bloco-taxa');
            const blocoEndereco = document.getElementById('bloco-endereco');
            if (this.value === 'retirada') {
                blocoTaxa.style.display     = 'none';
                blocoEndereco.style.display = 'none';
                document.getElementById('taxa_entrega').value = '0,00';
                atualizarResumo();
            } else {
                blocoTaxa.style.display     = '';
                blocoEndereco.style.display = '';
            }
        });
    });
}

// ─── Finalizar pedido ─────────────────────────────────────────────────────────

async function finalizarPedido() {
    // Validações
    const idCliente = $('#id_cliente').val();
    if (!idCliente) {
        Swal.fire({ icon: 'warning', title: 'Selecione o cliente', timer: 2000, timerProgressBar: true });
        return;
    }

    if (carrinho.length === 0) {
        Swal.fire({ icon: 'warning', title: 'Adicione ao menos um item', timer: 2000, timerProgressBar: true });
        return;
    }

    const pagamento = document.getElementById('pagamento').value;
    if (!pagamento) {
        Swal.fire({ icon: 'warning', title: 'Selecione a forma de pagamento', timer: 2000, timerProgressBar: true });
        return;
    }

    const tipoEntregaEl = document.querySelector('input[name="tipo_entrega"]:checked');
    if (!tipoEntregaEl) {
        Swal.fire({ icon: 'warning', title: 'Selecione o tipo de entrega', timer: 2000, timerProgressBar: true });
        return;
    }
    const tipoEntrega = tipoEntregaEl.value;
    const taxa        = strParaFloat(document.getElementById('taxa_entrega').value || '0');
    const parcelas    = parseInt(document.getElementById('num_parcelas').value || '1', 10);
    const idEndereco  = document.getElementById('id_endereco_salvo').value || '';
    const salvarEndereco = document.getElementById('salvar_endereco').checked ? 'true' : 'false';

    // Monta endereço em observação
    let enderecoStr = '';
    if (tipoEntrega === 'delivery') {
        const rua         = document.getElementById('endereco_rua').value.trim();
        const num         = document.getElementById('endereco_numero').value.trim();
        const compl       = document.getElementById('endereco_complemento').value.trim();
        const bairro      = document.getElementById('endereco_bairro').value.trim();
        const cidade      = document.getElementById('endereco_cidade').value.trim();
        const cep         = document.getElementById('endereco_cep').value.trim();
        const referencia  = document.getElementById('endereco_referencia').value.trim();

        if (!rua) {
            Swal.fire({ icon: 'warning', title: 'Informe o endereço de entrega', timer: 2000, timerProgressBar: true });
            return;
        }

        enderecoStr = `Entrega: ${rua}, ${num}${compl ? ' ' + compl : ''} — ${bairro} — ${cidade}${cep ? ' — CEP ' + cep : ''}${referencia ? ' | Ref: ' + referencia : ''}`;
    } else {
        enderecoStr = 'Retirada no balcão';
    }

    const obsBase  = document.getElementById('observacao').value.trim();
    const obsTotal = [enderecoStr, obsBase].filter(Boolean).join(' | ');

    const subtotal = carrinho.reduce((s, i) => s + i.subtotal, 0);
    const total    = subtotal + taxa;

    const body = {
        id_cliente:    idCliente,
        itens:         JSON.stringify(carrinho),
        pagamento:     pagamento,
        observacao:    obsTotal,
        tipo_entrega:  tipoEntrega,
        taxa_entrega:  taxa,
        total_geral:   total,
        parcelas:      parcelas,
        intervalo:     pagamento === 'credito' && parcelas > 1 ? 30 : 0,
        id_payment_terms: selectedPaymentTermId || '',
        id_endereco:   idEndereco,
        salvar_endereco: salvarEndereco,
        endereco_rua: document.getElementById('endereco_rua').value.trim(),
        endereco_numero: document.getElementById('endereco_numero').value.trim(),
        endereco_complemento: document.getElementById('endereco_complemento').value.trim(),
        endereco_bairro: document.getElementById('endereco_bairro').value.trim(),
        endereco_cidade: document.getElementById('endereco_cidade').value.trim(),
        endereco_cep: document.getElementById('endereco_cep').value.trim(),
        endereco_referencia: document.getElementById('endereco_referencia').value.trim(),
    };

    document.getElementById('btn-finalizar').disabled = true;

    try {
        const requests = new Requests();
        const fd       = new FormData();
        Object.keys(body).forEach(k => fd.append(k, body[k]));

        const response = await requests.setBody(fd).post('/pedido/virtual/insert');

        if (!response.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: response.msg || 'Não foi possível enviar o pedido.', timer: 3500, timerProgressBar: true });
            return;
        }

        Swal.fire({
            icon: 'success',
            title: 'Pedido enviado!',
            html: `Pedido <strong>#${response.id}</strong> enviado para a cozinha com sucesso.<br>
                   <span class="badge bg-warning text-dark mt-2">Aguardando preparo...</span>`,
            timer: 3000,
            timerProgressBar: true,
        }).then(() => {
            window.location.href = '/pedido/lista';
        });

    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: `${error.message}`, timer: 3500, timerProgressBar: true });
    } finally {
        document.getElementById('btn-finalizar').disabled = false;
    }
}


async function salvarNovoCliente() {
    const cpf  = document.getElementById('modal_cpf').value.trim();
    const nome = document.getElementById('modal_nome').value.trim();
    if (!cpf || !nome) {
        Swal.fire({ icon: 'warning', title: 'Preencha CPF e nome', timer: 1800 });
        return;
    }
    const fd = new FormData();
    fd.append('numeroDocumento', cpf);
    fd.append('nomeExibicao',    nome);
    fd.append('nomeLegal',       document.getElementById('modal_sobrenome').value);
    fd.append('ativo',           'true');
    const requests = new Requests();
    const res = await requests.setBody(fd).post('/cliente/insert');
    if (!res.status) {
        Swal.fire({ icon: 'error', title: res.msg || 'Erro ao salvar', timer: 2500 });
        return;
    }
    // Injeta no Select2 e seleciona automaticamente
    const option = new Option(`#${res.id} — ${nome}`, res.id, true, true);
    $('#id_cliente').append(option).trigger('change');
    bootstrap.Modal.getInstance(document.getElementById('modalNovoCliente')).hide();
    Swal.fire({ icon: 'success', title: 'Cliente salvo!', timer: 1500 });
}
document.getElementById('btn-salvar-cliente').addEventListener('click', salvarNovoCliente);
// ─── Expõe globais ────────────────────────────────────────────────────────────

window.removerItemCarrinho = removerItem;

// ─── Init ─────────────────────────────────────────────────────────────────────

initClienteSelect();
initProdutoSelect();
initQuantidade();
loadPaymentTerms();
initPagamento();
initTipoEntrega();

maskDecimal(document.getElementById('taxa_entrega'));
document.getElementById('taxa_entrega').addEventListener('input', atualizarResumo);
document.getElementById('troco_para').addEventListener('input', calcularTroco);
document.getElementById('insert-item').addEventListener('click', adicionarItem);
document.getElementById('btn-finalizar').addEventListener('click', finalizarPedido);
