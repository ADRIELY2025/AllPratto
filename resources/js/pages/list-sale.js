import DataTables from '../components/data-tables.js';
import Requests from '../components/requests.js';

const Id = document.getElementById('id');

const table = DataTables.SetId('table-sale').setRequestVariables([]).post('/sale/listingdata');

// ─── Excluir venda ────────────────────────────────────────────────────────────

async function deleteSale() {
    const requests = new Requests();
    try {
        const response = await requests.setForm('form').post('/sale/delete');
        return response;
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: `Restrição: ${error.message}`,
            timer: 3000,
            timerProgressBar: true,
        });
    }
}

async function ShowModal(id) {
    Id.value = id;
    Swal.fire({
        title: 'Atenção!',
        text: 'Deseja realmente excluir esta venda? Esta ação não pode ser desfeita e também removerá todos os itens associados.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Excluir',
        cancelButtonText: 'Cancelar',
    }).then(async (result) => {
        if (result.isConfirmed) {
            const response = await deleteSale();
            if (!response?.status) {
                Swal.fire({
                    title: 'Erro!',
                    text: response?.msg || 'Não foi possível excluir a venda.',
                    icon: 'error',
                    timer: 3000,
                    timerProgressBar: true,
                });
                return;
            }
            Swal.fire({
                title: 'Removido!',
                text: 'Venda excluída com sucesso.',
                icon: 'success',
                timer: 2000,
                timerProgressBar: true,
            }).then(() => {
                table.ajax.reload();
            });
        }
    });
}

// ─── Exportar para window (necessário nos botões inline do DataTable) ─────────

window.ShowModal = ShowModal;
