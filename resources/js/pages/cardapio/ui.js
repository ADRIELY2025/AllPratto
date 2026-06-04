/**
 * ui.js — toast e modal de mesa
 */
import { state } from './state.js';

export function showToast(msg, tipo = '') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className   = 'show ' + tipo;
  setTimeout(() => { t.className = tipo; }, 2500);
}

export function confirmarMesa() {
  const val = parseInt(document.getElementById('input-mesa').value, 10);
  if (val >= 1 && val <= 99) {
    state.mesa = val;
    document.getElementById('modal-mesa').classList.remove('show');
    document.querySelector('header p').textContent = 'Mesa ' + state.mesa;
    document.querySelectorAll('.btn-add').forEach(b => {
      b.disabled = false;
      b.removeAttribute('title');
    });
  } else {
    alert('Digite um número de mesa entre 1 e 99');
  }
}

export function initModalListeners() {
  document.querySelector('#modal-mesa button').addEventListener('click', confirmarMesa);
  document.getElementById('input-mesa').addEventListener('keydown', e => {
    if (e.key === 'Enter') confirmarMesa();
  });
}