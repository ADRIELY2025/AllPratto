// ─── Estado em Memória
const installmentItems = [];
let currentPaymentTermsId = null;

const codigo = document.getElementById('codigo');
const insertPaymentoTermsButton = document.getElementById('insertPaymentoTermsButton');
const insertInstallmentButton = document.getElementById('insertInstallmentButton');

// ─── Funções Auxiliares
function getTableBody() {
  return document.querySelector('#tbInstallments');
}

function createEmptyRow() {
  const row = document.createElement('tr');
  row.innerHTML = '<td colspan="4" class="text-center text-muted py-4">Nenhuma parcela adicionada.</td>';
  return row;
}

function addInstallmentItem(item) {
  installmentItems.push(item);
  renderInstallments();
}

async function removeInstallmentItem(index) {
  if (!Number.isNaN(index) && index >= 0 && index < installmentItems.length) {
    const item = installmentItems[index];

    // Se tem ID, é do banco - deletar antes
    if (item.id) {
      try {
        const response = await api.installment.delete(item.id);
        if (!response.status) {
          toast('error', 'Erro', 'Erro ao deletar parcela do banco!', 3000);
          return;
        }
      } catch (error) {
        toast('error', 'Erro', error.message, 3000);
        return;
      }
    }

    installmentItems.splice(index, 1);
    renderInstallments();
    toast('success', 'Sucesso', 'Parcela removida com sucesso!', 3000);
  }
}

function clearInstallmentItems() {
  installmentItems.length = 0;
  renderInstallments();
}

function renderInstallments() {
  const tableBody = getTableBody();
  if (!tableBody) return;

  tableBody.innerHTML = '';

  if (installmentItems.length === 0) {
    tableBody.appendChild(createEmptyRow());
    return;
  }

  installmentItems.forEach((item, index) => {
    const row = document.createElement('tr');
    row.dataset.index = index;

    row.innerHTML = `
      <td class="ps-3">${item.parcela}X</td>
      <td>${item.parcela}</td>
      <td>${item.intervalo} Dias</td>
      <td class="text-center">
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeInstallmentItem(${index})">
          <i class="bi bi-trash"></i>
        </button>
      </td>
    `;

    tableBody.appendChild(row);
  });
}

// Salvar a condição de pagamento (sem fechar)
async function SavePaymentTermsOnly() {
  const form = document.getElementById('form');
  const data = formToJson(form);

  // Validar campos obrigatórios
  if (!data.codigo || !data.titulo) {
    toast('error', 'Erro', 'Preencha o tipo e título da condição de pagamento!', 3000);
    return false;
  }

  try {
    const response = (document.getElementById('acao').value === 'c') ?
      await api.paymentTerms.insert(data)
      :
      await api.paymentTerms.update(document.getElementById('id').value, data);

    if (!response.status) {
      toast('error', 'Erro', response.msg, 3000);
      return false;
    }

    currentPaymentTermsId = response.id;
    document.getElementById('id').value = response.id;
    document.getElementById('acao').value = 'e';
    
    toast('success', 'Sucesso', 'Condição de pagamento salva!', 3000);
    
    return true;

  } catch (erro) {
    toast('error', 'Erro', erro.message, 3000);
    return false;
  }
}

// Salvar a condição de pagamento e FECHAR a janela
async function InsertPaymentTerms() {
  const result = await SavePaymentTermsOnly();
  
  if (result) {
    // Fechar janela após 1 segundo
    setTimeout(() => {
      api.window.close();
    }, 1000);
  }
  
  return result;
}

// Salvar todas as parcelas no banco
async function SaveAllInstallments() {
  if (!currentPaymentTermsId) {
    toast('error', 'Erro', 'Salve a condição de pagamento primeiro!', 3000);
    return;
  }

  try {
    for (const item of installmentItems) {
      const installmentData = {
        id_pagamento: currentPaymentTermsId,
        parcela: item.parcela,
        intervalo: item.intervalo
      };

      const response = await api.installment.insert(installmentData);

      if (!response.status) {
        toast('error', 'Erro', `Erro ao salvar parcela ${item.parcela}: ${response.msg}`, 3000);
        return;
      }
    }

    toast('success', 'Sucesso', 'Todas as parcelas foram salvas com sucesso!', 3000);
    await LoadInstallmentsFromDatabase();

  } catch (error) {
    toast('error', 'Erro', error.message, 3000);
  }
}

