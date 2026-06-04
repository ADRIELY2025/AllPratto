/**
 * carrinho.js — lógica do carrinho e seus event listeners
 */
import { state }          from './state.js';
import { renderCarrinho } from './render.js';
import { showToast }      from './ui.js';

export function adicionarItem(id) {
  const item = state._itens.find(i => i.id === id);
  if (!item) return;

  const existente = state.carrinho.find(i => i.id === id);
  existente ? existente.quantidade++ : state.carrinho.push({ ...item, quantidade: 1 });

  renderCarrinho();
  showToast('✅ ' + item.nome + ' adicionado!');
  if (!state.aberto) toggleCarrinho();
}

export function alterarQty(id, delta) {
  const idx = state.carrinho.findIndex(i => i.id === id);
  if (idx === -1) return;
  state.carrinho[idx].quantidade += delta;
  if (state.carrinho[idx].quantidade <= 0) state.carrinho.splice(idx, 1);
  renderCarrinho();
}

export function toggleCarrinho() {
  state.aberto = !state.aberto;
  document.getElementById('carrinho-body').classList.toggle('aberto', state.aberto);
  document.getElementById('carrinho-seta').textContent = state.aberto ? '▼' : '▲';
}

export function initCarrinhoListeners() {
  // Botões −/+ e remover
  document.getElementById('lista-carrinho').addEventListener('click', e => {
    const btnQty     = e.target.closest('.btn-qty');
    const btnRemover = e.target.closest('.btn-remover');
    if (btnQty)     alterarQty(Number(btnQty.dataset.id),     Number(btnQty.dataset.delta));
    if (btnRemover) alterarQty(Number(btnRemover.dataset.id), -999);
  });

  // Abrir/fechar carrinho
  document.getElementById('carrinho-header').addEventListener('click', toggleCarrinho);

  // Evento disparado pelo render do cardápio
  document.addEventListener('adicionar-item', e => adicionarItem(e.detail.id));
}