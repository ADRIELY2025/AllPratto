/**
 * render.js — geração de HTML dinâmico
 */
import { state } from './state.js';

function renderVisual(item) {
  if (item.imagemUrl) {
    return `<div class="card-img-wrap">
        <img src="${item.imagemUrl}" alt="${item.nome}" class="card-img" loading="lazy"
             onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
        <span class="card-emoji-fallback" style="display:none">${item.emoji ?? '🍽'}</span>
      </div>`;
  }
  return `<span class="card-emoji">${item.emoji ?? '🍽'}</span>`;
}

export function renderCardapio(dados) {
  const el = document.getElementById('menu-content');
  let html  = '';

  for (const [categoria, itens] of Object.entries(dados)) {
    html += `<div class="categoria"><h2>${categoria}</h2><div class="grid">`;

    for (const item of itens) {
      const precoFmt = 'R$ ' + Number(item.preco).toFixed(2).replace('.', ',');
      const badge    = item.destaque ? '<span class="destaque-badge">⭐ Destaque</span>' : '';
      const disabled = state.mesa ? '' : 'disabled title="Informe a mesa primeiro"';

      html += `
        <div class="card">
          <div class="card-top">
            ${renderVisual(item)}
            <h3>${item.nome}${badge}</h3>
          </div>
          <p>${item.descricao ?? ''}</p>
          <div class="card-footer">
            <div>
              <div class="preco">${precoFmt}</div>
              <div class="tempo">⏱ ${item.tempo ?? ''}</div>
            </div>
            <button class="btn-add" data-id="${item.id}" ${disabled}>+ Adicionar</button>
          </div>
        </div>`;
    }
    html += `</div></div>`;
  }

  el.innerHTML = html;
  state._itens = Object.values(dados).flat();

  // Delegação de evento — um listener só, sem onclick inline
  el.addEventListener('click', e => {
    const btn = e.target.closest('.btn-add');
    if (btn && !btn.disabled) {
      el.dispatchEvent(new CustomEvent('adicionar-item', {
        bubbles: true,
        detail:  { id: Number(btn.dataset.id) },
      }));
    }
  });
}

export function renderCarrinho() {
  const lista  = document.getElementById('lista-carrinho');
  const footer = document.getElementById('carrinho-footer');
  const badge  = document.getElementById('badge-count');

  const totalQty = state.carrinho.reduce((s, i) => s + i.quantidade, 0);
  const total    = state.carrinho.reduce((s, i) => s + i.preco * i.quantidade, 0);

  badge.textContent   = totalQty;
  badge.style.display = totalQty > 0 ? 'inline' : 'none';

  if (state.carrinho.length === 0) {
    lista.innerHTML      = '<p style="color:#888;font-size:.85rem;text-align:center;padding:10px">Carrinho vazio</p>';
    footer.style.display = 'none';
    return;
  }

  footer.style.display = 'block';
  document.getElementById('total-valor').textContent =
    'R$ ' + total.toFixed(2).replace('.', ',');

  lista.innerHTML = state.carrinho.map(item => {
    const sub   = 'R$ ' + (item.preco * item.quantidade).toFixed(2).replace('.', ',');
    const thumb = item.imagemUrl
      ? `<img src="${item.imagemUrl}" alt="" class="carrinho-thumb" onerror="this.style.display='none'">`
      : `<span>${item.emoji ?? '🍽'}</span>`;
    return `
      <div class="item-carrinho">
        <span class="item-nome">${thumb} ${item.nome}</span>
        <div class="item-qty">
          <button class="btn-qty" data-id="${item.id}" data-delta="-1">−</button>
          <span>${item.quantidade}</span>
          <button class="btn-qty" data-id="${item.id}" data-delta="1">+</button>
        </div>
        <span class="item-subtotal">${sub}</span>
        <button class="btn-remover" data-id="${item.id}" title="Remover">✕</button>
      </div>`;
  }).join('');
}