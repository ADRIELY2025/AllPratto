/**
 * api.js — todas as chamadas fetch, zero lógica de DOM
 */
export async function fetchCardapio() {
  const res = await fetch('/cardapio/itens');
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  const json = await res.json();
  if (!json.sucesso) throw new Error('Falha ao carregar cardápio');
  return json.dados;
}

export async function postPedido({ mesa, pagamento, itens }) {
  const res = await fetch('/cardapio/pedido', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ mesa, pagamento, itens }),
  });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return await res.json();
}