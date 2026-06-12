import Requests from '../components/requests.js';
import Validate from '../components/validate.js';

const Action = document.getElementById('action');
const Id     = document.getElementById('id');
const Insert = document.getElementById('insert');

const inputPrecoCompra      = document.getElementById('precoCompra');
const inputTotalImposto     = document.getElementById('totalImposto');
const inputCustoOperacional = document.getElementById('custoOperacional');
const inputMargemLucro      = document.getElementById('margemLucro');
const inputPrecoVenda       = document.getElementById('precoVenda');

// ─── Máscaras ────────────────────────────────────────────────────────────────
if (window.Inputmask) {
    Inputmask('currency', {
        radixPoint: ',',
        prefix: 'R$ ',
        autoGroup: true,
        groupSeparator: '.',
        rightAlign: false,
        onBeforeMask: v => String(v).replace('.', ','),
    }).mask('#precoCompra, #precoVenda');

    Inputmask('currency', {
        radixPoint: ',',
        prefix: '% ',
        autoGroup: true,
        groupSeparator: '.',
        rightAlign: false,
        onBeforeMask: v => String(v).replace('.', ','),
    }).mask('#totalImposto, #margemLucro, #custoOperacional');
}

// ─── Helper: limpa máscara e converte para float ─────────────────────────────
function parseMoney(val) {
    return parseFloat(
        String(val).replace('R$', '').replace('%', '').replace(/\./g, '').replace(',', '.').trim()
    ) || 0;
}

// ─── Calculadora de preço de venda ────────────────────────────────────────────
function calcularPrecoVenda() {
    const precoCompra      = parseMoney(inputPrecoCompra.value);
    const totalImposto     = parseMoney(inputTotalImposto.value);
    const custoOperacional = parseMoney(inputCustoOperacional.value);
    const margemLucro      = parseMoney(inputMargemLucro.value);
    const resultadoRow     = document.getElementById('resultado-row');

    if (precoCompra <= 0 && margemLucro <= 0) {
        resultadoRow.classList.add('d-none');
        return;
    }

    try {
        const taxRate       = totalImposto     / 100;
        const marginRate    = margemLucro      / 100;
        const operatingRate = custoOperacional / 100;
        const totalRate     = taxRate + marginRate + operatingRate;
        const divisor       = 1 - totalRate;

        if (divisor <= 0.01) { resultadoRow.classList.add('d-none'); return; }

        const round2 = v => Math.round((v + Number.EPSILON) * 100) / 100;
        const precoSugerido = round2(precoCompra / divisor);
        const valorImposto  = round2(precoSugerido * taxRate);
        const valorCusto    = round2(precoSugerido * operatingRate);
        const valorMargem   = round2(precoSugerido * marginRate);

        const fmt = v => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        document.getElementById('val-imposto').textContent = fmt(valorImposto);
        document.getElementById('val-custo').textContent   = fmt(valorCusto);
        document.getElementById('val-margem').textContent  = fmt(valorMargem);
        document.getElementById('val-venda').textContent   = fmt(precoSugerido);

        resultadoRow.classList.remove('d-none');
    } catch {
        resultadoRow.classList.add('d-none');
    }
}

[inputPrecoCompra, inputTotalImposto, inputCustoOperacional, inputMargemLucro]
    .forEach(el => el?.addEventListener('input', calcularPrecoVenda));

// ─── Salvar ───────────────────────────────────────────────────────────────────
async function applyChanges() {
    $('button').prop('disabled', true);

    const IsValid = Validate.SetForm('form').Validate();
    if (!IsValid) {
        Swal.fire({ icon: 'error', title: 'Erro', text: 'Corrija os erros antes de salvar.', timer: 3000, timerProgressBar: true });
        $('button').prop('disabled', false);
        return;
    }

    // Monta FormData manualmente para controlar cada campo
    const form = document.getElementById('form');
    const formData = new FormData();

    // Campos texto — envia como estão (sem máscara nos campos de valor — limpamos abaixo)
    formData.append('action',         Action.value);
    formData.append('id',             Id.value);
    formData.append('nome',           document.getElementById('nome').value);
    formData.append('codigoBarra',    document.getElementById('codigoBarra').value);
    formData.append('grupo',          document.getElementById('grupo').value);
    formData.append('unidade',        document.getElementById('unidade').value);
    formData.append('tempoPreparo',   document.getElementById('tempoPreparo').value);
    formData.append('descricao',      document.getElementById('descricao').value);

    // Checkbox: envia 'true' se marcado, 'false' se não (evita campo vazio)
    formData.append('ativo', document.getElementById('ativo').checked ? 'true' : 'false');

    // Valores numéricos — remove máscara antes de enviar
    formData.append('precoCompra',        parseMoney(inputPrecoCompra.value));
    formData.append('totalImposto',       parseMoney(inputTotalImposto.value));
    formData.append('custoOperacional',   parseMoney(inputCustoOperacional.value));
    formData.append('margemLucro',        parseMoney(inputMargemLucro.value));
    formData.append('precoVenda',         parseMoney(inputPrecoVenda.value));

    // Valor sugerido calculado
    const valVendaText = document.getElementById('val-venda')?.textContent ?? '0';
    formData.append('valorVendaSugerido', parseMoney(valVendaText.replace('R$', '')));

    const requests = new Requests();
    try {
        const url = (Action.value !== 'e') ? '/product/insert' : '/product/update';
        const response = await requests.setBody(formData).post(url);

        if (!response?.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: response?.msg || 'Erro ao salvar.', timer: 3000, timerProgressBar: true });
            return;
        }

        if (Action.value === 'e') {
            Swal.fire({ icon: 'success', title: 'Sucesso', text: response.msg, timer: 2000, timerProgressBar: true })
                .then(() => window.location.href = '/product/lista');
            return;
        }

        Action.value = 'e';
        Id.value = response.id;
        window.history.pushState({}, '', `/product/detalhes/${response.id}`);
        Swal.fire({ icon: 'success', title: 'Sucesso', text: response.msg || 'Produto salvo!', timer: 2000, timerProgressBar: true });
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: `Erro: ${error.message}`, timer: 3000, timerProgressBar: true });
    } finally {
        $('button, input').prop('disabled', false);
    }
}

Insert.addEventListener('click', applyChanges);