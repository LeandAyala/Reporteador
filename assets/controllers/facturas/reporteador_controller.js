import mensajes from '../central/mensajes';
import { Controller } from "@hotwired/stimulus";

export default class extends Controller 
{
    editor = null;
    lineaError = -1;
    mensaje = new mensajes();

    static values = 
    {
        'urlGuardarInforme' : String
    };
    static targets = 
    [
        'btnGuardarInforme'
    ];

    connect()
    {
        var self = this;
        console.log('connect');
        $('.selectpicker').selectpicker('refresh');
        $('#btnRegresar').on('click', function(){$(this).html('<i class="fas fa-spinner fa-spin"></i> Regresando')});
        const editor = CodeMirror.fromTextArea(document.getElementById('nuevo_informe_sql'), 
        {
            mode: 'text/x-sql',
            smartIndent: true,
            lineNumbers: true,
            matchBrackets: true,
            indentWithTabs: true,
        });
        editor.replaceRange('', { line: 0, ch: 0 }, { line: 10, ch: 0 });
        this.editor = editor;
    }

    async showModalNuevoInforme(event)
    {
        /** En esta función se hace visible el modal para crear/actualizar informes */
        /** ----------------------------------------------------------------------- */

        setTimeout(() =>
        {
            this.editor.refresh();
            $('.CodeMirror-vscrollbar').addClass('listado');
        }, 500);
        $('#nuevoInforme').modal('show');
    }

    async guardarInforme(event)
    {
        /** En esta función se efectúa el registro/edición de un informe */
        /** ------------------------------------------------------------ */

        event.preventDefault();
        let timeMostrarError = 1;
        $('#nuevo_informe_sql').val(this.editor.getValue());
        let btnGuardarInforme = this.btnGuardarInformeTarget;
        if(this.lineaError > -1)
        {
            this.editor.removeLineClass(this.lineaError, 'background', 'lineaError');
            timeMostrarError = 1000;
            this.hideMensajeError();
            this.editor.refresh();
            this.lineaError = -1;
        }
        let form = new FormData(event.currentTarget);
        if($('#nuevo_informe_sql').val() === '')
        {
            setTimeout(() => { $('#divConfigurarSql').css('background', '#17A2B8');}, 3000);
            setTimeout(() => {$('#btnGuardarInforme').prop('disabled', false)}, 200);
            this.mensaje.mostrarMensaje('¡Configure un sql antes de continuar!');
            $('#divConfigurarSql').css('background', '#DC3545');
            return;
        }
        
        /** Se guarda/edita el informe */
        /** -------------------------- */

        btnGuardarInforme.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando';
        let consulta = await fetch(this.urlGuardarInformeValue, {'method' : 'POST', 'body' : form});
        let result = await consulta.json();
        btnGuardarInforme.innerHTML = '<i class="fas fa-save"></i> Guardar';

        /** Se valida si el registro fue exitoso */
        /** ------------------------------------ */

        if(result.status == 'success')
        {
            this.mensaje.mostrarMensaje('¡El informe se ha guardado con éxito!', 1);
            $('#frameCamposJson').html(result.camposJson);
        }
        else
        {   
            $('#msgError').text(result.message);
            let lineaSplit = result.message.split('LINE');
            setTimeout(() => {$('#errorValidarSql').css('display', 'flex')}, timeMostrarError);

            /** Se resalta la línea que contien el error */
            /** ---------------------------------------- */
            
            if(lineaSplit.length > 1)
            {
                let lineaError = Number(lineaSplit[1].split(':')[0].trim()) - 1;
                this.editor.addLineClass(lineaError, 'background', 'lineaError');
                this.lineaError = lineaError;
                this.editor.refresh();
            } 
        }
    }

    hideMensajeError()
    {
        /** En esta función se oculta el mensaje de error que se visualiza si el SQl es inválido */
        /** ------------------------------------------------------------------------------------ */

        $('#errorValidarSql').removeClass('animate__fadeInDown').addClass('animate__fadeOutUp');
        setTimeout(() => {$('#errorValidarSql').hide().removeClass('animate__fadeOutUp').addClass('animate__fadeInDown')}, 1000);
    }
}   