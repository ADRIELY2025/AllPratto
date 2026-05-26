import connection from '../database/Connection.js';
export default class ItemSale {
    static table = 'item_sale';

    static async insert(data) {
        if (!data || !data.id_venda || !data.id_produto) {
            return { status: false, msg: 'id_venda e id_produto são obrigatórios', id: null, data: [] };
        }

        try {
            const clean = ItemSale.#sanitize(data);
            const [result] = await connection(ItemSale.table)
                .insert(clean)
                .returning('*');

            return { status: true, msg: 'Item de venda salvo com sucesso!', id: result.id, data: [result] };
        } catch (err) {
            return { status: false, msg: 'Erro: ' + err.message, id: null, data: [] };
        }
    }

    static #sanitize(data) {
        const ignore = ['id', 'action'];
        const clean = {};

        for (const [key, value] of Object.entries(data)) {
            if (ignore.includes(key)) continue;
            if (value === '' || value === null || value === undefined) continue;
            if (value === 'true') { clean[key] = true; continue; }
            if (value === 'false') { clean[key] = false; continue; }
            if (typeof value === 'string' && /^[0-9]+(\.[0-9]+)?$/.test(value)) {
                clean[key] = Number(value);
                continue;
            }
            clean[key] = value;
        }

        return clean;
    }
}