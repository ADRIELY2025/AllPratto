export default class Requests {
    constructor({ baseUrl = '', token = null, credentials = 'same-origin' } = {}) {
        this.baseUrl = baseUrl;
        this.token = token;
        this.credentials = credentials;
        this.headers = {
            Accept: 'application/json'
        };
        this.body = null;
    }

    // Define token Bearer para autenticação
    setToken(token) {
        this.token = token;
        return this;
    }

    // Define formulário via ID e cria FormData
    setForm(formId) {
        const form = document.getElementById(formId);
        if (!form) {
            throw new Error(`Formulário com id "${formId}" não encontrado`);
        }
        this.body = new FormData(form);
        return this;
    }

    // Define body manualmente (JSON, FormData, etc)
    setBody(body) {
        this.body = body;
        return this;
    }

    // Método POST
    async post(endpoint) {
        return this.#request('POST', endpoint);
    }

    // Método GET
    async get(endpoint) {
        return this.#request('GET', endpoint);
    }

    //Core request handler
    async #request(method, endpoint) {
        const url = `${this.baseUrl}${endpoint}`;
        const headers = { ...this.headers };
        if (this.token) {
            headers.Authorization = `Bearer ${this.token}`;
        }
        const options = {
            method,
            headers,
            credentials: this.credentials,
            body: method === 'GET' ? null : this.body,
            cache: 'no-store'
        };
        let response;
        try {
            response = await fetch(url, options);
        } catch (error) {
            throw new Error(`Falha de rede ao acessar ${url}`);
        }
        if (!response.ok) {
            const errorBody = await this.#safeParseJson(response);
            // ✅ CORRIGIDO: verifica 'erro' (padrão do cardápio) antes de 'msg' e 'message'
            const errorMessage = errorBody?.erro || errorBody?.msg || errorBody?.message || response.statusText;
            const error = new Error(errorMessage);
            error.status = response.status;
            error.body = errorBody;
            throw error;
        }
        return this.#safeParseJson(response);
    }

    // Evita erro ao tentar parsear body vazio
    async #safeParseJson(response) {
        const text = await response.text();
        return text ? JSON.parse(text) : null;
    }
}