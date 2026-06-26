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

// ─── Gerar PDF de uma venda ───────────────────────────────────────────────────

async function gerarPdfVenda(id) {
    const modalEl = document.getElementById('modalPdfVenda');
    const content = document.getElementById('pdf-content-venda');
    const modal   = new bootstrap.Modal(modalEl);

    content.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2">Carregando dados da venda #${id}...</p>
        </div>`;
    modal.show();

    try {
        const res  = await fetch(`/sale/pdf/${id}`);
        const data = await res.json();

        if (!data.status) {
            content.innerHTML = `<div class="alert alert-danger">${data.msg || 'Erro ao carregar venda.'}</div>`;
            return;
        }

        const v     = data.venda;
        const itens = data.itens ?? [];

        const estadoMap = {
            PRE_VENDA : '<span class="badge bg-warning text-dark">Em edição</span>',
            ORCAMENTO : '<span class="badge bg-info text-dark">Orçamento</span>',
            VENDA     : '<span class="badge bg-success">Finalizada</span>',
        };

        const linhasItens = itens.length
            ? itens.map(i => `
                <tr>
                    <td>${i.nome ?? '-'}</td>
                    <td class="text-center">${parseFloat(i.quantidade).toFixed(2)}</td>
                    <td class="text-end">R$ ${parseFloat(i.preco_unitario || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
                    <td class="text-end">R$ ${parseFloat(i.total_liquido || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
                </tr>`).join('')
            : `<tr><td colspan="4" class="text-center text-muted">Nenhum item registrado</td></tr>`;

        content.innerHTML = `
            <div id="area-pdf-venda">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h4 class="fw-bold mb-1">AllPratto</h4>
                        <p class="text-muted mb-0 small">Sistema de Gestão</p>
                    </div>
                    <div class="text-end">
                        <h5 class="fw-bold text-success mb-1">VENDA #${v.id}</h5>
                        <p class="text-muted mb-0 small">${v.criado_em}</p>
                    </div>
                </div>
                <hr>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="fw-bold text-secondary text-uppercase small mb-2">Cliente</h6>
                        <p class="mb-0 fw-semibold">${v.nome_cliente ?? '<em class="text-muted">Sem cliente</em>'}</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <h6 class="fw-bold text-secondary text-uppercase small mb-2">Status</h6>
                        <p class="mb-0">${estadoMap[v.estado_venda] ?? v.estado_venda}</p>
                    </div>
                </div>
                <h6 class="fw-bold text-secondary text-uppercase small mb-2">Itens da Venda</h6>
                <table class="table table-sm table-bordered mb-4">
                    <thead class="table-dark">
                        <tr>
                            <th>Produto</th>
                            <th class="text-center">Qtd</th>
                            <th class="text-end">Unitário</th>
                            <th class="text-end">Total Líquido</th>
                        </tr>
                    </thead>
                    <tbody>${linhasItens}</tbody>
                </table>
                <div class="row justify-content-end">
                    <div class="col-md-5">
                        <table class="table table-sm table-borderless">
                            <tbody>
                                <tr>
                                    <td class="text-muted">Total Bruto:</td>
                                    <td class="text-end fw-semibold">R$ ${parseFloat(v.total_bruto || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Desconto:</td>
                                    <td class="text-end text-danger">${parseFloat(v.desconto || 0).toFixed(2)}%</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Acréscimo:</td>
                                    <td class="text-end text-success">${parseFloat(v.acrescimo || 0).toFixed(2)}%</td>
                                </tr>
                                <tr class="border-top">
                                    <td class="fw-bold">Total Líquido:</td>
                                    <td class="text-end fw-bold text-success fs-5">R$ ${parseFloat(v.total_liquido || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                ${v.observacao ? `<div class="alert alert-light border mt-2"><strong>Observação:</strong> ${v.observacao}</div>` : ''}
                <hr>
                <p class="text-muted text-center small mb-0">Documento gerado em ${new Date().toLocaleString('pt-BR')}</p>
            </div>`;

        document.getElementById('btn-imprimir-pdf-venda').onclick = () => {
            const area = document.getElementById('area-pdf-venda').innerHTML;
            const win  = window.open('', '_blank');
            win.document.write(`
                <!DOCTYPE html><html lang="pt-BR">
                <head>
                    <meta charset="UTF-8">
                    <title>Venda #${v.id} — AllPratto</title>
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
                    <style>body{font-family:Arial,sans-serif;padding:30px;}@media print{body{padding:10px;}}</style>
                </head>
                <body>${area}</body></html>`);
            win.document.close();
            win.focus();
            setTimeout(() => { win.print(); }, 400);
        };

    } catch (err) {
        content.innerHTML = `<div class="alert alert-danger">Erro inesperado: ${err.message}</div>`;
    }
}

window.gerarPdfVenda = gerarPdfVenda;
