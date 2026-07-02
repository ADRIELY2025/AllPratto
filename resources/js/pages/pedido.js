import 'select2';
import Requests from '../components/requests.js';

const Id = document.getElementById('id');
const BtnSalvar = document.getElementById('btn-salvar');
const StatusSelect = document.getElementById('status');
const CardAdicionar = document.getElementById('card-adicionar-item');
const AlertaBloqueado = document.getElementById('alerta-bloqueado');
const AlertaBloqueadoStatus = document.getElementById('alerta-bloqueado-status');

const STATUS_FINAIS = ['pronto', 'entregue', 'pago', 'cancelado'];

function strParaFloat(v) {
    if (!v && v !== 0) return 0;
    let s = v.toString().trim().replace(/R\$\s*/g, '').trim();
    if (/^-?\d+(\.\d+)?$/.test(s)) return parseFloat(s) || 0;
    s = s.replace(/\./g, '').replace(',', '.');
    return parseFloat(s) || 0;
}

function floatParaBR(v) {
    return Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function maskDecimal(input) {
    input.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '');
        v = (parseInt(v || '0', 10) / 100).toFixed(2);
        this.value = v.replace('.', ',');
    });
}

function initProdutoSelect() {
    const $select = $('#id_produto');
    if (!$select.length) return;

    $select.select2({
        theme: 'bootstrap-5',
        placeholder: 'Buscar produto...',
        allowClear: true,
        language: 'pt-BR',
        minimumInputLength: 0,
        ajax: {
            url: '/sale/find-product',
            type: 'POST',
            delay: 250,
            data: params => ({ term: params.term || '', limit: 100, offset: 0 }),
            processResults: json => ({
                results: (json.data || []).map(item => ({
                    id: item.id,
                    text: item.nome + (item.codigo ? ' [' + item.codigo + ']' : ''),
                    preco: item.preco_venda ?? item.preco ?? 0,
                    nome: item.nome,
                })),
            }),
        },
    });

    $select.on('select2:select', e => {
        const data = e.params.data;
        document.getElementById('unitario').value = floatParaBR(data.preco || 0);
        recalcularTotalItemForm();
    });
    $select.on('select2:clear', () => {
        document.getElementById('unitario').value = '';
        document.getElementById('valor-total').value = '';
    });
}

function recalcularTotalItemForm() {
    const preco = strParaFloat(document.getElementById('unitario').value);
    const qtd = strParaFloat(document.getElementById('quantidade').value) || 1;
    document.getElementById('valor-total').value = floatParaBR(preco * qtd);
}

function aplicarTravaEdicao(status) {
    const bloqueado = STATUS_FINAIS.includes(status);

    CardAdicionar.style.display = bloqueado ? 'none' : '';
    AlertaBloqueado.classList.toggle('d-none', !bloqueado);
    if (bloqueado) {
        AlertaBloqueadoStatus.textContent = status;
    }

    document.querySelectorAll('.btn-cancelar-item').forEach(btn => {
        btn.disabled = bloqueado || btn.dataset.jaCancelado === '1';
    });
}

function linhaItemHTML(item) {
    const cancelado = item.status === 'cancelado';
    const subtotal = parseFloat(item.subtotal);

    return `
        <tr data-item-id="${item.id}" class="${cancelado ? 'text-muted' : ''}">
            <td class="${cancelado ? 'text-decoration-line-through' : ''}">
                ${item.nome}
                ${cancelado ? '<span class="badge bg-secondary ms-1">Cancelado</span>' : ''}
            </td>
            <td class="text-end ${cancelado ? 'text-decoration-line-through' : ''}">${item.quantidade}</td>
            <td class="text-end ${cancelado ? 'text-decoration-line-through' : ''}">R$ ${parseFloat(item.preco).toFixed(2).replace('.', ',')}</td>
            <td class="text-end ${cancelado ? 'text-decoration-line-through' : ''}">R$ ${subtotal.toFixed(2).replace('.', ',')}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger btn-cancelar-item"
                    data-item-id="${item.id}" data-ja-cancelado="${cancelado ? '1' : '0'}"
                    ${cancelado ? 'disabled' : ''} title="Cancelar item">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </td>
        </tr>`;
}

function recalcularTotalTabela() {
    let total = 0;
    document.querySelectorAll('#tbody-itens tr').forEach(tr => {
        if (tr.classList.contains('text-muted')) return;
        const cells = tr.querySelectorAll('td');
        total += strParaFloat(cells[3].textContent);
    });
    document.getElementById('total-itens').textContent = 'R$ ' + floatParaBR(total);
    const totalInput = document.getElementById('total-pedido-input');
    if (totalInput) totalInput.value = 'R$ ' + floatParaBR(total);
}

