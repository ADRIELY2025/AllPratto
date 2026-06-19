import Requests from '../components/requests.js';

// ─── Estado local dos itens ───────────────────────────────────────────────────

/**
 * @type {Array<{
 *   id_item_sale: number,
 *   id_produto:   number,
 *   nome_produto: string,
 *   quantidade:   number,
 *   preco_unitario: number,
 *   total: number
 * }>}
 */
export const saleItems = [];

// ─── Utilitários de formatação ────────────────────────────────────────────────

export function stringParaFloat(valor) {
    if (valor === null || valor === undefined || valor === '') return 0;

    let str = valor.toString().trim();

    // Remove prefixo R$ e espaços
    str = str.replace(/R\$\s*/g, '').trim();

    // Caso já seja um número válido (sem máscara), retorna direto
    if (/^-?\d+(\.\d+)?$/.test(str)) {
        return parseFloat(str) || 0;
    }

    // Formato brasileiro: 1.234,56 → separador de milhar é "." e decimal é ","
    // Remove todos os pontos (separador de milhar) e troca vírgula por ponto
    str = str.replace(/\./g, '').replace(',', '.');

    return parseFloat(str) || 0;
}

export function floatParaBR(valor) {
    return Number(valor).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

// ─── Atualiza totais do resumo lateral ───────────────────────────────────────

export function updateTotals() {
    const subtotal         = saleItems.reduce((sum, item) => sum + (item.total || 0), 0);
    const descontoPercent  = parseFloat(document.getElementById('desconto')?.value  || 0) || 0;
    const acrescimoPercent = parseFloat(document.getElementById('acrescimo')?.value || 0) || 0;
    const valorDesconto    = (subtotal * descontoPercent)  / 100;
    const valorAcrescimo   = (subtotal * acrescimoPercent) / 100;
    const totalLiquido     = subtotal - valorDesconto + valorAcrescimo;

    const elBruto   = document.getElementById('total_bruto');
    const elLiquido = document.getElementById('total_liquido');
    if (elBruto)   elBruto.textContent   = floatParaBR(subtotal);
    if (elLiquido) elLiquido.textContent = floatParaBR(totalLiquido);
}

// ─── Remove item do banco + array ────────────────────────────────────────────

async function removeItemFromDB(idItemSale) {
    try {
        const requests = new Requests();
        const fd = new FormData();
        fd.append('id', idItemSale);
        const result = await requests.setBody(fd).post('/sale/item/delete');
        return result?.status === true;
    } catch (e) {
        console.error('Erro ao remover item do banco:', e);
        return false;
    }
}

export async function removeItem(index) {
    if (index < 0 || index >= saleItems.length) return;

    const item = saleItems[index];

    // Se o item já foi persistido, remove do banco
    if (item.id_item_sale) {
        const ok = await removeItemFromDB(item.id_item_sale);
        if (!ok) {
            Swal.fire({ icon: 'error', title: 'Erro', text: 'Não foi possível remover o item.' });
            return;
        }
    }

    saleItems.splice(index, 1);
    renderItems();
}

// ─── Renderiza a tabela de itens ──────────────────────────────────────────────

export function renderItems() {
    const tbody = document.querySelector('#sale-items-table tbody');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (saleItems.length === 0) {
        tbody.innerHTML = `
            <tr id="empty-row">
                <td colspan="7" class="text-center text-muted py-4">
                    <i class="fa-solid fa-inbox fa-lg mb-1 d-block"></i>
                    Nenhum item adicionado.
                </td>
            </tr>`;
        updateTotals();
        return;
    }

    saleItems.forEach((item, index) => {
        const tr = document.createElement('tr');
        tr.dataset.index = index;
        tr.innerHTML = `
            <td class="text-muted small">#${item.id_produto}</td>
            <td>${item.nome_produto}</td>
            <td class="text-end">${floatParaBR(item.quantidade)}</td>
            <td class="text-end">R$ ${floatParaBR(item.preco_unitario)}</td>
            <td class="text-end">R$ ${floatParaBR(item.total)}</td>
            <td class="text-center"></td>
        `;

        const btnRemover = document.createElement('button');
        btnRemover.type      = 'button';
        btnRemover.className = 'btn btn-sm btn-danger';
        btnRemover.title     = 'Remover item';
        btnRemover.innerHTML = '<i class="fa-solid fa-trash"></i>';
        btnRemover.addEventListener('click', () => removeItem(index));
        tr.querySelector('td:last-child').appendChild(btnRemover);

        tbody.appendChild(tr);
    });

    updateTotals();
}

// ─── Insere item no banco e adiciona ao array ─────────────────────────────────

/**
 * @param {object}  product     Objeto do produto vindo da API
 * @param {number}  currentSaleId  ID da venda já criada
 * @param {string}  quantity    Valor bruto do campo quantidade (string mascarada)
 * @param {string}  unitPrice   Valor bruto do campo preço unitário (string mascarada)
 * @returns {Promise<boolean>}
 */
export async function insertItem(product, currentSaleId, quantity, unitPrice) {
    const qty   = stringParaFloat(quantity)  || 0;
    const price = stringParaFloat(unitPrice) || parseFloat(product.preco_venda) || 0;
    const total = parseFloat((qty * price).toFixed(4));

    // Persiste no banco
    const requests = new Requests();
    const fd = new FormData();
    fd.append('id_venda',         currentSaleId);
    fd.append('id_produto',       product.id);
    fd.append('nome',             product.nome);
    fd.append('descricao',        product.descricao || '');
    fd.append('quantidade',       qty);
    fd.append('total_bruto',      total);
    fd.append('unitario_bruto',   price);
    fd.append('total_liquido',    total);
    fd.append('unitario_liquido', price);
    fd.append('desconto',         0);
    fd.append('acrescimo',        0);

    let itemResult;
    try {
        itemResult = await requests.setBody(fd).post('/sale/item/insert');
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Erro', text: e.message });
        return false;
    }

    if (!itemResult?.status) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: itemResult?.msg || 'Não foi possível inserir o item.',
        });
        return false;
    }

    // Adiciona ao topo da lista local
    saleItems.unshift({
        id_item_sale:   itemResult.id,
        id_produto:     product.id,
        nome_produto:   product.nome,
        quantidade:     qty,
        preco_unitario: price,
        total,
    });

    renderItems();
    return true;
}

// ─── Limpa o array local (sem chamadas ao banco) ──────────────────────────────

export function clearItems() {
    saleItems.length = 0;
    renderItems();
}
