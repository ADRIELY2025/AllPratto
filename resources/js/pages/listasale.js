const table = Datatables.SetTable('#table-sales', [
    { 
        data: 'id', 
        className: 'text-center',
        width: '60px'
    },
    { 
        data: 'nome_cliente', 
        // Se o nome_cliente não existir no objeto (sem join), ele tenta mostrar o id_cliente
        render: (data, type, row) => data ? data : `Cliente ID: ${row.id_cliente}`,
        defaultContent: '<span class="text-muted">Desconhecido</span>'
    },
    { 
        data: 'total_bruto', 
        className: 'text-end',
        render: data => `R$ ${parseFloat(data || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` 
    },
    { 
        data: 'total_liquido', 
        className: 'text-end',
        render: data => `R$ ${parseFloat(data || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` 
    },
    { 
        data: 'desconto', 
        className: 'text-center',
        render: data => `${parseFloat(data || 0).toFixed(2)}%` 
    },
    { 
        data: 'acrescimo', 
        className: 'text-center',
        render: data => `${parseFloat(data || 0).toFixed(2)}%` 
    },
    { 
        data: 'observacao', 
        defaultContent: '-',
        className: 'text-muted small'
    },
    {
        data: null,
        className: 'text-center',
        orderable: false,
        searchable: false,
        width: '150px',
        render: function (row) {
            return `
                <div class="btn-group" role="group">
                    <button onclick="editSale(${row.id})" class="btn btn-warning btn-sm" title="Editar">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button onclick="deleteSale(${row.id})" class="btn btn-danger btn-sm" title="Excluir">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            `;
        }
    } 
]).getData(filter => api.sale.find(filter)); // 'sale' minúsculo conforme Preload.js

// Escuta evento de recarregamento (ex: após salvar ou excluir em outra aba)
api.sale.onReload(() => {
    table.ajax.reload(null, false);
});

// Função para Excluir Venda
async function deleteSale(id) {
    const result = await Swal.fire({
        title: 'Tem certeza?',
        text: 'Esta ação não pode ser desfeita e afetará os registros de venda.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sim, excluir!',
        cancelButtonText: 'Cancelar',
    });

    if (result.isConfirmed) {
        try {
            const response = await api.sale.delete(id);
            if (response.status) {
                Swal.fire('Excluído!', 'A venda foi removida com sucesso.', 'success');
                table.ajax.reload(null, false);
            } else {
                Swal.fire('Erro!', response.error || 'Não foi possível excluir a venda.', 'error');
            }
        } catch (error) {
            console.error("Erro ao deletar venda:", error);
            Swal.fire('Erro!', 'Ocorreu uma falha na comunicação com o banco.', 'error');
        }
    }
}

// Função para Abrir Tela de Edição
function editSale(id) {
    api.window.open('pages/sale', { maximized: true, title: 'Editar Venda', id: id });
}


// Vincula as funções ao objeto window para que os botões (onclick) funcionem no contexto do módulo
window.deleteSale = deleteSale;
window.editSale = editSale;