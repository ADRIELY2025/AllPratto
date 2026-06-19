import Requests from '../components/requests.js';
import Validate from '../components/validate.js';

const Action  = document.getElementById('action');
const Id      = document.getElementById('id');
const Codigo  = document.getElementById('codigo');
const Titulo  = document.getElementById('titulo');
const BtnAdd  = document.getElementById('btnAddParcela');
const TbBody  = document.getElementById('tbInstallments');

let installments = [];

// ─── Render da tabela
function renderInstallments() {
    TbBody.innerHTML = '';

    if (installments.length === 0) {
        TbBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Nenhuma parcela adicionada.</td></tr>';
        return;
    }

    installments.forEach((item, index) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${item.parcela}X</td>
            <td>${item.parcela}</td>
            <td>${item.intervalo} dias</td>
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
            installments = res.data.map(r => ({ id: r.id, parcela: r.parcela, intervalo: r.intervalo }));
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
});

// ─── Adicionar parcela
BtnAdd.addEventListener('click', async () => {
    const parcela   = parseInt(document.getElementById('parcela').value)   || 0;
    const intervalo = parseInt(document.getElementById('intervalo').value) || 0;

    if (parcela <= 0 || intervalo < 0) {
        Swal.fire({ icon: 'error', title: 'Erro', text: 'Preencha a quantidade de parcelas e o intervalo.', timer: 3000, timerProgressBar: true });
        return;
    }

    // Salva o payment_terms primeiro se ainda não foi salvo
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
        });

        if (!res.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: res.msg, timer: 3000, timerProgressBar: true });
            return;
        }

        installments.push({ id: res.id, parcela, intervalo });
        renderInstallments();

        document.getElementById('parcela').value   = '';
        document.getElementById('intervalo').value = '';
        document.getElementById('parcela').focus();

        Swal.fire({ icon: 'success', title: 'Adicionado!', text: 'Parcela salva com sucesso.', timer: 2000, timerProgressBar: true });

    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Erro', text: e.message, timer: 3000, timerProgressBar: true });
    }
});

// ─── Botão Salvar
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

// ─── Ao abrir a página em modo edição, carrega parcelas
loadInstallments();