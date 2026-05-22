import DataTables from '../components/data-tables.js';
import Requests from '../components/requests.js';

const Id = document.getElementById('id');
const table = DataTables.SetId('table-product').setRequestVariables([]).post('/product/listingdata');

async function deleteProduct() {
    const requests = new Requests();
    try {
        const response = await requests.setForm('form').post('/product/delete');
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
            const response = await deleteProduct();
            if (!response || !response.status) {
                Swal.fire({
                    title: 'Erro!',
                    text: response?.msg || 'Falha ao excluir o produto.',
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
