export default class Validate {
    static form;
    static SetForm(id) {
        // checa dependências
        if (typeof jQuery === 'undefined') {
            throw new Error('jQuery não encontrado. Certifique-se de incluir jQuery antes deste script.');
        }
        if (typeof jQuery.validator === 'undefined') {
            throw new Error('jQuery Validation plugin não encontrado. Inclua o plugin jquery.validate.js.');
        }
        // configura defaults do plugin (continua compatível com seu código original)
        jQuery.validator.setDefaults({
            // rules podem ser definidos via atributos HTML ou ao chamar .validate({ rules: ... }) no form
            errorElement: 'span',
            errorPlacement: function (error, element) {
                error.addClass('invalid-feedback');
                element.closest('.form-group').append(error);
            },
            highlight: function (element, errorClass, validClass) {
                jQuery(element).addClass('is-invalid');
            },
            unhighlight: function (element, errorClass, validClass) {
                jQuery(element).removeClass('is-invalid');
            }
        });
        this.form = jQuery(`#${id}`);
        if (!this.form || this.form.length === 0) {
            throw new Error("Formulário não encontrado!");
        }
        // inicializa o validator para o formulário (gera o validator e aplica regras/placements)
        // não sobrescrevemos regras existentes: se você quiser regras por JS, pode passar um objeto aqui
        this.form.validate();
        return this;
    }
    static Validate() {
        if (!this.form || this.form.length === 0) {
            throw new Error("Formulário não inicializado. Chame Validate.SetForm(id) primeiro.");
        }
        // garante que o validator esteja inicializado
        if (!this.form.data('validator')) {
            this.form.validate();
        }
        // retorna boolean indicando se o formulário é válido
        return this.form.valid();
    }
}