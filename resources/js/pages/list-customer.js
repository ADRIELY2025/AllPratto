import DataTables from '../components/data-tables.js';
import Requests from '../components/requests.js';

const Id = document.getElementById('id');
const table = DataTables.SetId('table-customer').setRequestVariables([]).post('/cliente/listingdata');

async function deletecustomer() {
    const requests = new Requests();
    try {
        const response = await requests.setForm('form').post('/cliente/delete');
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
        title: "Atenção!",
        text: "Deseja realmente excluir este registro?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "Excluir"
    }).then(async (result) => {
        if (result.isConfirmed) {
            const response = await deletecustomer();
            if (!response.status) {
                Swal.fire({
                    title: "Erro!",
                    text: response.mesg,
                    icon: "error",
                    timer: 3000,
                    timerProgressBar: true
                });
                return;
            }
            Swal.fire({
                title: "Removido!",
                text: "Registro excluído com sucesso.",
                icon: "success",
                timer: 2000,
                timerProgressBar: true
            }).then(async () => {
                table.ajax.reload();
            });
        }
    });
}

window.ShowModal = ShowModal;
// ─── Gerar PDF de um cliente ─────────────────────────────────────────────────

async function gerarPdfCliente(id) {
    const modalEl = document.getElementById('modalPdfCliente');
    const content = document.getElementById('pdf-content-cliente');
    const modal   = new bootstrap.Modal(modalEl);

    content.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-info" role="status"></div>
            <p class="mt-2">Carregando dados do cliente #${id}...</p>
        </div>`;
    modal.show();

    try {
        const res  = await fetch(`/cliente/pdf/${id}`);
        const data = await res.json();

        if (!data.status) {
            content.innerHTML = `<div class="alert alert-danger">${data.msg || 'Erro ao carregar cliente.'}</div>`;
            return;
        }

        const c      = data.cliente;
        const vendas = data.vendas ?? [];

        const nomeCompleto = [c.nome_fantasia, c.sobrenome_razao].filter(Boolean).join(' ');

        const estadoVenda = {
            PRE_VENDA : 'Em edição',
            ORCAMENTO : 'Orçamento',
            VENDA     : 'Finalizada',
        };

        const linhasVendas = vendas.length
            ? vendas.map(v => `
                <tr>
                    <td>#${v.id}</td>
                    <td>${v.data_venda}</td>
                    <td class="text-end">R$ ${parseFloat(v.total_liquido || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
                    <td>${estadoVenda[v.estado_venda] ?? v.estado_venda}</td>
                </tr>`).join('')
            : `<tr><td colspan="4" class="text-center text-muted">Nenhuma venda registrada</td></tr>`;

        content.innerHTML = `
            <div id="area-pdf-cliente">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h4 class="fw-bold mb-1">AllPratto</h4>
                        <p class="text-muted mb-0 small">Sistema de Gestão</p>
                    </div>
                    <div class="text-end">
                        <h5 class="fw-bold text-info mb-1">FICHA DO CLIENTE #${c.id}</h5>
                        <p class="text-muted mb-0 small">Gerado em ${new Date().toLocaleString('pt-BR')}</p>
                    </div>
                </div>
                <hr>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="fw-bold text-secondary text-uppercase small mb-2">Dados Pessoais</h6>
                        <p class="mb-1 fw-semibold fs-5">${nomeCompleto || '<em class="text-muted">Não informado</em>'}</p>
                        <p class="mb-1 text-muted small">CPF/CNPJ: <strong>${c.cpf_cnpj || '-'}</strong></p>
                        ${c.nascimento_fundacao ? `<p class="mb-1 text-muted small">Nascimento: <strong>${c.nascimento_fundacao}</strong></p>` : ''}
                        ${c.rg_ie ? `<p class="mb-1 text-muted small">RG/IE: <strong>${c.rg_ie}</strong></p>` : ''}
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold text-secondary text-uppercase small mb-2">Status</h6>
                        <p class="mb-0">
                            <span class="badge ${c.ativo ? 'bg-success' : 'bg-secondary'}">${c.ativo ? 'Ativo' : 'Inativo'}</span>
                        </p>
                    </div>
                </div>

                <h6 class="fw-bold text-secondary text-uppercase small mb-2">Últimas Vendas</h6>
                <table class="table table-sm table-bordered mb-4">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Data</th>
                            <th class="text-end">Total Líquido</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>${linhasVendas}</tbody>
                </table>

                <hr>
                <p class="text-muted text-center small mb-0">Cadastrado em ${c.criado_em} — AllPratto</p>
            </div>`;

        document.getElementById('btn-imprimir-pdf-cliente').onclick = () => {
            const area = document.getElementById('area-pdf-cliente').innerHTML;
            const win  = window.open('', '_blank');
            win.document.write(`
                <!DOCTYPE html><html lang="pt-BR">
                <head>
                    <meta charset="UTF-8">
                    <title>Cliente #${c.id} — AllPratto</title>
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

window.gerarPdfCliente = gerarPdfCliente;
