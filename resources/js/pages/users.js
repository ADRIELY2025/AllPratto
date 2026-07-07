import Requests from '../components/requests.js';
import Validate from '../components/validate.js';

const Action = document.getElementById('action');
const Id = document.getElementById('id');
const Insert = document.getElementById('insert');

Inputmask({ mask: ['999.999.999-99'] }).mask('#cpf');

async function applyChanges() {
    $('button').prop('disabled', true);
    const IsValid = Validate.SetForm('form').Validate();
    if (!IsValid) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: `Por favor, corrija os erros no formulário antes de salvar.`,
            timer: 3000,
            timerProgressBar: true,
        });
        return;
    }

    const requests = new Requests();
    try {
        const response = (Action.value !== 'e')
            ? await requests.setForm('form').post('/users/insert')
            : await requests.setForm('form').post('/users/update');

        if (!response.status) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: response.msg || 'Ocorreu um erro ao salvar o usuário.',
                timer: 3000,
                timerProgressBar: true,
            });
            return;
        }

        const redirectUrl = `/users/detalhes/${response.id}`;
        if (Action.value === 'e') {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso',
                text: response.msg || 'Usuário alterado com sucesso.',
                timer: 3000,
                timerProgressBar: true,
            }).then(() => {
                window.location.href = '/users/lista';
            });
            return;
        }

        Action.value = 'e';
        Id.value = response.id;
        window.history.pushState({}, '', redirectUrl);
        Swal.fire({
            icon: 'success',
            title: 'Sucesso',
            text: response.msg || 'Usuário salvo com sucesso!',
            timer: 3000,
            timerProgressBar: true,
        });
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: `Restrição: ${error.message}`,
            timer: 3000,
            timerProgressBar: true,
        });
    } finally {
        $('button, input, checkbox').prop('disabled', false);
    }
}

Insert.addEventListener('click', async () => {
    await applyChanges();
});

// ============================================================================
// RESETAR SENHA (ação do administrador — só existe na edição de usuário já
// cadastrado; a nova senha é gerada no servidor e exibida uma única vez)
// ============================================================================
const ResetPassword = document.getElementById('resetPassword');

if (ResetPassword) {
    ResetPassword.addEventListener('click', async () => {
        const result = await Swal.fire({
            title: 'Resetar senha',
            text: 'Uma nova senha será gerada automaticamente para este usuário. Deseja continuar?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Resetar senha',
            cancelButtonText: 'Cancelar',
        });

        if (!result.isConfirmed) return;

        ResetPassword.disabled = true;

        try {
            const formData = new FormData();
            formData.append('id', Id.value);

            const response = await new Requests().setBody(formData).post('/users/reset-password');

            if (!response?.status) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: response?.msg || 'Falha ao resetar a senha do usuário.',
                    timer: 3000,
                    timerProgressBar: true,
                });
                return;
            }

            Swal.fire({
                title: 'Nova senha gerada',
                icon: 'success',
                html: `
                    <p style="font-size:0.85rem; color:#666; margin-bottom:10px;">
                        Copie a senha abaixo e repasse ao usuário. Ela não será exibida novamente.
                    </p>
                    <div style="display:flex; gap:8px;">
                        <input id="swal-senha-gerada" type="text" readonly value="${response.senha}"
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
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: error.message || 'Erro ao conectar ao servidor',
                timer: 3000,
                timerProgressBar: true,
            });
        } finally {
            ResetPassword.disabled = false;
        }
    });
}