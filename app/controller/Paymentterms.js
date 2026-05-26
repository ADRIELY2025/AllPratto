import connection from '../database/Connection.js';
export default class PaymentTerms {
    // Tabela no banco
    static table = 'payment_terms';
    //teste
    // Mapeamento: índice da coluna no DataTable → nome no banco
    static #columns = ['id', 'codigo', 'titulo'];

    //Insere um novo condição de pagamento.
    static async insert(data) {
        if (data.codigo === null || data.titulo === null) {
            return { status: false, msg: 'Preencha corretamento os dados para salvar', id: null, data: [] };
        }
        try {

            const clean = {
                codigo: data.codigo,
                titulo: data.titulo
            }

            //Inserir no banco de dados 
            const [result] = await connection(PaymentTerms.table)
                .insert(clean)
                .returning('*');

            const response = { status: true, msg: 'Salvo com sucesso!', id: result.id, data: result };

            return response;
        } catch (error) {
            return { status: false, msg: 'Erro: ' + error.message, id: null, data: [] };
        }
    }

    // Atualiza uma condição de pagamento
    static async update(id, data) {
        if (!id) {
            return { status: false, msg: 'ID é obrigatório', id: null, data: [] };
        }

        try {
            const clean = PaymentTerms.#sanitize(data);

            const affectedRows = await connection(PaymentTerms.table)
                .where({ id: id })
                .update(clean);

            return {
                status: affectedRows > 0,
                msg: affectedRows > 0 ? 'Atualizado com sucesso!' : 'Registro não encontrado',
                id: id,
                data: []
            };
        } catch (error) {
            return { status: false, msg: 'Erro: ' + error.message, id: null, data: [] };
        }
    }

    // Deleta uma condição de pagamento
    static async delete(id) {
        if (!id) {
            return { status: false, msg: 'ID é obrigatório' };
        }

        try {
            const affectedRows = await connection(PaymentTerms.table)
                .where({ id: id })
                .del();

            return {
                status: affectedRows > 0,
                msg: affectedRows > 0 ? 'Deletado com sucesso!' : 'Registro não encontrado'
            };
        } catch (error) {
            return { status: false, msg: 'Erro: ' + error.message };
        }
    }

    // Busca todas as condições de pagamento
    static async find(data = {}) {
        const { term = '', limit = 10, offset = 0 } = data;

        try {
            let query = connection(PaymentTerms.table);

            if (term) {
                query = query.where(function () {
                    this.where('codigo', 'like', `%${term}%`)
                        .orWhere('titulo', 'like', `%${term}%`);
                });
            }

            const rows = await query
                .limit(parseInt(limit))
                .offset(parseInt(offset));

            return { status: true, msg: 'Encontrado!', data: rows };
        } catch (error) {
            return { status: false, msg: 'Erro: ' + error.message, data: [] };
        }
    }

    // Busca uma condição de pagamento pelo ID
    static async findById(id) {
        try {
            const result = await connection(PaymentTerms.table)
                .where({ id: id })
                .first();

            return { status: true, msg: 'Encontrado!', id: null, data: result ? [result] : [] };
        } catch (error) {
            return { status: false, msg: 'Erro: ' + error.message, id: null, data: [] };
        }
    }
    static async findAll() {
        try {
            const rows = await connection(PaymentTerms.table)
                .select('*')
                .orderBy('id', 'asc');
            return { status: true, data: rows };
        } catch (error) {
            return { status: false, msg: error.message, data: [] };
        }
    }

    static async findInstallments(id_pagamento) {
        try {
            const rows = await connection('installment')
                .where({ id_pagamento })
                .orderBy('parcela', 'asc');
            return { status: true, data: rows };
        } catch (error) {
            return { status: false, msg: error.message, data: [] };
        }
    }
    //Remove campos vazios e converte tipos.
    static #sanitize(data) {
        // Campos de controle do form — não existem no banco
        const ignore = ['id', 'acao', 'parcela', 'intervalo', 'id_parcelamento'];

        const clean = {};

        for (const [key, value] of Object.entries(data)) {
            if (ignore.includes(key)) continue;
            if (value === '' || value === null || value === undefined) continue;
            if (value === 'true') { clean[key] = true; continue; }
            if (value === 'false') { clean[key] = false; continue; }
            clean[key] = value;
        }

        return clean;
    }
}