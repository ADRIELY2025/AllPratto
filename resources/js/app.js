// 1. jQuery global — DEVE ser o primeiro import.
import './jquery-global.js'

// 2. Bootstrap 5 — JS bundle inclui Popper internamente
import 'bootstrap'

// DataTables core + extensões com tema Bootstrap 5
// Estes pacotes registram $.fn.DataTable, $.fn.dataTable etc. ao serem carregados
import 'datatables.net-bs5'
import 'datatables.net-responsive-bs5'
import 'datatables.net-staterestore-bs5'

//Select2 integrado com Bootstrap 5
import 'select2'
import 'select2/dist/css/select2.min.css'
import 'select2-bootstrap-5-theme/dist/select2-bootstrap-5-theme.min.css'
import select2 from 'select2'
select2(window, window.jQuery)

// jQuery Validate — registra $.fn.validate
import 'jquery-validation'
import 'jquery-validation/dist/localization/messages_pt_BR.js'
import 'jquery-validation/dist/localization/methods_pt.js'

// 3. SweetAlert2 — usado como Swal.fire(...) nos arquivos de página
import Swal from 'sweetalert2'
window.Swal = Swal

// 4. Biblioteca de mascaras
import Inputmask from 'inputmask';
window.Inputmask = Inputmask.default ?? Inputmask;

// 4. Biblioteca de calandário 
import flatpickr from 'flatpickr'
import { Portuguese } from 'flatpickr/dist/l10n/pt.js'
flatpickr.localize(Portuguese)
window.flatpickr = flatpickr

// 5. Importa o script do Echarts e registra globalmente
import * as echarts from 'echarts'
window.echarts = echarts