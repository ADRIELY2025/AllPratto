import DataTables from '../components/data-tables.js';
import Requests from '../components/requests.js';

const Id = document.getElementById('id');
const table = DataTables.SetId('table-pedido').setRequestVariables([]).post('/pedido/listingdata');

async function cancelarPedido() {
    const requests = new Requests();
    try {
        const response = await requests.setForm('form').post('/pedido/delete');
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
        text: 'Deseja realmente cancelar este pedido? A mesa voltará para livre.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Cancelar pedido',
        cancelButtonText: 'Voltar'
    }).then(async (result) => {
        if (result.isConfirmed) {
            const response = await cancelarPedido();
            if (!response.status) {
                Swal.fire({
                    title: 'Erro!',
                    text: response.msg,
                    icon: 'error',
                    timer: 3000,
                    timerProgressBar: true
                });
                return;
            }
            Swal.fire({
                title: 'Cancelado!',
                text: 'Pedido cancelado com sucesso.',
                icon: 'success',
                timer: 2000,
                timerProgressBar: true
            }).then(() => {
                table.ajax.reload();
            });
        }
    });
}

window.ShowModal = ShowModal;