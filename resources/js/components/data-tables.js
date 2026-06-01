export default class DataTables {
    static Id = '';
    static requestVariables = [];
    static setRequestVariables(variables = []) {
        if (!Array.isArray(variables)) {
            throw new Error("O parâmetro 'variables' deve ser um array");
        }
        this.requestVariables = variables;
        return this;
    }
    static SetId(value = '') {
        this.Id = value;
        if (!this.Id) {
            throw new Error("O parâmetro 'Id' é nulo, indefinido ou vazio");
        }
        return this;
    }
    static get() { }
    static post(Url = '') {
        try {
            if (!Url) {
                throw new Error('O parâmetro "url" é nulo, indefinido ou vazio');
            }
            // Destruir a tabela se já existir
            if ($.fn.DataTable.isDataTable("#" + this.Id)) {
                $("#" + this.Id).DataTable().destroy();
                // Limpar o conteúdo da tabela
                $("#" + this.Id).empty();
            }
            let table = new $("#" + this.Id).DataTable({
                paging: true,
                lengthChange: true,
                searching: true,
                ordering: true,
                info: true,
                autoWidth: false,
                responsive: true,
                stateSave: true,
                select: true,
                processing: true,
                serverSide: true,
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/2.3.6/i18n/pt-BR.json',
                    searchPlaceholder: 'Digite sua pesquisa...'
                },
                ajax: {
                    url: Url,
                    type: 'POST',
                    data: (d) => {
                        // Adiciona as variáveis de requisição
                        let requestData = {};
                        DataTables.requestVariables.forEach(variable => {
                            requestData[variable] = $('#' + variable).val();
                        });
                        // Adiciona os dados padrão do DataTable
                        return $.extend({}, d, requestData);
                    }
                },
                layout: {
                    topStart: 'search',
                    topEnd: 'pageLength',
                    bottomStart: 'info',
                    bottomEnd: 'paging'
                },
                // ✅ Aqui aplicamos a estilização após a tabela estar pronta
                initComplete: function () {
                    setTimeout(() => {
                        // Remove o label "Pesquisar"
                        const label = document.querySelector('.dt-search label');
                        if (label) {
                            label.remove(); // Remove completamente do DOM
                        }
                        // Seleciona div que contém o campo de pesquisa
                        const searchDiv = document.querySelector('.row > div.dt-layout-start');
                        if (searchDiv) {
                            searchDiv.classList.remove('col-md-auto');
                            searchDiv.classList.add('col-lg-6', 'col-md-6', 'col-sm-12');
                        }
                        const divSearch = document.querySelector('.dt-search');
                        if (divSearch) {
                            divSearch.classList.add('w-100'); // ou w-100, w-75 etc.
                        }

                        const input = document.querySelector('#dt-search-0');
                        if (input) {
                            input.classList.remove('form-control-sm'); // ou w-100, w-75 etc.
                            input.classList.add('form-control-md', 'w-100'); // ou w-100, w-75 etc.
                            // Remove margem e padding da esquerda
                            input.style.marginLeft = '0';
                            input.focus();
                        }
                        const pageLength = document.querySelector('#dt-length-0');
                        if (pageLength) {
                            pageLength.classList.add('form-select-md'); // ou form-select-sm, dependendo do tamanho desejado
                        }
                    }, 100);
                }
            });
            $(`#${this.Id} tbody`).on('click', 'tr', function () {
                $(this).toggleClass('selected');
            });

            $('#button').click(function () {
                alert(table.rows('.selected').data().length + ' row(s) selected');
            });
            window.addEventListener('resize', () => {
                table.ajax.reload(null, false);
            });
            table.ajax.reload(null, false);
            return table;
        } catch (error) {
            throw new Error("Ocorreu um erro ao enviar os dados: " + error.message);
        }
    }
}