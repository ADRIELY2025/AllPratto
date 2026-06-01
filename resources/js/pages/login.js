import Validate from '../components/validate.js';
import Requests from '../components/requests.js';

const BtnLogin = document.getElementById('btnLogin');
const InputLogin = document.getElementById('login');
const InputSenha = document.getElementById('senha');

function setLoading(loading) {
    BtnLogin.disabled = loading;
    BtnLogin.textContent = loading ? 'Entrando…' : 'Login';
}

function markInvalid(input) {
    input.classList.add('is-invalid');
    input.addEventListener('input', () => input.classList.remove('is-invalid'), { once: true });
}

function clearErrors() {
    [InputLogin, InputSenha].forEach(el => el.classList.remove('is-invalid'));
}

function validateLoginForm() {
    Validate.SetForm('login-form');
    Validate.form.validate({
        rules: {
            login: { required: true },
            senha: { required: true },
        },
    });

    const ok = Validate.Validate();
    if (!ok) {
        Swal.fire({
            icon: 'warning',
            title: 'Campos obrigatórios',
            text: 'Preencha o usuário e a senha antes de continuar.',
            confirmButtonColor: '#198754',
        });
    }
    return ok;
}

async function handleLogin() {
    clearErrors();
    if (!validateLoginForm()) return;

    setLoading(true);
    try {
        const requests = new Requests();
        const response = await requests.setForm('login-form').post('/login');

        if (!response?.status) {
            Swal.fire({
                icon: 'error',
                title: 'Falha no login',
                text: response?.msg || 'Usuário ou senha incorretos.',
                confirmButtonColor: '#198754',
            });
            markInvalid(InputSenha);
            InputSenha.value = '';
            InputSenha.focus();
            return;
        }

        await Swal.fire({
            icon: 'success',
            title: 'Bem-vindo!',
            text: response.msg,
            timer: 1500,
            timerProgressBar: true,
            showConfirmButton: false,
        });

        window.location.href = '/home';

    } catch (error) {
        let titulo = 'Erro';
        let texto = error.message || 'Não foi possível conectar ao servidor.';

        if (texto.includes('429')) { titulo = 'Muitas tentativas'; texto = 'Sua conta foi temporariamente bloqueada. Tente novamente em alguns minutos.'; }
        else if (texto.includes('403')) { titulo = 'Acesso negado'; texto = 'Usuário ou senha incorretos.'; }
        else if (texto.includes('500')) { titulo = 'Erro no servidor'; texto = 'Ocorreu um problema interno. Tente novamente em instantes.'; }

        Swal.fire({ icon: 'error', title: titulo, text: texto, confirmButtonColor: '#198754' });
        markInvalid(InputSenha);
        InputSenha.value = '';
        InputSenha.focus();

    } finally {
        setLoading(false);
    }
}

BtnLogin.addEventListener('click', handleLogin);

document.getElementById('login-form').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); handleLogin(); }
});

function getCookie(name) {
    return document.cookie
        .split(';')
        .map(c => c.trim())
        .find(c => c.startsWith(name + '='))
        ?.split('=')[1] ?? null;
}

async function handleGoogleSignIn(credential) {
    try {
        // FormData → getParsedBody() do PHP consegue ler
        const formData = new FormData();
        formData.append('credential', credential);
        formData.append('g_csrf_token', getCookie('g_csrf_token') ?? '');

        const response = await fetch('/authentication/google', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }, // SEM Content-Type — FormData define sozinho
            body: formData,
        });

        const text = await response.text();
        let data = null;

        try {
            data = JSON.parse(text);
        } catch (err) {
            console.warn('Google auth response is not JSON', err, text);
        }

        if (!response.ok || !data?.status) {
            throw new Error(data?.msg || text || `Falha ao autenticar com Google. (${response.status})`);
        }

        await Swal.fire({
            icon: 'success',
            title: 'Bem-vindo!',
            text: data.msg,
            timer: 1500,
            timerProgressBar: true,
            showConfirmButton: false,
        });

        window.location.href = '/home';

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Falha no login com Google',
            text: error.message || 'Não foi possível conectar ao servidor.',
            confirmButtonColor: '#198754',
        });
    }
}

function handleCredentialResponse(response) {
    if (!response?.credential) {
        Swal.fire({
            icon: 'error',
            title: 'Falha no Google Sign-In',
            text: 'Não foi possível receber o token do Google.',
            confirmButtonColor: '#198754',
        });
        return;
    }
    handleGoogleSignIn(response.credential);
}

