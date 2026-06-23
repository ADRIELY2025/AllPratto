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

// 7. Plugins e configuração global do FilePond — upload seguro e visualização de imagens
//import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';
//import FilePondPluginImageExifOrientation from 'filepond-plugin-image-exif-orientation';
import FilePondPluginImagePreview from 'filepond-plugin-image-preview';
//import FilePondPluginImageCrop from 'filepond-plugin-image-crop';
//import FilePondPluginImageResize from 'filepond-plugin-image-resize';
//import FilePondPluginImageTransform from 'filepond-plugin-image-transform';


// Registro único no boot — reutiliza o FilePond já importado no passo 6.
// Ordem importa: validação → orientação → preview → crop → resize → transform.
FilePond.registerPlugin(
    //FilePondPluginFileValidateType,
    //FilePondPluginImageExifOrientation,
    FilePondPluginImagePreview,
    //FilePondPluginImageCrop,
    //FilePondPluginImageResize,
    //FilePondPluginImageTransform,
);

// Defaults globais seguros e performáticos — cada página pode sobrescrever.
FilePond.setOptions({
    // Segurança: primeira linha no cliente (a barreira real continua no servidor).
    acceptedFileTypes: ['image/png', 'image/jpeg', 'image/webp'],
    maxParallelUploads: 2,

    // Visualização.
    allowImagePreview: true,
    imagePreviewHeight: 220,

    // Performance: reduz e re-encoda a imagem ANTES do upload.
    allowImageResize: true,
    imageResizeTargetWidth: 1600,
    imageResizeTargetHeight: 1600,
    imageResizeMode: 'contain',
    imageResizeUpscale: false,
    imageTransformOutputQuality: 80,

    // Rótulos em pt-BR.
    labelIdle: 'Arraste e solte a imagem ou <span class="filepond--label-action">selecione</span>',
    labelInvalidField: 'Campo contém arquivos inválidos',
    labelFileWaitingForSize: 'Calculando tamanho',
    labelFileLoading: 'Carregando',
    labelFileLoadError: 'Erro ao carregar',
    labelFileProcessing: 'Enviando',
    labelFileProcessingComplete: 'Envio concluído',
    labelFileProcessingError: 'Erro no envio',
    labelFileProcessingAborted: 'Envio cancelado',
    labelTapToCancel: 'toque para cancelar',
    labelTapToRetry: 'toque para repetir',
    labelTapToUndo: 'toque para desfazer',
    labelFileTypeNotAllowed: 'Tipo de arquivo inválido',
    fileValidateTypeLabelExpectedTypes: 'Esperado {allButLastType} ou {lastType}',

    // Estético.
    credits: false,
});