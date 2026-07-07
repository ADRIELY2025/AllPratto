import Validate from "../components/validate.js";
import Requests from "../components/requests.js";

const BtnLogin = document.getElementById('btnLogin');
const BtnCadastrar = document.getElementById('btnCadastrar');
const OverlayCadastro = document.getElementById('overlay-cadastro');

// ============================================================================
// LOGIN
// ============================================================================
BtnLogin.addEventListener('click', async () => {
    try {
        const response = await new Requests().setForm('login-form').post('/login');
        
        if (!response?.status) {
            Swal.fire({
                title: "Atenção!",
                text: response?.msg || 'Erro ao fazer login',
                icon: "error",
                timer: 3000
            });
            return;
        }

        Swal.fire({
            title: "Bem-vindo!",
            text: response.msg,
            icon: "success",
            timer: 2000
        }).then(() => {
            window.location.href = '/home';
        });
    } catch (error) {
        Swal.fire({
            title: "Erro",
            text: error.message || "Erro ao conectar ao servidor",
            icon: "error",
            timer: 3000
        });
    }
});

// ============================================================================
// GOOGLE SIGN-IN
// ============================================================================
function getCookie(name) {
    return document.cookie
        .split(';')
        .map(c => c.trim())
        .find(c => c.startsWith(name + '='))
        ?.split('=')[1] ?? null;
}

async function handleGoogleSignIn(credential) {
    try {
        const formData = new FormData();
        formData.append('credential', credential);
        formData.append('g_csrf_token', getCookie('g_csrf_token') ?? '');

        const response = await fetch('/authentication/google', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            body: formData,
        });

        const data = await response.json();

        if (!response.ok || !data?.status) {
            throw new Error(data?.msg || 'Falha ao autenticar com Google');
        }

        Swal.fire({
            title: "Bem-vindo!",
            text: data.msg,
            icon: "success",
            timer: 2000
        }).then(() => {
            window.location.href = '/home';
        });
    } catch (error) {
        Swal.fire({
            title: "Erro",
            text: error.message,
            icon: "error",
            timer: 3000
        });
    }
}

function handleCredentialResponse(response) {
    if (response?.credential) {
        handleGoogleSignIn(response.credential);
    }
}

function initGoogleSignIn() {
    const button = document.getElementById('loginGoogle');
    const clientId = button?.dataset.clientId?.trim();
    if (!clientId || !window.google?.accounts?.id) return;

    google.accounts.id.initialize({
        client_id: clientId,
        callback: handleCredentialResponse,
        auto_select: false,
        ux_mode: 'popup',
        cancel_on_tap_outside: true,
    });

    google.accounts.id.renderButton(button, {
        type: 'standard',
        theme: 'outline',
        size: 'large',
        text: 'continue_with',
    });
}

window.addEventListener('load', () => {
    const googleScript = document.querySelector('script[src*="accounts.google.com/gsi/client"]');
    if (!googleScript) return;
    if (window.google?.accounts?.id) {
        initGoogleSignIn();
    } else {
        googleScript.addEventListener('load', initGoogleSignIn);
    }

    if (window.Inputmask) {
        Inputmask({ mask: '999.999.999-99' }).mask('#cad-cpf');
        Inputmask({ mask: '(99) 99999-9999' }).mask('#cad-celular');
        Inputmask({ mask: '(99) 9999-9999' }).mask('#cad-telefone');
        Inputmask({ mask: '(99) 99999-9999' }).mask('#cad-whatsapp');
    }
});

// ============================================================================
// CADASTRO
// ============================================================================
window.openModal = () => {
    OverlayCadastro.classList.add('active');
    document.body.style.overflow = 'hidden';
};

window.closeModal = () => {
    OverlayCadastro.classList.remove('active');
    document.body.style.overflow = '';
};

OverlayCadastro.addEventListener('click', (e) => {
    if (e.target === e.currentTarget) window.closeModal();
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') window.closeModal();
});

window.toggleChip = (btn, type) => {
    btn.classList.toggle('active');
    document.getElementById('contact-' + type).classList.toggle('visible', btn.classList.contains('active'));
};

window.togglePw = (id, btn) => {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.textContent = input.type === 'password' ? '👁' : '🙈';
};

// Indicador de força da senha
document.getElementById('cad-senha').addEventListener('input', function () {
    const pw = this.value;
    let score = 0;

    if (pw.length >= 8) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    const colors = ['#e05252', '#e07c52', '#d4b800', '#1e6b45'];
    const labels = ['Muito fraca', 'Fraca', 'Média', 'Forte'];

    for (let i = 1; i <= 4; i++) {
        document.getElementById('s' + i).style.background = i <= score ? colors[score - 1] : '#eee';
    }

    const lbl = document.getElementById('strength-label');
    lbl.textContent = pw.length ? (labels[score - 1] ?? '') : '';
    lbl.style.color = pw.length ? (colors[score - 1] ?? '#aaa') : '#aaa';
});

