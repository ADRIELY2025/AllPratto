import Requests from '../components/requests.js';
import Validate from '../components/validate.js';

const Action = document.getElementById('action');
const Id     = document.getElementById('id');
const Insert = document.getElementById('insert');

const inputPrecoCompra      = document.getElementById('preco_compra');
const inputTotalImposto     = document.getElementById('total_imposto');
const inputCustoOperacional = document.getElementById('custo_operacional');
const inputMargemLucro      = document.getElementById('margem_lucro');
const inputPrecoVenda       = document.getElementById('preco_venda');


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
    formData.append('codigo_barra',    document.getElementById('codigo_barra').value);
    formData.append('grupo',          document.getElementById('grupo').value);
    formData.append('unidade',        document.getElementById('unidade').value);
    formData.append('tempo_preparo',   document.getElementById('tempo_preparo').value);
    formData.append('descricao',      document.getElementById('descricao').value);

    // Checkbox: envia 'true' se marcado, 'false' se não (evita campo vazio)
    formData.append('ativo', document.getElementById('ativo').checked ? 'true' : 'false');

    // Valores numéricos — remove máscara antes de enviar
    formData.append('preco_compra',        parseMoney(inputPrecoCompra.value));
    formData.append('total_imposto',       parseMoney(inputTotalImposto.value));
    formData.append('custo_operacional',   parseMoney(inputCustoOperacional.value));
    formData.append('margem_lucro',        parseMoney(inputMargemLucro.value));
    formData.append('preco_venda',         parseMoney(inputPrecoVenda.value));

    // Valor sugerido calculado
    const valVendaText = document.getElementById('val-venda')?.textContent ?? '0';
    formData.append('valor_venda_sugerido', parseMoney(valVendaText.replace('R$', '')));

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

// ─── Upload de imagem ─────────────────────────────────────────────
const btnUpload    = document.getElementById('btn-upload-imagem');
const inputImagem  = document.getElementById('inputImagem');
const previewImg   = document.getElementById('preview-imagem');

// Pré-visualização local antes de enviar
if (inputImagem) {
    inputImagem.addEventListener('change', () => {
        const file = inputImagem.files[0];
        if (!file) return;
        previewImg.src = URL.createObjectURL(file);
    });
}

// Envio da imagem separado do formulário principal
if (btnUpload) {
    btnUpload.addEventListener('click', async () => {
        const file = inputImagem?.files[0];
        if (!file) {
            Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Selecione uma imagem antes de enviar.', timer: 2500, timerProgressBar: true });
            return;
        }

        const formData = new FormData();
        formData.append('id',     Id.value);
        formData.append('imagem', file);

        btnUpload.disabled = true;

        try {
            const requests = new Requests();
            const response = await requests.setBody(formData).post('/product/upload-imagem');

            if (!response?.status) {
                Swal.fire({ icon: 'error', title: 'Erro', text: response?.msg || 'Erro ao enviar imagem.', timer: 3000, timerProgressBar: true });
                return;
            }

            // Atualiza o preview com a imagem do servidor (evita cache)
            previewImg.src = response.imagem_url + '?v=' + Date.now();
            Swal.fire({ icon: 'success', title: 'Sucesso', text: 'Imagem enviada!', timer: 2000, timerProgressBar: true });
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Erro', text: error.message, timer: 3000, timerProgressBar: true });
        } finally {
            btnUpload.disabled = false;
        }
    });
}
Insert.addEventListener('click', applyChanges);