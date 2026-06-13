import Requests from '../components/requests.js';

const Id      = document.getElementById('id');
const BtnSalvar = document.getElementById('btn-salvar');

// Carrega os itens do pedido ao abrir a página
async function carregarItens() {
    const pedidoId = Id.value;
    if (!pedidoId) return;

    try {
        const requests = new Requests();
        const response = await requests.get(`/pedido/itens/${pedidoId}`);

        if (!response.status || !response.itens.length) return;

        const tbody = document.getElementById('tbody-itens');
        let total = 0;

        response.itens.forEach(item => {
            const subtotal = parseFloat(item.subtotal);
            total += subtotal;
            tbody.innerHTML += `
                <tr>
                    <td>${item.nome}</td>
                    <td class="text-end">${item.quantidade}</td>
                    <td class="text-end">R$ ${parseFloat(item.preco).toFixed(2).replace('.', ',')}</td>
                    <td class="text-end">R$ ${subtotal.toFixed(2).replace('.', ',')}</td>
                </tr>`;
        });

        document.getElementById('total-itens').textContent =
            'R$ ' + total.toFixed(2).replace('.', ',');
        document.getElementById('itens-container').style.display = '';
    } catch (error) {
        // Sem itens ou erro silencioso — não bloqueia a tela
        console.warn('Itens não carregados:', error.message);
    }
}

// Salva apenas o status do pedido
async function salvarStatus() {
    $('button').prop('disabled', true);

    const form = document.getElementById('form');
    const body = new FormData(form);

    const requests = new Requests();
    try {
        const response = await requests.setBody(body).post('/pedido/update-status');

        if (!response.status) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: response.msg || 'Não foi possível atualizar o status.',
                timer: 3000,
                timerProgressBar: true,
            });
            return;
        }

        Swal.fire({
            icon: 'success',
            title: 'Sucesso',
            text: response.msg || 'Status atualizado!',
            timer: 2000,
            timerProgressBar: true,
        }).then(() => {
            window.location.href = '/pedido/lista';
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
        $('button').prop('disabled', false);
    }
}

// Inicia a página
carregarItens();
BtnSalvar.addEventListener('click', async () => {
    await salvarStatus();
});