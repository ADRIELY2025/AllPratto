/**
 * pedido.js — envio do pedido para o backend
 */
import { state }         from './state.js';
import { postPedido }    from './api.js';
import { renderCarrinho } from './render.js';
import { toggleCarrinho } from './carrinho.js';
import { showToast }      from './ui.js';

export async function enviarPedido() {
  if (!state.mesa)              { showToast('⚠️ Informe sua mesa!', 'erro'); return; }
  if (!state.carrinho.length)   { showToast('Carrinho vazio!', 'erro');      return; }

  const pagamento = document.getElementById('select-pagamento').value;
  const btn       = document.getElementById('btn-pedido');
  btn.disabled    = true;
  btn.textContent = 'Enviando…';

  try {
    const json = await postPedido({
      mesa:  state.mesa,
      pagamento,
      itens: state.carrinho.map(i => ({ id: i.id, quantidade: i.quantidade })),
    });

    if (json.sucesso) {
      showToast('🎉 Pedido #' + json.pedido_id + ' enviado!', 'sucesso');
      state.carrinho = [];
      renderCarrinho();
      if (state.aberto) toggleCarrinho();
    } else {
      showToast('Erro: ' + (json.erro || 'Tente novamente'), 'erro');
    }
  } catch {
    showToast('Erro de conexão. Tente novamente.', 'erro');
  } finally {
    btn.disabled    = false;
    btn.textContent = 'Fazer Pedido';
  }
}

export function initPedidoListeners() {
  document.getElementById('btn-pedido').addEventListener('click', enviarPedido);
}