import Requests from "./requests.js";

export default class FindCompany {
    constructor({ cnpjField = '', cnaeValue = '', cnaeSearch = 'codigoAtividadeEconomica' } = {}) {
        this.cnpjField = cnpjField;
        this.cnaeValue = cnaeValue;
        this.cnaeSearch = cnaeSearch;
    }
    // Valida o CPF
    #isValidCPF(cpf) {
        if (!cpf) return false;

        const cleaned = cpf.replace(/\D/g, '');

        if (cleaned.length !== 11) return false;

        // Reject known invalid sequences
        if (/^(\d)\1{10}$/.test(cleaned)) return false;

        const digits = cleaned.split('').map(Number);

        // Validate first check digit
        let sum = 0;
        for (let i = 0; i < 9; i++) {
            sum += digits[i] * (10 - i);
        }
        let firstCheckDigit = (sum * 10) % 11;
        if (firstCheckDigit === 10) firstCheckDigit = 0;

        if (firstCheckDigit !== digits[9]) return false;

        // Validate second check digit
        sum = 0;
        for (let i = 0; i < 10; i++) {
            sum += digits[i] * (11 - i);
        }
        let secondCheckDigit = (sum * 10) % 11;
        if (secondCheckDigit === 10) secondCheckDigit = 0;

        return secondCheckDigit === digits[10];
    }
    // Valida o CNPJ
    #isValidCNPJ(cnpj) {
        if (!cnpj) return false;

        const cleaned = cnpj.replace(/\D/g, '');

        if (cleaned.length !== 14) return false;

        // Reject known invalid sequences
        if (/^(\d)\1{13}$/.test(cleaned)) return false;

        const digits = cleaned.split('').map(Number);

        const calculateCheckDigit = (baseDigits, weights) => {
            const sum = baseDigits.reduce(
                (acc, digit, index) => acc + digit * weights[index],
                0
            );
            const remainder = sum % 11;
            return remainder < 2 ? 0 : 11 - remainder;
        };

        const firstWeights = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        const secondWeights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        const firstCheckDigit = calculateCheckDigit(digits.slice(0, 12), firstWeights);
        if (firstCheckDigit !== digits[12]) return false;

        const secondCheckDigit = calculateCheckDigit(digits.slice(0, 13), secondWeights);
        return secondCheckDigit === digits[13];
    }
    // Preenche os dados da empresa com base no CNPJ
    #FillCompanyData(companyData = {}) {
        if (!companyData || typeof companyData !== 'object' || Object.keys(companyData).length === 0) {
            Swal?.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Nenhum dado da empresa encontrado.',
                timer: 3000,
                timerProgressBar: true,
            });
            return;
        }

        const form = document.querySelector('form');
        if (!form) {
            console.warn('Formulário não encontrado no DOM.');
            return;
        }

        const normalizeDateBR = (value) => {
            if (!value) return '';
            const [year, month, day] = value.split('T')[0].split('-');
            return `${day}/${month}/${year}`;
        };

        const fieldMap = {
            numeroDocumento: data =>
                data.estabelecimento?.cnpj ?? '',
            nomeExibicao: data =>
                data.estabelecimento?.nome_fantasia
                ?? data.razao_social
                ?? '',
            nomeLegal: data =>
                data.razao_social ?? '',
            registroSecundario: data =>
                data.estabelecimento?.inscricoes_estaduais?.[0]?.inscricao_estadual ?? '',
            dataRegistro: data =>
                normalizeDateBR(data.estabelecimento?.data_inicio_atividade),
            regimeTributario: data => {
                if (!data.simples) return '3';
                return data.simples?.excesso_sublimite ? '2' : '1';
            },
            codigoAtividadeEconomica: data =>
                data.estabelecimento?.atividade_principal?.id ?? '',
            ativo: data =>
                data.estabelecimento?.situacao_cadastral === 'Ativa',
            cnae: data =>
                data.estabelecimento?.atividade_principal?.classe.replace(/\D/g, '') ?? '',
        };

        Object.entries(fieldMap).forEach(([fieldId, resolver]) => {
            const field = form.querySelector(`#${fieldId}`);
            if (!field) return;

            const value = resolver(companyData);

            switch (field.type) {
                case 'checkbox':
                    field.checked = Boolean(value);
                    break;

                default:
                    if (field.value.trim() !== '') break;
                    field.value = value ?? '';
                    break;
            }

            field.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }
    #ValidadeDocument(cpfOrCnpj = '') {
        return (cpfOrCnpj.length === 11) ? this.#isValidCPF(cpfOrCnpj) : this.#isValidCNPJ(cpfOrCnpj);
    }
    #CreateCompanyListCnae(cnaeData = {}) {
        if (!cnaeData || typeof cnaeData !== 'object' || Object.keys(cnaeData).length === 0) {
            Swal?.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Nenhum código de atividade da empresa encontrado.',
                timer: 3000,
                timerProgressBar: true,
            });
            return;
        }
        let html = ``;
        cnaeData.forEach((item) => {
            (document.getElementById(this.cnaeValue).value == item.id) ?
                html += `<option value="${item.id}" selected >${item.id} - ${item.descricao}</option>`
                :
                html += `<option value="${item.id}">${item.id} - ${item.descricao}</option>`;
        });
        document.getElementById(this.cnaeSearch).innerHTML = html;
        $(`#${this.cnaeSearch}`).select2({
            theme: 'bootstrap-5',
            language: 'pt-BR',
            removeItemButton: true,
            placeholder: true,
            selectionCssClass: 'select2--large',
            dropdownCssClass: 'select2--large',
        });
        $('.select2bs4').on('select2:open', function (e) {
            $('.select2-search__field').attr('placeholder', 'Digite para pesquisar...');
            let inputElement = document.querySelector(`input.select2-search__field`);
            inputElement.focus();
        });
    }
    async FindCompanyData() {
        const cnpjElement = document.getElementById(this.cnpjField);
        const cnpjValue = cnpjElement.value.replace(/\D/g, '');
        const isValid = this.#ValidadeDocument(cnpjValue);
        if (!isValid) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Documento inválido.',
                timer: 3000,
                timerProgressBar: true,
            });
            return;
        }
        const requests = new Requests();
        try {
            const response = await requests.get(`https://publica.cnpj.ws/cnpj/${cnpjValue}`);
            this.#FillCompanyData(response);
        } catch (error) {
            console.error('Erro ao buscar dados da empresa:', error);
        }
    }
    async FindCompanyCnae() {
        const requests = new Requests();
        try {
            const response = await requests.get(`https://servicodados.ibge.gov.br/api/v2/cnae/classes`);
            this.#CreateCompanyListCnae(response);
        } catch (error) {
            console.error('Erro ao buscar dados do CNAE:', error);
        }
    }
}