// Adicionar parcela imediatamente no banco
async function InsertInstallment() {
  const parcelaInput = document.getElementById('parcela');
  const intervaloInput = document.getElementById('intervalo');
  const paymentTermsId = document.getElementById('id').value;

  const parcela = parseInt(parcelaInput.value) || 0;
  const intervalo = parseInt(intervaloInput.value) || 0;

  // Validar campos
  if (parcela <= 0 || intervalo <= 0) {
    toast('error', 'Erro', 'Digite valores válidos para parcelas e intervalo!', 3000);
    return;
  }

  // Se for criação (acao = 'c'), precisa salvar o payment terms PRIMEIRO (SEM FECHAR)
  if (document.getElementById('acao').value === 'c') {
    if (!currentPaymentTermsId) {
      const result = await SavePaymentTermsOnly();
      if (!result) return; // Se falhar, não continua
    }
  } else if (!paymentTermsId) {
    toast('error', 'Erro', 'Salve a condição de pagamento primeiro!', 3000);
    return;
  }

  try {
    // Salvar parcela no banco IMEDIATAMENTE
    const installmentData = {
      id_pagamento: currentPaymentTermsId || paymentTermsId,
      parcela: parcela,
      intervalo: intervalo
    };

    const response = await api.installment.insert(installmentData);

    if (!response.status) {
      toast('error', 'Erro', `Erro ao salvar parcela: ${response.msg}`, 3000);
      return;
    }

    // Limpar campos
    parcelaInput.value = '';
    intervaloInput.value = '';
    parcelaInput.focus();

    // Recarregar lista do banco
    await LoadInstallmentsFromDatabase();
    
    toast('success', 'Sucesso', 'Parcela adicionada com sucesso!', 3000);

  } catch (error) {
    toast('error', 'Erro', error.message, 3000);
  }
}

// Carregar parcelas do banco
async function LoadInstallmentsFromDatabase() {
  const id_pagamento = document.getElementById('id').value;

  if (!id_pagamento) {
    clearInstallmentItems();
    return;
  }

  try {
    const response = await api.installment.findByPaymentTerms(id_pagamento);

    if (!response.status) {
      toast('error', 'Erro', response.msg, 3000);
      return;
    }

    clearInstallmentItems();
    response.data.forEach(element => {
      addInstallmentItem({
        id: element.id,
        parcela: element.parcela,
        intervalo: element.intervalo
      });
    });

  } catch (error) {
    toast('error', 'Erro', error.message, 3000);
  }
}

// ─── Listeners de Eventos
codigo.addEventListener('change', () => {
  const codigoSelecionado = codigo.value;
  const parcelaInput = document.getElementById('parcela');
  const intervaloInput = document.getElementById('intervalo');
  
  // Atualizar título
  document.getElementById('titulo_campo').value = codigo.options[codigo.selectedIndex].text;
  
  // Se for Dinheiro (01), bloquear campos de parcelas
  if (codigoSelecionado === '01') {
    parcelaInput.disabled = true;
    intervaloInput.disabled = true;
    parcelaInput.value = '';
    intervaloInput.value = '';
    document.getElementById('titulo_campo').focus();
  } else {
    // Se for outro tipo de pagamento, desbloquear e focar em parcelas
    parcelaInput.disabled = false;
    intervaloInput.disabled = false;
    parcelaInput.focus();
  }
});

insertPaymentoTermsButton.addEventListener('click', async () => {
  await InsertPaymentTerms();
});

insertInstallmentButton.addEventListener('click', async () => {
  await InsertInstallment();
});

// Carregar parcelas quando página carrega
document.addEventListener('DOMContentLoaded', () => {
  LoadInstallmentsFromDatabase();
  
  // Aplicar estado inicial dos campos de parcelas
  const codigoSelecionado = codigo.value;
  const parcelaInput = document.getElementById('parcela');
  const intervaloInput = document.getElementById('intervalo');
  
  if (codigoSelecionado === '01') {
    parcelaInput.disabled = true;
    intervaloInput.disabled = true;
  } else {
    parcelaInput.disabled = false;
    intervaloInput.disabled = false;
  }
});

// Expor funções globais
window.removeInstallmentItem = removeInstallmentItem;