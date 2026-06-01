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

window.ShowModal = ShowModal;
