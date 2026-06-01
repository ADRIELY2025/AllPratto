import FindCompany from "../components/find-company.js";
import Requests from "../components/requests.js";
import Validate from "../components/validate.js";

const Action = document.getElementById('action');
const Id = document.getElementById('id');
const Cnpj = document.getElementById('numeroDocumento');
const Insert = document.getElementById('insert');
Inputmask({ mask: ['999.999.999-99', '99.999.999/9999-99'], keepStatic: true }).mask("#numeroDocumento");
Inputmask({ mask: ['99/99/9999'] }).mask("#dataRegistro");
$('#dataRegistro').flatpickr({
    enableTime: false,
    dateFormat: "d/m/Y",
    locale: "pt"
});

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
            ? await requests.setForm('form').post('/cliente/insert') :
            await requests.setForm('form').post('/cliente/update');
        if (!response.status) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: response.msg || 'Ocorreu um erro ao salvar os dados do cliente.',
                timer: 3000,
                timerProgressBar: true,
            });
            return;
        }
        const baseUrl = window.location.origin;
        const redirectUrl = `${baseUrl}/cliente/detalhes/${response.id}`;
        if (Action.value === 'e') {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso',
                text: response.msg || 'Dados do cliente alterados com sucesso.',
                timer: 3000,
                timerProgressBar: true,
            }).then(() => {
                window.location.href = '/cliente/lista';
            });
            return;
        }
        Action.value = 'e';
        Id.value = response.id;
        window.history.pushState({}, '', redirectUrl);
        Swal.fire({
            icon: 'success',
            title: 'Sucesso',
            text: response.msg || 'Cliente salvo com sucesso!',
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
        $('button, input, checkbox').prop('disabled', false);
    } finally {
        $('button, input, checkbox').prop('disabled', false);
    }
}

Cnpj.addEventListener('blur', async () => {
    if (Cnpj.value.trim() === '' || Cnpj.value.replace(/\D/g, '').length < 14) {
        return;
    }
    const findCompany = new FindCompany({ cnpjField: 'numeroDocumento', cnaeValue: 'cnae', cnaeSearch: 'codigoAtividadeEconomica' })
    await findCompany.FindCompanyData();
});

Insert.addEventListener('click', async () => {
    await applyChanges();
});