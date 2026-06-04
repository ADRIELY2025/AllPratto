/**
 * state.js — estado global compartilhado entre módulos
 * mesa vem do window.CARDAPIO_CONFIG injetado pelo Twig
 */
export const state = {
  mesa:     window.CARDAPIO_CONFIG?.mesa ?? null,
  carrinho: [],   // [{ id, nome, preco, emoji, imagemUrl, quantidade }]
  aberto:   false,
  _itens:   [],   // cache flat dos itens vindos da API
};