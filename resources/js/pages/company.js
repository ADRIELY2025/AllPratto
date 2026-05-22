import Requests from '../components/requests.js';
import Validate from '../components/validate.js';

const Action = document.getElementById('action');
const Id = document.getElementById('id');
const Insert = document.getElementById('insert');

Inputmask({ mask: ['99.999.999/9999-99'] }).mask('#cnpj');
Inputmask({ mask: ['99999-999'] }).mask('#cep');

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
            ? await requests.setForm('form').post('/company/insert')
            : await requests.setForm('form').post('/company/update');

        if (!response.status) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: response.msg || 'Ocorreu um erro ao salvar a empresa.',
                timer: 3000,
                timerProgressBar: true,
            });
            return;
        }

        const redirectUrl = `/company/detalhes/${response.id}`;
        if (Action.value === 'e') {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso',
                text: response.msg || 'Empresa alterada com sucesso.',
                timer: 3000,
                timerProgressBar: true,
            }).then(() => {
                window.location.href = '/company/lista';
            });
            return;
        }

        Action.value = 'e';
        Id.value = response.id;
        window.history.pushState({}, '', redirectUrl);
        Swal.fire({
            icon: 'success',
            title: 'Sucesso',
            text: response.msg || 'Empresa salva com sucesso!',
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