function initGoogleSignIn() {
    const button = document.getElementById('loginGoogle');
    const clientId = button?.dataset.clientId?.trim();

    if (!button || !clientId || !window.google?.accounts?.id) return;

    google.accounts.id.initialize({
        client_id: clientId,
        callback: handleCredentialResponse,
        auto_select: false,
        ux_mode: 'popup',   // popup funciona em localhost
        cancel_on_tap_outside: true,
    });

    // Renderiza o botão estilizado do Google sobre o botão HTML existente
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
});

window.openModal = function () {
    document.getElementById('overlay-cadastro').classList.add('active');
    document.body.style.overflow = 'hidden';
};

window.closeModal = function () {
    document.getElementById('overlay-cadastro').classList.remove('active');
    document.body.style.overflow = '';
};

document.getElementById('overlay-cadastro').addEventListener('click', (e) => {
    if (e.target === e.currentTarget) window.closeModal();
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') window.closeModal();
});

window.toggleChip = function (btn, type) {
    btn.classList.toggle('active');
    document.getElementById('contact-' + type)
        .classList.toggle('visible', btn.classList.contains('active'));
};

window.togglePw = function (id, btn) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.textContent = input.type === 'password' ? '👁' : '🙈';
};

document.getElementById('cad-cpf').addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').slice(0, 11);
    v = v
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
    this.value = v;
});

document.getElementById('cad-rg').addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').slice(0, 9);
    v = v
        .replace(/(\d{2})(\d)/, '$1.$2')
        .replace(/(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/(\d{2})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
    this.value = v;
});

['cad-celular', 'cad-whatsapp'].forEach(id => {
    document.getElementById(id).addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').slice(0, 11);
        v = v
            .replace(/(\d{2})(\d)/, '($1) $2')
            .replace(/(\d{1}) (\d{4})(\d)/, '$1 $2-$3');
        this.value = v;
    });
});

document.getElementById('cad-telefone').addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').slice(0, 10);
    v = v
        .replace(/(\d{2})(\d)/, '($1) $2')
        .replace(/(\d{4})(\d)/, '$1-$2');
    this.value = v;
});

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

function validarCadastro() {
    let valid = true;

    document.querySelectorAll('.err-msg').forEach(e => (e.textContent = ''));
    document.querySelectorAll('.field input').forEach(e => e.classList.remove('error'));

    const obrigatorios = {
        nome: { el: document.getElementById('cad-nome'), msg: 'Nome é obrigatório' },
        sobrenome: { el: document.getElementById('cad-sobrenome'), msg: 'Sobrenome é obrigatório' },
    };

    for (const [key, { el, msg }] of Object.entries(obrigatorios)) {
        if (!el.value.trim()) {
            document.getElementById('err-' + key).textContent = msg;
            el.classList.add('error');
            valid = false;
        }
    }

    const cpf = document.getElementById('cad-cpf').value.replace(/\D/g, '');
    if (cpf.length !== 11) {
        document.getElementById('err-cpf').textContent = 'CPF inválido';
        document.getElementById('cad-cpf').classList.add('error');
        valid = false;
    }

    const senha = document.getElementById('cad-senha').value;
    const conf = document.getElementById('cad-confirmar-senha').value;

    if (senha.length < 8) {
        document.getElementById('err-senha').textContent = 'Mínimo 8 caracteres';
        document.getElementById('cad-senha').classList.add('error');
        valid = false;
    }

    if (senha !== conf) {
        document.getElementById('err-confirmar-senha').textContent = 'As senhas não coincidem';
        document.getElementById('cad-confirmar-senha').classList.add('error');
        valid = false;
    }

    const emailAtivo = document.getElementById('contact-email').classList.contains('visible');
    const email = document.getElementById('cad-email').value.trim();

    if (emailAtivo && !email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
        document.getElementById('err-email').textContent = 'E-mail inválido';
        document.getElementById('cad-email').classList.add('error');
        valid = false;
    }

    return { valid, cpf, senha, email, emailAtivo };
}

document.getElementById('btnCadastrar').addEventListener('click', async () => {
    console.log('oi')
    const isValid = Validate.SetForm('register-form').Validate();
    if (!isValid) {
        Swal.fire({
            icon: 'warning',
            title: 'Campos obrigatórios',
            text: 'Preencha os campos obrigatórios antes de continuar.',
            confirmButtonColor: '#198754',
        });
        return;
    }
    const requests = new Requests();
    try {
        const response = await requests.setForm('register-form').post('/authentication/preregister');
        console.log(response);

    } catch (error) {

    }
});