// Validação e Cadastro
BtnCadastrar.addEventListener('click', async () => {
    let valid = true;
    document.querySelectorAll('.err-msg').forEach(e => (e.textContent = ''));
    document.querySelectorAll('#register-form input').forEach(e => e.classList.remove('error'));

    const nome = document.getElementById('cad-nome').value.trim();
    const sobrenome = document.getElementById('cad-sobrenome').value.trim();
    const cpf = document.getElementById('cad-cpf').value.replace(/\D/g, '');
    const senha = document.getElementById('cad-senha').value;
    const confirmarSenha = document.getElementById('cad-confirmar-senha').value;
    const emailAtivo = document.getElementById('contact-email').classList.contains('visible');
    const email = document.getElementById('cad-email').value.trim();

    // Validações
    if (!nome) {
        document.getElementById('err-nome').textContent = 'Nome é obrigatório';
        document.getElementById('cad-nome').classList.add('error');
        valid = false;
    }

    if (!sobrenome) {
        document.getElementById('err-sobrenome').textContent = 'Sobrenome é obrigatório';
        document.getElementById('cad-sobrenome').classList.add('error');
        valid = false;
    }

    if (cpf.length !== 11) {
        document.getElementById('err-cpf').textContent = 'CPF inválido';
        document.getElementById('cad-cpf').classList.add('error');
        valid = false;
    }

    if (senha.length < 8) {
        document.getElementById('err-senha').textContent = 'Mínimo 8 caracteres';
        document.getElementById('cad-senha').classList.add('error');
        valid = false;
    }

    if (senha !== confirmarSenha) {
        document.getElementById('err-confirmar-senha').textContent = 'As senhas não coincidem';
        document.getElementById('cad-confirmar-senha').classList.add('error');
        valid = false;
    }

    if (emailAtivo && !email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
        document.getElementById('err-email').textContent = 'E-mail inválido';
        document.getElementById('cad-email').classList.add('error');
        valid = false;
    }

    if (!valid) {
        Swal.fire({
            title: "Campos inválidos",
            text: "Corrija os erros antes de continuar",
            icon: "warning",
            timer: 3000
        });
        return;
    }

    // Monta dados para envio
    const formData = new FormData();
    formData.append('cad-nome', nome);
    formData.append('cad-sobrenome', sobrenome);
    formData.append('cad-cpf', cpf);
    formData.append('cad-rg', document.getElementById('cad-rg').value);
    formData.append('cad-senha', senha);
    formData.append('cad-confirmar-senha', confirmarSenha);

    // Monta contatos
    const contacts = [];
    if (emailAtivo && email) {
        contacts.push({ tipo: 'EMAIL', contato: email });
    }
    if (document.getElementById('contact-celular').classList.contains('visible')) {
        const celular = document.getElementById('cad-celular').value.trim();
        if (celular) contacts.push({ tipo: 'CELULAR', contato: celular });
    }
    if (document.getElementById('contact-telefone').classList.contains('visible')) {
        const telefone = document.getElementById('cad-telefone').value.trim();
        if (telefone) contacts.push({ tipo: 'TELEFONE', contato: telefone });
    }
    if (document.getElementById('contact-whatsapp').classList.contains('visible')) {
        const whatsapp = document.getElementById('cad-whatsapp').value.trim();
        if (whatsapp) contacts.push({ tipo: 'WHATSAPP', contato: whatsapp });
    }

    if (contacts.length > 0) {
        formData.append('contacts', JSON.stringify(contacts));
    }

    try {
        const response = await fetch('/authentication/preregister', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            body: formData,
        });

        const data = await response.json();

        if (!response.ok || !data?.status) {
            Swal.fire({
                title: "Erro no cadastro",
                text: data?.msg || 'Ocorreu um erro',
                icon: "error",
                timer: 3000
            });
            return;
        }

        Swal.fire({
            title: "Sucesso!",
            text: data.msg || 'Conta criada com sucesso',
            icon: "success",
            timer: 2000
        }).then(() => {
            window.closeModal();
            document.getElementById('register-form').reset();
        });
    } catch (error) {
        Swal.fire({
            title: "Erro",
            text: error.message || 'Erro ao conectar',
            icon: "error",
            timer: 3000
        });
    }
});