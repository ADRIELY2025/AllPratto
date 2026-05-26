import connection from '../database/Connection.js';

export default class Sale {
    // Tabela no banco
    static table = 'sale';
    static tableItem = 'item_sale';
    // Mapeamento: índice da coluna no DataTable → nome no banco
    static #columns = ['id', 'id_cliente', 'total_bruto', 'total_liquido', 'desconto', 'acrescimo', 'observacao', 'created_at', 'updated_at', null];
    // Colunas pesquisáveis pelo termo de busca
    static #searchable = ['id_cliente', 'total_bruto', 'total_liquido', 'desconto', 'acrescimo', 'observacao'];

    // Implementamos a pesquisa completa para a venda
  static async find(data = {}) {
    const { term = '', limit = 10, offset = 0, orderType = 'asc', column = 0, draw = 1 } = data;

    // 1. Query base com JOIN para pegar o nome do cliente
    const queryBase = connection(Sale.table)
        .leftJoin('customer', 'sale.id_cliente', 'customer.id')
        .select('sale.*', 'customer.nome as nome_cliente');

    // 2. Contagem total
    const [{ count: total }] = await connection(Sale.table).count('id as count');

    // 3. Filtro de busca (pesquisa no ID da venda, Observação ou Nome do Cliente)
    if (term) {
        queryBase.where(function() {
            this.whereRaw('CAST(sale.id AS TEXT) ILIKE ?', [`%${term}%`])
                .orWhere('sale.observacao', 'ILIKE', `%${term}%`)
                .orWhere('customer.nome', 'ILIKE', `%${term}%`);
        });
    }

    // 4. Contagem filtrada
    const filteredCountQuery = connection(Sale.table)
        .leftJoin('customer', 'sale.id_cliente', 'customer.id');
    if (term) {
        filteredCountQuery.where(function() {
            this.whereRaw('CAST(sale.id AS TEXT) ILIKE ?', [`%${term}%`])
                .orWhere('customer.nome', 'ILIKE', `%${term}%`);
        });
    }
    const [{ count: filtered }] = await filteredCountQuery.count('sale.id as count');

    // 5. Ordenação e Paginação
    const rows = await queryBase
        .orderBy(`sale.id`, orderType === 'desc' ? 'desc' : 'asc')
        .limit(parseInt(limit))
        .offset(parseInt(offset));

    return {
        draw: parseInt(draw),
        recordsTotal: parseInt(total),
        recordsFiltered: parseInt(filtered),
        data: rows,
    };
}


    // Retorna apenas uma venda pelo seu ID
    static async findById(id) {
        if (!id) return null;
        const row = await connection(Sale.table)
            .where({ id })
            .first();
        return row || null;
    }

    // Insere uma nova venda
    static async insert(data) {
        try {
            const raw = await connection(Sale.table).insert(data).returning('id');
            const id = Array.isArray(raw) ? raw[0]?.id ?? raw[0] : raw;
            return { status: true, id };
        } catch (error) {
            return { status: false, error: error.message };
        }
    }

    // Atualiza uma venda pelo ID
    static async update(id, data) {
        try {
            const affectedRows = await connection(Sale.table).where({ id }).update(data);
            return { status: affectedRows > 0 };
        } catch (error) {
            return { status: false, error: error.message };
        }
    }

    // Deleta uma venda pelo ID
    static async delete(id) {
        try {
            const affectedRows = await connection(Sale.table).where({ id }).del();
            return { status: affectedRows > 0 };
        } catch (error) {
            return { status: false, error: error.message };
        }
    }

    // Insere um item na venda
    static async insertItem(data) {
        const id = data['id'] ?? null;
        const id_produto = data['id_produto'] ?? null;
        const quantidade = parseFloat(data['quantidade']) || 1;
        const preco_unitario = parseFloat(data['preco_unitario'] ?? data['unitario_liquido'] ?? data['unitario_bruto']) || 0;

        // Verifica se o id da venda está vazio ou nulo
        if (!id) {
            return {
                status: false,
                msg: 'Restrição: O ID da venda é obrigatório!',
                id: 0
            };
        }

        // Verifica se o id do produto está vazio ou nulo
        if (!id_produto) {
            return {
                status: false,
                msg: 'Restrição: O ID do produto é obrigatório!',
                id: 0
            };
        }

        try {
            // Seleciona o produto que está sendo vendido
            const produto = await connection('product')
                .where({ id: id_produto })
                .first();

            if (!produto) {
                return {
                    status: false,
                    msg: 'Restrição: Nenhum produto localizado!',
                    id: 0
                };
            }

            // Calcula os valores baseado no preço unitário fornecido ou preço de venda do produto
            const precoUnitario = preco_unitario > 0 ? preco_unitario : parseFloat(produto.preco_venda || 0);
            const totalBruto = parseFloat((quantidade * precoUnitario).toFixed(4));
            const totalLiquido = totalBruto; // Inicialmente igual ao bruto

            const FieldAndValue = {
                id_venda: id,
                id_produto: id_produto,
                quantidade: quantidade,
                unitario_bruto: precoUnitario,
                unitario_liquido: precoUnitario,
                total_bruto: totalBruto,
                total_liquido: totalLiquido,
                desconto: 0,
                acrescimo: 0,
                nome: produto.nome
            };

            // Insere o item na venda
            const isInserted = await connection(Sale.tableItem)
                .insert(FieldAndValue);

            if (!isInserted) {
                return {
                    status: false,
                    msg: 'Restrição: Falha ao inserir o item da venda!',
                    id: 0
                };
            }

            // Soma os totais de todos os itens da venda
            const sale = await connection(Sale.tableItem)
                .where({ id_venda: id })
                .sum({ total_bruto: 'total_bruto', total_liquido: 'total_liquido' })
                .first();

            // Atualiza o total da venda
            await connection(Sale.table)
                .where({ id })
                .update({
                    total_bruto: parseFloat(sale.total_bruto || 0),
                    total_liquido: parseFloat(sale.total_liquido || 0)
                });

            return {
                status: true,
                msg: 'Item inserido com sucesso!',
                id: 0
            };

        } catch (error) {
            return {
                status: false,
                msg: 'Restrição: ' + error.message,
                id: 0
            };
        }
        
    }
    static async insertInstallmentSale(data) {
    try {
        await connection('installment_sale_purchase').insert({
            id_sale:          data.id_sale,
            id_purchase:      data.id_purchase      || 0,
            id_installment:   data.id_installment   || 0,
            id_payment_terms: data.id_payment_terms,
            total_parcelas:   data.total_parcelas,
            numero_parcela:   data.numero_parcela,
            valor_parcela:    data.valor_parcela,
            valor_total:      data.valor_total,
            status:           data.status           || 'aberto',
            data_vencimento:  data.data_vencimento
        });
        return { status: true };
    } catch (error) {
        return { status: false, error: error.message };
    }
}


// 
}