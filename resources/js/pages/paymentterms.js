import Requests from '../components/requests.js';
import Validate from '../components/validate.js';

// ─── Elementos
const Action = document.getElementById('action');
const Id = document.getElementById('id');
const Codigo = document.getElementById('codigo');
const Titulo = document.getElementById('titulo');
const BtnAdd = document.getElementById('btnAddParcela');
const TbBody = document.getElementById('tbInstallments');
const AvisoEl = document.getElementById('avisoSemParcela');
const NomeFormaEl = document.getElementById('nomeFormaPagamento');
const WrapParcela = document.getElementById('wrapParcela');
const WrapIntervalo = document.getElementById('wrapIntervalo');
const InputParcela = document.getElementById('parcela');
const InputInterv = document.getElementById('intervalo');
const InputValor = document.getElementById('valor_total');   // readonly — valor por parcela calculado
const TotalVendaRaw = document.getElementById('total_venda_raw');

// Valor total da venda vindo do servidor (float)
const TOTAL_VENDA = parseFloat(TotalVendaRaw?.value || '0') || 0;

// Formas que NÃO permitem parcelamento
const SEM_PARCELAMENTO = ['01', '04', '17']; // Dinheiro, Cartão de Débito, PIX

const MAX_INTERVALO = 40;

let installments = [];

// ─── Formata número como moeda BR
function fmtBRL(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
}

// ─── Verifica se forma atual bloqueia parcelas
function isSemParcelamento() {
    return SEM_PARCELAMENTO.includes(Codigo.value);
}

// ─── Recalcula o campo "Valor por Parcela" com base na qtd digitada
function recalcularValorParcela() {
    if (!TOTAL_VENDA) return;

    if (isSemParcelamento()) {
        InputValor.value = fmtBRL(TOTAL_VENDA);
        return;
    }

    const qtd = parseInt(InputParcela.value) || 0;
    if (qtd > 0) {
        InputValor.value = fmtBRL(TOTAL_VENDA / qtd);
    } else {
        InputValor.value = '';
    }
}

// ─── Atualiza UI conforme forma de pagamento selecionada
function atualizarCamposForma() {
    const bloqueado = isSemParcelamento();
    const nomeForma = Codigo.options[Codigo.selectedIndex]?.text ?? '';

    AvisoEl.classList.toggle('d-none', !bloqueado);
    if (bloqueado) NomeFormaEl.textContent = nomeForma;

    InputParcela.disabled = bloqueado;
    InputInterv.disabled = bloqueado;

    if (bloqueado) {
        InputParcela.value = '';
        InputInterv.value = '';
        WrapParcela.style.opacity = '0.4';
        WrapIntervalo.style.opacity = '0.4';
    } else {
        WrapParcela.style.opacity = '1';
        WrapIntervalo.style.opacity = '1';
    }

    recalcularValorParcela();
}

// ─── Render da tabela
function renderInstallments() {
    TbBody.innerHTML = '';

    if (installments.length === 0) {
        TbBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Nenhuma parcela adicionada.</td></tr>';
        return;
    }

    installments.forEach((item, index) => {
        const valorParcela = item.parcela > 0 ? TOTAL_VENDA / item.parcela : TOTAL_VENDA;
        const valorFmt = fmtBRL(valorParcela);

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
window.removeParcela = async function (index) {
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
                id: r.id,
                parcela: r.parcela,
                intervalo: r.intervalo,
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
        Id.value = response.id;
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

// ─── Recalcula ao digitar quantidade de parcelas
InputParcela.addEventListener('input', recalcularValorParcela);

// ─── Limita intervalo a MAX_INTERVALO
InputInterv.addEventListener('input', () => {
    const v = parseInt(InputInterv.value) || 0;
    if (v > MAX_INTERVALO) {
        InputInterv.value = MAX_INTERVALO;
        Swal.fire({
            icon: 'warning', title: 'Atenção',
            text: `O intervalo máximo permitido é de ${MAX_INTERVALO} dias.`,
            timer: 2500, timerProgressBar: true,
        });
    }
});

// ─── Adicionar parcela
BtnAdd.addEventListener('click', async () => {
    const semParc = isSemParcelamento();
    const maxParcela = semParc ? 1 : (parseInt(InputParcela.value) || 0);
    const intervalo = semParc ? 0 : (parseInt(InputInterv.value) || 0);

    if (!semParc && maxParcela <= 0) {
        Swal.fire({ icon: 'error', title: 'Atenção', text: 'Informe a quantidade de parcelas.', timer: 3000, timerProgressBar: true });
        InputParcela.focus();
        return;
    }

    if (!semParc && (intervalo < 0 || intervalo > MAX_INTERVALO)) {
        Swal.fire({ icon: 'error', title: 'Atenção', text: `O intervalo deve ser entre 0 e ${MAX_INTERVALO} dias.`, timer: 3000, timerProgressBar: true });
        InputInterv.focus();
        return;
    }

    // Salva payment_terms primeiro se ainda não foi salvo
    if (!Id.value) {
        const ok = await savePaymentTerms();
        if (!ok) return;
    }

    // Descobre quais parcelas já existem para não duplicar
    const parcelasJaCadastradas = new Set(installments.map(i => i.parcela));
    let adicionados = 0;
    let erros = 0;

    // Cria registros de 1 até maxParcela (pula os já existentes)
    for (let n = 1; n <= maxParcela; n++) {
        if (parcelasJaCadastradas.has(n)) continue;

        try {
            const fd = new FormData();
            fd.append('id', Id.value);
            fd.append('parcela', n);
            fd.append('intervalo', intervalo);
            fd.append('valor_total', TOTAL_VENDA);

            const res = await new Requests().setBody(fd).post('/payment/installment/insert');

            if (res && res.status) {
                installments.push({ id: res.id, parcela: n, intervalo });
                adicionados++;
            } else {
                erros++;
            }
        } catch (e) {
            erros++;
        }
    }

    renderInstallments();

    InputParcela.value = '';
    InputInterv.value = '';
    InputValor.value = '';

    if (!semParc) InputParcela.focus();

    if (erros > 0) {
        Swal.fire({ icon: 'warning', title: 'Atenção', text: `${adicionados} parcela(s) salva(s), ${erros} erro(s).`, timer: 3000, timerProgressBar: true });
    } else if (adicionados === 0) {
        Swal.fire({ icon: 'info', title: 'Sem alterações', text: 'Todas as parcelas até esse número já estavam cadastradas.', timer: 2500, timerProgressBar: true });
    } else {
        Swal.fire({ icon: 'success', title: 'Adicionado!', text: `${adicionados} opção(ões) de parcelamento salva(s) (1x até ${maxParcela}x).`, timer: 2500, timerProgressBar: true });
    }
});

// ─── Botão Salvar principal
document.getElementById('insert').addEventListener('click', async () => {
    $('button').prop('disabled', true);

    const ok = await savePaymentTerms();

    if (ok) {
        Swal.fire({
            icon: 'success', title: 'Sucesso',
            text: 'Condição de pagamento salva com sucesso!',
            timer: 2500, timerProgressBar: true,
        }).then(() => { window.location.href = '/payment/lista'; });
    }

    $('button').prop('disabled', false);
});

// ─── Init
atualizarCamposForma();
loadInstallments();