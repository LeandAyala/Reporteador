import mensajes from '../central/mensajes';
import { Controller } from "@hotwired/stimulus";

export default class extends Controller 
{
    mensaje = new mensajes();

    static values = 
    {
        'urlGuardarFactura' : String,
        'urlEliminarFactura' : String,
        'urlFrameNuevoFactura' : String,
        'urlActualizarListaFactura' : String
    };

    static targets = 
    [
        'formFiltros', 'cargandoFrameNuevoFactura', 'frameNuevoFactura', 'formularioPrincipal', 'frameListaFactura',
        'cargandoFiltros', 'totalRegistrosHidden'
    ];

    connect()
    {
        var self = this;
        console.log('connect');
        this.actualizarListaFactura(null, 1);
        $('.selectpicker').selectpicker('refresh');
        $('#btnRegresar').on('click', function(){$(this).html('<i class="fas fa-spinner fa-spin"></i> Regresando')});
    }

    formatearCampo(event)
    {
        /** En esta función se formatea el valor ingresado en los inputs que reciben valores numéricos */
        /** ------------------------------------------------------------------------------------------ */

        new Cleave(event.currentTarget, { numeral: true, numeralPositiveOnly: true, numeralDecimalScale: 2, numeralDecimalMark: ',', delimiter: '.' });
    }

    async showModalNuevoFactura(event)
    {
        /** En esta función se hace visible el modal de Nuevo/Editar registro cargando la información correspondiente */
        /** --------------------------------------------------------------------------------------------------------- */
        
        let self = this;
        event.preventDefault();
        let form = new FormData();
        let id = event.currentTarget.dataset.id;
        $('#modalNuevoFactura').modal('show');
        this.cargandoFrameNuevoFacturaTarget.style.display = '';

        if(id == 0)
        {
            $('#tituloModalNuevo').text('Nuevo Factura');
            $('#iconoModalNuevo').removeClass('fa-edit').addClass('fa-external-link-alt');
            $('#btnNuevoFactura').html('<i class="fas fa-spinner fa-spin"></i> Cargando');
        }
        else
        {
            $('#tituloModalNuevo').text('Editar Factura');
            $('#opc'+id).html('<i class="fas fa-spinner fa-spin text-primary"></i>');
            $('#iconoModalNuevo').removeClass('fa-external-link-alt').addClass('fa-edit');
            form.append('id', id);
        }

        /** Se carga el formulario para crear/editar registros */
        /** -------------------------------------------------- */

        let consulta = await fetch(this.urlFrameNuevoFacturaValue, {'method' : 'POST', 'body' : form});
        this.frameNuevoFacturaTarget.innerHTML = await consulta.text();
        if(id == 0)
        {
            $('#btnNuevoFactura').html('<i class="fas fa-external-link-alt"></i> Nuevo');
        }
        else
        {
            $('#opc'+id).html('<i class="fas fa-cog text-primary"></i>');
        }
        $('.camposNumericos').each(function(){if($(this).val() != ''){$(this).val(self.numberFormat($(this).val()))}});
        $('.selectpicker').selectpicker('refresh');
    }

    async guardarFactura(event)
    {
        /** En esta función se envía el formulario para crear/editar registros */
        /** ------------------------------------------------------------------ */

        event.preventDefault();
        $('#btnGuardarFactura').html('<i class="fas fa-spinner fa-spin"></i> Guardando').prop('disabled', true);
        $('.camposNumericos').each(function(){if($(this).val() != ''){$(this).val($(this).val().replaceAll('.','').replace(',','.'))}});

        /** Se envía el formulario para guardar/editar el registro */
        /** ------------------------------------------------------ */

        let form = new FormData(this.formularioPrincipalTarget);
        let consulta = await fetch(this.urlGuardarFacturaValue, {'method' : 'POST', 'body' : form});
        let result = await consulta.json();

        /** Se valida si el registro/edición fue exitoso */
        /** -------------------------------------------- */

        if(result.status == 'success')
        {
            this.mensaje.mostrarMensaje('¡El registro se ha guardado con éxito!', 1);
            $('#modalNuevoFactura').modal('hide');
            await this.actualizarListaFactura();
        }
        else
        {
            this.mensaje.mostrarMensaje(result.message, 2);
        }
        $('#btnGuardarFactura').html('<i class="fas fa-save"></i> Guardar').prop('disabled', false);
    }

    async actualizarListaFactura(event = null, opc = 0)
    {
        /** En esta función se actualiza la lista de registros de acuerdo a los filtros de búsqueda seleccionados */
        /** ----------------------------------------------------------------------------------------------------- */

        let form = new FormData(this.formFiltrosTarget);
        if(opc == 0){$('#cargandoFiltros').css('display', '');}
        let consulta = await fetch(this.urlActualizarListaFacturaValue, {'method' : 'POST', 'body' : form});
        this.frameListaFacturaTarget.innerHTML = await consulta.text();
        $('#cargandoFiltros').css('display', 'none');

        /** Se actualiza el total de registros */
        /** ---------------------------------- */

        $('#totalRegistros').removeClass('animate__flipInX').addClass('animate__flipOutX');
        let intervaloRegistros = setInterval(() =>
        {
            if(this.targets.find('totalRegistrosHidden') != undefined)
            {
                clearInterval(intervaloRegistros);
                $('#totalRegistros').css('display', (parseFloat($('#totalRegistrosHidden').val()) == 0)?'none':'').text(`Total registros: ${$('#totalRegistrosHidden').val()}`).removeClass('animate__flipOutX').addClass('animate__flipInX');
            }
        }, 1000);
    }

    async eliminarFactura(event)
    {
        /** En esta función se hace la eliminación de un registro */
        /** ----------------------------------------------------- */

        event.preventDefault();
        let form = new FormData();
        let id = event.currentTarget.dataset.id;
        form.append('id', event.currentTarget.dataset.id);
        $('#opc'+id).html('<i class="fas fa-spinner fa-spin text-danger"></i>');

        /** Se realiza la eliminación del registro */
        /** -------------------------------------- */

        let consulta = await fetch(this.urlEliminarFacturaValue, {'method' : 'POST', 'body' : form});
        let result = await consulta.json();

        /** Se valida si la eliminación fue exitosa */
        /** --------------------------------------- */

        if(result.status == 'success')
        {
            this.mensaje.mostrarMensaje('¡El registro se ha eliminado con éxito!', 1);
            await this.actualizarListaFactura();
        }
        else
        {
            this.mensaje.mostrarMensaje(result.message, 2);
            $('#opc'+id).html('<i class="fas fa-cog text-primary"></i>');
        }
    }

    showOpciones()
    {
        /** En esta función se ajusta el top de las opciones Editar/Eliminar de cada registro */
        /** --------------------------------------------------------------------------------- */

        $('.dropdown-menu').each(function()
        {
            setTimeout(() => 
            {
                if($(this).hasClass('show')){$('.dropdown-menu.show').css('top', '4px')}
            });
        });
    }

    numberFormat(valor) 
    {
        /** En esta función se formatea un valor numérico */
        /** --------------------------------------------- */

        valor = parseFloat(valor).toFixed(2).toString().replace('.', ',');
        while(true) 
        {
            let valorFormato = valor.replace(/(\d)(\d{3})($|,|\.)/g, '$1.$2$3');
            if(valor == valorFormato){break;}
            valor = valorFormato;
        }
        return valor;
    }
}