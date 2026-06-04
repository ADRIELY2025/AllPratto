/**
 * main.js — ponto de entrada, inicializa tudo
 * Carregado com <script type="module"> no index.html
 */
import { fetchCardapio }        from './api.js';
import { renderCardapio }       from './render.js';
import { initCarrinhoListeners } from './carrinho.js';
import { initModalListeners }   from './ui.js';
import { initPedidoListeners }  from './pedido.js';

async function init() {
  initCarrinhoListeners();
  initModalListeners();
  initPedidoListeners();

  try {
    const dados = await fetchCardapio();
    document.getElementById('menu-loading').style.display = 'none';
    renderCardapio(dados);
  } catch {
    document.getElementById('menu-loading').textContent =
      'Erro ao carregar o cardápio. Tente recarregar a página.';
  }
}

document.addEventListener('DOMContentLoaded', init);