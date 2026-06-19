import Requests from '../components/requests.js';
import Validate  from '../components/validate.js';

// ─── Elementos
const Action       = document.getElementById('action');
const Id           = document.getElementById('id');
const Codigo       = document.getElementById('codigo');
const Titulo       = document.getElementById('titulo');
const BtnAdd       = document.getElementById('btnAddParcela');
const TbBody       = document.getElementById('tbInstallments');
const AvisoEl      = document.getElementById('avisoSemParcela');
const NomeFormaEl  = document.getElementById('nomeFormaPagamento');
const WrapParcela  = document.getElementById('wrapParcela');
const WrapIntervalo= document.getElementById('wrapIntervalo');
const InputParcela = document.getElementById('parcela');
const InputInterv  = document.getElementById('intervalo');
const InputValor   = document.getElementById('valor_total');

// Formas que NÃO permitem parcelamento (somente à vista)
const SEM_PARCELAMENTO = ['01', '17']; // Dinheiro, PIX

let installments = [];

// ─── Verifica se a forma atual bloqueia parcela/intervalo
function isSemParcelamento() {
    return SEM_PARCELAMENTO.includes(Codigo.value);
}

// ─── Atualiza UI conforme forma de pagamento selecionada
function atualizarCamposForma() {
    const bloqueado = isSemParcelamento();
    const nomeForma = Codigo.options[Codigo.selectedIndex]?.text ?? '';

    // Aviso
    AvisoEl.classList.toggle('d-none', !bloqueado);
    if (bloqueado) NomeFormaEl.textContent = nomeForma;

    // Bloquear / desbloquear qtd. parcelas e intervalo
    InputParcela.disabled = bloqueado;
    InputInterv.disabled  = bloqueado;

    if (bloqueado) {
        InputParcela.value = '';
        InputInterv.value  = '';
        WrapParcela.style.opacity   = '0.4';
        WrapIntervalo.style.opacity = '0.4';
        InputValor.focus();
    } else {
        WrapParcela.style.opacity   = '1';
        WrapIntervalo.style.opacity = '1';
        InputParcela.focus();
    }
}

// ─── Render da tabela
function renderInstallments() {
    TbBody.innerHTML = '';

    if (installments.length === 0) {
        TbBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Nenhuma parcela adicionada.</td></tr>';
        return;
    }

    installments.forEach((item, index) => {
        const valorFmt = item.valor_total
            ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(item.valor_total / item.parcela)
            : '—';

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${item.parcela}X</td>
            <td>${item.parcela}</td>
            <td>${item.intervalo != null ? item.intervalo + ' dias' : '—'}</td>
            <td>${valorFmt}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-danger" onclick="removeParcela(${index})">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </td>`;
        TbBody.appendChild(tr);
    });
}

// ─── Remover parcela
window.removeParcela = async function(index) {
    const item = installments[index];

    if (item.id) {
        const requests = new Requests();
        try {
            const res = await requests.post('/payment/installment/delete', { id: item.id });
            if (!res.status) {
                Swal.fire({ icon: 'error', title: 'Erro', text: res.msg, timer: 3000, timerProgressBar: true });
                return;
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Erro', text: e.message, timer: 3000, timerProgressBar: true });
            return;
        }
    }

    installments.splice(index, 1);
    renderInstallments();
};

// ─── Carregar parcelas existentes (modo edição)
async function loadInstallments() {
    if (!Id.value) return;

    try {
        const requests = new Requests();
        const res = await requests.post('/payment/installment/list', { id_pagamento: Id.value });
        if (res.status && res.data) {
            installments = res.data.map(r => ({
                id:          r.id,
                parcela:     r.parcela,
                intervalo:   r.intervalo,
                valor_total: r.valor_total ?? null,
            }));
            renderInstallments();
        }
    } catch (e) { /* silencioso */ }
}

// ─── Salva o payment_terms e retorna true/false
async function savePaymentTerms() {
    const IsValid = Validate.SetForm('form').Validate();
    if (!IsValid) {
        Swal.fire({ icon: 'error', title: 'Erro', text: 'Corrija os erros antes de salvar.', timer: 3000, timerProgressBar: true });
        return false;
    }

    const requests = new Requests();
    try {
        const response = (Action.value !== 'e')
            ? await requests.setForm('form').post('/payment/insert')
            : await requests.setForm('form').post('/payment/update');

        if (!response.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: response.msg, timer: 3000, timerProgressBar: true });
            return false;
        }

        Action.value = 'e';
        Id.value     = response.id;
        window.history.replaceState({}, '', `/payment/detalhes/${response.id}`);
        return true;

    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Erro', text: e.message, timer: 3000, timerProgressBar: true });
        return false;
    }
}

// ─── Preenche título automaticamente ao selecionar forma
Codigo.addEventListener('change', () => {
    if (!Titulo.value) {
        Titulo.value = Codigo.options[Codigo.selectedIndex].text;
    }
    atualizarCamposForma();
});

// ─── Adicionar parcela
BtnAdd.addEventListener('click', async () => {
    const semParc   = isSemParcelamento();
    const parcela   = semParc ? 1 : (parseInt(InputParcela.value) || 0);
    const intervalo = semParc ? 0 : (parseInt(InputInterv.value)  || 0);
    const valorTotal = parseFloat(InputValor.value) || 0;

    // Validações
    if (!semParc && parcela <= 0) {
        Swal.fire({ icon: 'error', title: 'Atenção', text: 'Informe a quantidade de parcelas.', timer: 3000, timerProgressBar: true });
        InputParcela.focus();
        return;
    }

    if (!semParc && intervalo < 0) {
        Swal.fire({ icon: 'error', title: 'Atenção', text: 'Informe o intervalo em dias.', timer: 3000, timerProgressBar: true });
        InputInterv.focus();
        return;
    }

    if (valorTotal <= 0) {
        Swal.fire({ icon: 'error', title: 'Atenção', text: 'Informe o Valor Total (R$).', timer: 3000, timerProgressBar: true });
        InputValor.focus();
        return;
    }

    // Salva payment_terms primeiro se ainda não foi salvo
    if (!Id.value) {
        const ok = await savePaymentTerms();
        if (!ok) return;
    }

    const requests = new Requests();
    try {
        const res = await requests.post('/payment/installment/insert', {
            id_pagamento: Id.value,
            parcela,
            intervalo,
            valor_total: valorTotal,
        });

        if (!res.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: res.msg, timer: 3000, timerProgressBar: true });
            return;
        }

        installments.push({ id: res.id, parcela, intervalo, valor_total: valorTotal });
        renderInstallments();

        InputParcela.value = '';
        InputInterv.value  = '';
        InputValor.value   = '';

        if (!semParc) InputParcela.focus();
        else InputValor.focus();

        Swal.fire({ icon: 'success', title: 'Adicionado!', text: 'Parcela salva com sucesso.', timer: 2000, timerProgressBar: true });

    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Erro', text: e.message, timer: 3000, timerProgressBar: true });
    }
});

// ─── Botão Salvar principal
document.getElementById('insert').addEventListener('click', async () => {
    $('button').prop('disabled', true);

    const ok = await savePaymentTerms();

    if (ok) {
        Swal.fire({
            icon: 'success',
            title: 'Sucesso',
            text: 'Condição de pagamento salva com sucesso!',
            timer: 2500,
            timerProgressBar: true,
        }).then(() => { window.location.href = '/payment/lista'; });
    }

    $('button').prop('disabled', false);
});

// ─── Init
atualizarCamposForma(); // aplica estado inicial (caso haja valor pré-selecionado no edit)
loadInstallments();