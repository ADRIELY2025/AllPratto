import DataTables from '../components/data-tables.js';
import Requests from '../components/requests.js';

const Id = document.getElementById('id');
const table = DataTables.SetId('table-users').setRequestVariables([]).post('/users/listingdata');

async function deleteUser() {
    const requests = new Requests();
    try {
        const response = await requests.setForm('form').post('/users/delete');
        return response;
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: `Restrição: ${error}`,
            timer: 3000,
            timerProgressBar: true,
        });
    }
}

async function ShowModal(id) {
    Id.value = id;
    Swal.fire({
        title: 'Atenção!',
        text: 'Deseja realmente excluir este registro?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Excluir'
    }).then(async (result) => {
        if (result.isConfirmed) {
            const response = await deleteUser();
            if (!response || !response.status) {
                Swal.fire({
                    title: 'Erro!',
                    text: response?.msg || 'Falha ao excluir o usuário.',
                    icon: 'error',
                    timer: 3000,
                    timerProgressBar: true
                });
                return;
            }
            Swal.fire({
                title: 'Removido!',
                text: 'Registro excluído com sucesso.',
                icon: 'success',
                timer: 2000,
                timerProgressBar: true
            }).then(async () => {
                table.ajax.reload();
            });
        }
    });
}

async function resetPassword(id) {
    const requests = new Requests();
    const formData = new FormData();
    formData.append('id', id);

    try {
        const response = await requests.setBody(formData).post('/users/reset-password');
        return response;
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: `Restrição: ${error.message || error}`,
            timer: 3000,
            timerProgressBar: true,
        });
        return null;
    }
}

function showGeneratedPassword(senha) {
    Swal.fire({
        title: 'Nova senha gerada',
        icon: 'success',
        html: `
            <p style="font-size:0.85rem; color:#666; margin-bottom:10px;">
                Copie a senha abaixo e repasse ao usuário. Ela não será exibida novamente.
            </p>
            <div style="display:flex; gap:8px;">
                <input id="swal-senha-gerada" type="text" readonly value="${senha}"
                    style="flex:1; font-family:'Courier New', monospace; font-weight:600;
                           font-size:1.05rem; padding:8px; text-align:center;
                           border:1px solid #ccc; border-radius:6px;">
                <button id="swal-copiar-senha" class="btn btn-primary" type="button">
                    <i class="fa-solid fa-copy"></i>
                </button>
            </div>
            <p id="swal-copia-feedback" style="color:#1e6b45; font-size:0.78rem; min-height:16px; margin-top:6px;"></p>
        `,
        showConfirmButton: true,
        confirmButtonText: 'Fechar',
        didOpen: () => {
            const btnCopiar = document.getElementById('swal-copiar-senha');
            const input = document.getElementById('swal-senha-gerada');
            const feedback = document.getElementById('swal-copia-feedback');

            btnCopiar.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(input.value);
                } catch (error) {
                    input.select();
                    input.setSelectionRange(0, input.value.length);
                    document.execCommand('copy');
                }
                feedback.textContent = 'Senha copiada!';
                setTimeout(() => { feedback.textContent = ''; }, 2500);
            });
        },
    });
}

async function ShowResetPasswordModal(id) {
    Swal.fire({
        title: 'Resetar senha',
        text: 'Uma nova senha será gerada automaticamente para este usuário. Deseja continuar?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Resetar senha',
        cancelButtonText: 'Cancelar',
    }).then(async (result) => {
        if (!result.isConfirmed) return;

        const response = await resetPassword(id);
        if (!response || !response.status) {
            Swal.fire({
                title: 'Erro!',
                text: response?.msg || 'Falha ao resetar a senha do usuário.',
                icon: 'error',
                timer: 3000,
                timerProgressBar: true,
            });
            return;
        }

        showGeneratedPassword(response.senha);
    });
}

window.ShowModal = ShowModal;
window.ShowResetPasswordModal = ShowResetPasswordModal;
