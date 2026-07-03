import Requests from '../components/requests.js';
import Validate from '../components/validate.js';

import { create, registerPlugin } from 'filepond';
import 'filepond/dist/filepond.css';

// Import the Image Preview plugin
import FilePondPluginImagePreview from 'filepond-plugin-image-preview';

// Register the plugin with FilePond
registerPlugin(FilePondPluginImagePreview);

const Action = document.getElementById('action');
const Id = document.getElementById('id');
const Insert = document.getElementById('insert');

const inputPrecoCompra = document.getElementById('preco_compra');
const inputTotalImposto = document.getElementById('total_imposto');
const inputCustoOperacional = document.getElementById('custo_operacional');
const inputMargemLucro = document.getElementById('margem_lucro');
const inputPrecoVenda = document.getElementById('preco_venda');

const Imagem = document.querySelector('#imagem_url');


create(Imagem, {

    storeAsFile: true,
});

// ─── Máscaras ────────────────────────────────────────────────────────────────
if (window.Inputmask) {
    Inputmask('currency', {
        radixPoint: ',',
        prefix: 'R$ ',
        autoGroup: true,
        groupSeparator: '.',
        rightAlign: false,
        onBeforeMask: v => String(v).replace('.', ','),
    }).mask('#preco_compra, #preco_venda');

    Inputmask('currency', {
        radixPoint: ',',
        prefix: '% ',
        autoGroup: true,
        groupSeparator: '.',
        rightAlign: false,
        onBeforeMask: v => String(v).replace('.', ','),
    }).mask('#total_imposto, #margem_lucro, #custo_operacional');
}

// ─── Helper: limpa máscara e converte para float ─────────────────────────────
function parseMoney(val) {
    return parseFloat(
        String(val).replace('R$', '').replace('%', '').replace(/\./g, '').replace(',', '.').trim()
    ) || 0;
}

// ─── Calculadora de preço de venda ────────────────────────────────────────────
function calcularPrecoVenda() {
    const precoCompra = parseMoney(inputPrecoCompra.value);
    const totalImposto = parseMoney(inputTotalImposto.value);
    const custoOperacional = parseMoney(inputCustoOperacional.value);
    const margemLucro = parseMoney(inputMargemLucro.value);
    const resultadoRow = document.getElementById('resultado-row');

    if (precoCompra <= 0 && margemLucro <= 0) {
        resultadoRow.classList.add('d-none');
        return;
    }

    try {
        const taxRate = totalImposto / 100;
        const marginRate = margemLucro / 100;
        const operatingRate = custoOperacional / 100;
        const totalRate = taxRate + marginRate + operatingRate;
        const divisor = 1 - totalRate;

        if (divisor <= 0.01) { resultadoRow.classList.add('d-none'); return; }

        const round2 = v => Math.round((v + Number.EPSILON) * 100) / 100;
        const precoSugerido = round2(precoCompra / divisor);
        const valorImposto = round2(precoSugerido * taxRate);
        const valorCusto = round2(precoSugerido * operatingRate);
        const valorMargem = round2(precoSugerido * marginRate);

        const fmt = v => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        document.getElementById('val-imposto').textContent = fmt(valorImposto);
        document.getElementById('val-custo').textContent = fmt(valorCusto);
        document.getElementById('val-margem').textContent = fmt(valorMargem);
        document.getElementById('val-venda').textContent = fmt(precoSugerido);

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

    // Monta FormData manualmente para enviar valores sem máscara
    const form = document.getElementById('form');
    const formData = new FormData(form);

    // Sobrescreve os campos monetários sem a máscara (R$ e %)
    formData.set('preco_compra',      parseMoney(inputPrecoCompra.value));
    formData.set('total_imposto',     parseMoney(inputTotalImposto.value));
    formData.set('custo_operacional', parseMoney(inputCustoOperacional.value));
    formData.set('margem_lucro',      parseMoney(inputMargemLucro.value));
    formData.set('preco_venda',       parseMoney(inputPrecoVenda.value));

    // Valor sugerido calculado (exibido na tela)
    const valVendaText = document.getElementById('val-venda')?.textContent ?? '0';
    formData.set('valor_venda_sugerido', parseMoney(valVendaText));

    const requests = new Requests();
    try {
        const url = (Action.value !== 'e') ? '/product/insert' : '/product/update';
        const response = await requests.setBody(formData).post(url);

        if (!response?.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: response?.msg || 'Erro ao salvar.', timer: 3000, timerProgressBar: true });
            return;
        }

        // Tanto insert quanto update: mostra sucesso e vai para a lista
        await Swal.fire({ icon: 'success', title: 'Sucesso', text: response.msg || 'Produto salvo!', timer: 2000, timerProgressBar: true });
        window.location.href = '/product/lista';

    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: `Erro: ${error.message}`, timer: 3000, timerProgressBar: true });
    } finally {
        $('button, input').prop('disabled', false);
    }
}




Insert.addEventListener('click', applyChanges);