async function carregarItens() {
    const pedidoId = Id.value;
    if (!pedidoId) return;

    try {
        const requests = new Requests();
        const response = await requests.get(`/pedido/itens/${pedidoId}`);

        if (!response.status || !response.itens.length) return;

        const tbody = document.getElementById('tbody-itens');
        tbody.innerHTML = response.itens.map(linhaItemHTML).join('');

        recalcularTotalTabela();
        document.getElementById('itens-container').style.display = '';
        aplicarTravaEdicao(StatusSelect.value);
    } catch (error) {
        console.warn('Itens não carregados:', error.message);
    }
}

async function cancelarItem(btn) {
    const itemId = btn.dataset.itemId;

    const confirmacao = await Swal.fire({
        icon: 'warning',
        title: 'Cancelar item?',
        text: 'O item ficará marcado como cancelado e a cozinha será avisada. Ele não será removido do pedido.',
        showCancelButton: true,
        confirmButtonText: 'Sim, cancelar',
        cancelButtonText: 'Voltar',
    });
    if (!confirmacao.isConfirmed) return;

    btn.disabled = true;
    try {
        const fd = new FormData();
        fd.append('order_item_id', itemId);

        const requests = new Requests();
        const response = await requests.setBody(fd).post('/pedido/item/cancelar');

        if (!response.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: response.msg, timer: 3000, timerProgressBar: true });
            btn.disabled = false;
            return;
        }

        const tr = document.querySelector(`tr[data-item-id="${itemId}"]`);
        if (tr) {
            const cells = tr.querySelectorAll('td');
            cells[0].classList.add('text-decoration-line-through');
            cells[0].innerHTML += ' <span class="badge bg-secondary ms-1">Cancelado</span>';
            cells[1].classList.add('text-decoration-line-through');
            cells[2].classList.add('text-decoration-line-through');
            cells[3].classList.add('text-decoration-line-through');
            tr.classList.add('text-muted');
        }
        btn.dataset.jaCancelado = '1';
        recalcularTotalTabela();

        Swal.fire({ icon: 'success', title: 'Item cancelado', timer: 1500, timerProgressBar: true, showConfirmButton: false });
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: error.message, timer: 3000, timerProgressBar: true });
        btn.disabled = false;
    }
}

async function inserirItem() {
    const $produto = $('#id_produto');
    const produtoData = $produto.select2('data')[0];

    if (!produtoData || !produtoData.id) {
        Swal.fire({ icon: 'warning', title: 'Selecione um produto', timer: 2000, timerProgressBar: true });
        return;
    }

    const qtd = strParaFloat(document.getElementById('quantidade').value) || 1;
    const preco = strParaFloat(document.getElementById('unitario').value);

    const btn = document.getElementById('insert-item');
    btn.disabled = true;

    try {
        const fd = new FormData();
        fd.append('order_id', Id.value);
        fd.append('product_id', produtoData.id);
        fd.append('nome', produtoData.nome || produtoData.text);
        fd.append('preco', preco);
        fd.append('quantidade', qtd);

        const requests = new Requests();
        const response = await requests.setBody(fd).post('/pedido/item/adicionar');

        if (!response.status) {
            Swal.fire({ icon: 'error', title: 'Erro', text: response.msg, timer: 3000, timerProgressBar: true });
            return;
        }

        $produto.val(null).trigger('change');
        document.getElementById('unitario').value = '';
        document.getElementById('valor-total').value = '';
        document.getElementById('quantidade').value = '1,00';

        await carregarItens();
        Swal.fire({ icon: 'success', title: 'Item adicionado!', timer: 1500, timerProgressBar: true, showConfirmButton: false });
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Erro', text: error.message, timer: 3000, timerProgressBar: true });
    } finally {
        btn.disabled = false;
    }
}

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

initProdutoSelect();
maskDecimal(document.getElementById('quantidade'));
carregarItens();
aplicarTravaEdicao(StatusSelect.value);

document.getElementById('quantidade').addEventListener('input', recalcularTotalItemForm);
document.getElementById('insert-item').addEventListener('click', inserirItem);

document.getElementById('tbody-itens').addEventListener('click', e => {
    const btn = e.target.closest('.btn-cancelar-item');
    if (btn && !btn.disabled) cancelarItem(btn);
});

BtnSalvar.addEventListener('click', async () => {
    await salvarStatus();
});