import mensajes from '../central/mensajes';
import { Controller } from "@hotwired/stimulus";

export default class extends Controller 
{
    form = new FormData();
    mensaje = new mensajes();
    estadoSeccionFiltros = 1;
    estadoBusquedaRapida = 0;
    estadoPaginaSeleccionada = 0;

    static values = 
    {
        'urlGenerarInforme' : String,
        'urlFrameErrorInforme' : String,
        'urlDescargarInformePdf' : String,
        'urlDescargarInformeExcel' : String,
    };

    static targets = 
    [
        'formFiltros', 'cargandoFrameNuevoFactura', 'frameNuevoFactura', 'formularioPrincipal', 'frameListaFactura',
        'cargandoFiltros', 'totalRegistrosHidden', 'paginaHidden', 'busquedaRapida', 'busquedaRapidaHidden'
    ];

    connect()
    {
        var self = this;
        console.log('connect');
        $('.selectpicker').selectpicker('refresh');
        $('#btnRegresar').on('click', function(){$(this).html('<i class="fas fa-spinner fa-spin"></i> Regresando')});
    }

    async generarInforme(event)
    {
        /** En esta función se genera un informe específico de acuerdo a los filtros de búsqueda seleccionados */
        /** -------------------------------------------------------------------------------------------------- */

        event.preventDefault();
        let form = new FormData();
        let formulario = Object.fromEntries(new FormData(event.currentTarget));
        Object.keys(formulario).forEach((item) => {form.append(item, formulario[item])});
        let rutaInforme = $('#filtros_informes_informe option:selected').data('rutaframe');
        let paginaActual = (this.targets.find('paginaHidden') != undefined)?this.paginaHiddenTarget.value:1;
        let busquedaRapida = (this.targets.find('busquedaRapida') != undefined)?this.busquedaRapidaTarget.value.trim():'';
        let busquedaRapidaActual = (this.targets.find('busquedaRapidaHidden') != undefined)?this.busquedaRapidaHiddenTarget.value.trim():'';
        busquedaRapida = (this.estadoBusquedaRapida == 1)?busquedaRapida:busquedaRapidaActual;
        paginaActual = (this.estadoPaginaSeleccionada == 1)?paginaActual:1;
        form.append('busquedaRapida', busquedaRapida);
        form.append('pagina', paginaActual);
        this.estadoPaginaSeleccionada = 0;
        this.estadoBusquedaRapida = 0;

        /** Se valida si existe una ruta configurada para la descarga del excel */
        /** ------------------------------------------------------------------- */

        if(rutaInforme == 'error')
        {
            this.mensaje.mostrarMensaje('¡La ruta configurada para generar el informe no es válida!');
            return;
        }

        /** Se genera el informe a partir de los filtros de búsqueda */
        /** -------------------------------------------------------- */
        
        this.form = form;
        let ruta = (rutaInforme !== '')?rutaInforme:this.urlGenerarInformeValue;
        this.cargandoFiltrosTarget.style.display = '';
        if(this.estadoSeccionFiltros == 1){this.showSeccionFiltros()}
        let consulta = await fetch(ruta, {'method' : 'POST', 'body' : form});
        let result = await consulta.json();
        $('#frameInforme').html(result.plantilla);
        this.cargandoFiltrosTarget.style.display = 'none';
        $('.listado').on('scroll', function()
        {
            if(this.scrollTop == 0)
            {
                $('.tituloFinal').css('border-radius', '0px 10px 3px 0px');
                $('.tituloInicial').css('border-radius', '10px 0px 0px 3px');
            }
            else
            {
                $('.tituloFinal').css('border-radius', '0px');
                $('.tituloInicial').css('border-radius', '0px');
            }
        });
    }

    async seleccionarPagina(event)
    {
        /** En esta función se realiza la búsqueda de registros de acuerdo a la página seleccionada */
        /** --------------------------------------------------------------------------------------- */

        let pagina = 1;
        this.estadoPaginaSeleccionada = 1;
        let opc = event.currentTarget.dataset.opc
        let paginaActual = Number($('#paginaHidden').val());
        let paginaSeleccionada = event.currentTarget.dataset.pagina;

        /** Se asigna la página seleccionada y se efectúa la búsqueda de registros */
        /** ---------------------------------------------------------------------- */

        if(opc == 3){pagina = Number(paginaActual) + 1}
        if(opc == 2){pagina = Number(paginaActual) - 1}
        if(opc == 1){pagina = Number(paginaSeleccionada)}
        if(paginaActual != paginaSeleccionada)
        {
            $('#paginaHidden').val(pagina);
            $('#btnGenerarInforme').click();
        }
    }

    showSeccionFiltros()
    {
        /** En esta función se hace visible/oculta la sección que contiene los filtros de búsqueda */
        /** -------------------------------------------------------------------------------------- */

        $('.seccionFiltros').toggle('400');
        this.estadoSeccionFiltros = (this.estadoSeccionFiltros == 1)?0:1;
    }

    busquedaRapida(event)
    {
        /** En esta función se realiza la búsqueda de registros teniendo en cuenta el campo Búsqueda rápida */
        /** ----------------------------------------------------------------------------------------------- */

        let opc = event.currentTarget.dataset.opc;
        if(opc == 1 || event.keyCode == 13)
        {
            if($('#busquedaRapidaHidden').val().trim() != '' || $('#busquedaRapida').val().trim() != '')
            { 
                this.estadoBusquedaRapida = 1;
                $('#btnGenerarInforme').click();
            }
            else
            {
                $('#busquedaRapida').css('border-color', '#DC3545');
                $('#btnBusquedaRapida').css('background', '#DC3545');
                setTimeout(() =>
                {
                    $('#busquedaRapida').css('border-color', '');
                    $('#btnBusquedaRapida').css('background', '#17A');
                }, 3000);
            }
        }
    }

    showMenuReporteador(event)
    {
        /** En esta función se hace visible/oculta el menú del reporteador */
        /** -------------------------------------------------------------- */
        
        let opc = event.currentTarget.dataset.opc;
        let icono = $('.menuReporteador').find('i');
        let btnMenuReporteador = event.currentTarget;

        if(opc == 0)
        {
            opc = 1;
            icono.removeClass('fa-bars').addClass('fa-times');
            btnMenuReporteador.classList.add('menuReporteadorError');
            $('#menuReporteador').attr('transition-style', 'in:custom:circle-swoop').css('display', 'flex');
        }
        else
        {
            opc = 0;
            btnMenuReporteador.style.pointerEvents = 'none';
            icono.removeClass('fa-times').addClass('fa-bars');
            btnMenuReporteador.classList.remove('menuReporteadorError');
            $('#menuReporteador').attr('transition-style', 'out:custom:circle-swoop');
            setTimeout(() => {$('#menuReporteador').hide(); btnMenuReporteador.style.pointerEvents = '';}, 1100);
        }
        btnMenuReporteador.dataset.opc = opc;
    }

    async descargarPDF()
    {
        /** En esta función se realiza la descarga del informe en formato PDF */
        /** ----------------------------------------------------------------- */

        let icono = $('.menuReporteador').find('i');
        let btnMenuReporteador = $('.menuReporteador');
        btnMenuReporteador.css('pointer-events', 'none');
        let form = new FormData($('#filtrosInforme')[0]);
        icono.removeClass('fa-times').addClass('fa-bars');
        btnMenuReporteador.removeClass('menuReporteadorError');
        form.append('busquedaRapida', $('#busquedaRapidaHidden').val().trim());
        $('#menuReporteador').attr('transition-style', 'out:custom:circle-swoop');
        let rutaPDF = $('#filtros_informes_informe option:selected').data('rutapdf');
        setTimeout(() => {$('#menuReporteador').hide(); btnMenuReporteador.css('pointer-events', '');}, 1100);
        let nombreInforme = $('#filtros_informes_informe option:selected').text().toLowerCase().replaceAll(' ', '_');
        btnMenuReporteador[0].dataset.opc = 0;

        /** Se valida si existe una ruta configurada para la descarga del PDF */
        /** ----------------------------------------------------------------- */

        if(rutaPDF == 'error')
        {
            this.mensaje.mostrarMensaje('¡La ruta configurada para la descarga del PDF no es válida!');
            return;
        }

        /** Se hace visible el loader de descarga */
        /** ------------------------------------- */

        let porcentajeDescarga = 0;
        let iconoDescarga = $('#divIconoDescarga').find('i');
        $('#divIconoDescarga').css('background', '#DC354526');
        $('#loaderDescargaInforme').css({'opacity' : '1', 'right' : '0px'});
        iconoDescarga.removeClass('fa-file-excel text-success').addClass('fa-file-pdf text-danger');
        let intervaloLoaderDescarga = setInterval(() =>
        {
            $('#porcentajeDescarga').html(`${porcentajeDescarga}%`);
            $('#barraProgresoDescarga').css('width', `${porcentajeDescarga}%`);
            if(porcentajeDescarga == 99){clearInterval(intervaloLoaderDescarga)}
            porcentajeDescarga ++;
        }, 500);

        /** Se genera el informe en formato PDF */
        /** ----------------------------------- */

        let ruta = (rutaPDF != '')?rutaPDF:this.urlDescargarInformePdfValue;
        let consulta = await fetch(ruta, {'method' : 'POST', 'body' : this.form});
        clearInterval(intervaloLoaderDescarga);

        /** Se valida si el archivo PDF se generó con éxito */
        /** ----------------------------------------------- */

        if(!consulta.ok) 
        {
            $('#barraProgresoDescarga').addClass('bg-danger');
            $('#porcentajeDescarga').html('<i class="fas fa-ban text-danger animate__animated animate__fadeIn"></i>');
            consulta = await fetch(this.urlFrameErrorInformeValue);
            $('#frameErrorInforme').html(await consulta.text()).css('display', '');
            setTimeout(() =>{$('#loaderDescargaInforme').css({'opacity' : '0', 'right' : '-260px'})}, 3000);
            setTimeout(() =>{$('#barraProgresoDescarga').removeClass('bg-danger').css('width', '0%');}, 4000);
            return;
        }

        /** Se finaliza el porcentaje de descarga */
        /** ------------------------------------- */

        intervaloLoaderDescarga = setInterval(() =>
        {
            $('#porcentajeDescarga').html(`${porcentajeDescarga}%`);
            $('#barraProgresoDescarga').css('width', `${porcentajeDescarga}%`);
            if(porcentajeDescarga == 100)
            {
                clearInterval(intervaloLoaderDescarga);
                $('#barraProgresoDescarga').addClass('bg-success');
                $('#porcentajeDescarga').html('<i class="fas fa-check-circle text-success animate__animated animate__fadeIn"></i>');        
            }
            porcentajeDescarga ++;
        }, 1);

        /** Se realiza la descarga del archivo PDF */
        /** -------------------------------------- */

        let blob = await consulta.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.download = `${nombreInforme}.pdf`;
        a.href = url;
        document.body.appendChild(a);
        a.click();
        a.remove();

        /** Se oculta el loader de descarga */
        /** ------------------------------- */

        setTimeout(() =>{$('#loaderDescargaInforme').css({'opacity' : '0', 'right' : '-260px'})}, 3000);
        setTimeout(() =>{$('#barraProgresoDescarga').removeClass('bg-success').css('width', '0%');}, 4000);
    }

    async descargarExcel()
    {
        /** En esta función se realiza la descarga del informe en formato excel */
        /** ------------------------------------------------------------------- */

        let icono = $('.menuReporteador').find('i');
        let btnMenuReporteador = $('.menuReporteador');
        btnMenuReporteador.css('pointer-events', 'none');
        let form = new FormData($('#filtrosInforme')[0]);
        icono.removeClass('fa-times').addClass('fa-bars');
        btnMenuReporteador.removeClass('menuReporteadorError');
        form.append('busquedaRapida', $('#busquedaRapidaHidden').val().trim());
        $('#menuReporteador').attr('transition-style', 'out:custom:circle-swoop');
        let rutaExcel = $('#filtros_informes_informe option:selected').data('rutaexcel');
        setTimeout(() => {$('#menuReporteador').hide(); btnMenuReporteador.css('pointer-events', '');}, 1100);
        let nombreInforme = $('#filtros_informes_informe option:selected').text().toLowerCase().replaceAll(' ', '_');
        btnMenuReporteador[0].dataset.opc = 0;

        /** Se valida si existe una ruta configurada para la descarga del excel */
        /** ------------------------------------------------------------------- */

        if(rutaExcel == 'error')
        {
            this.mensaje.mostrarMensaje('¡La ruta configurada para la descarga del excel no es válida!');
            return;
        }

        /** Se hace visible el loader de descarga */
        /** ------------------------------------- */

        let porcentajeDescarga = 0;
        let iconoDescarga = $('#divIconoDescarga').find('i');
        $('#divIconoDescarga').css('background', '#28A74526');
        $('#loaderDescargaInforme').css({'opacity' : '1', 'right' : '0px'});
        iconoDescarga.removeClass('fa-file-pdf text-danger').addClass('fa-file-excel text-success');
        let intervaloLoaderDescarga = setInterval(() =>
        {
            $('#porcentajeDescarga').html(`${porcentajeDescarga}%`);
            $('#barraProgresoDescarga').css('width', `${porcentajeDescarga}%`);
            if(porcentajeDescarga == 99){clearInterval(intervaloLoaderDescarga)}
            porcentajeDescarga ++;
        }, 500);

        /** Se genera el informe en formato excel */
        /** ------------------------------------- */

        let ruta = (rutaExcel != '')?rutaExcel:this.urlDescargarInformeExcelValue;
        let consulta = await fetch(ruta, {'method' : 'POST', 'body' : this.form});
        clearInterval(intervaloLoaderDescarga);

        /** Se valida si el archivo excel se generó con éxito */
        /** ------------------------------------------------- */

        if(!consulta.ok) 
        {
            $('#barraProgresoDescarga').addClass('bg-danger');
            $('#porcentajeDescarga').html('<i class="fas fa-ban text-danger animate__animated animate__fadeIn"></i>');
            consulta = await fetch(this.urlFrameErrorInformeValue);
            $('#frameErrorInforme').html(await consulta.text()).css('display', '');
            setTimeout(() =>{$('#loaderDescargaInforme').css({'opacity' : '0', 'right' : '-260px'})}, 3000);
            setTimeout(() =>{$('#barraProgresoDescarga').removeClass('bg-danger').css('width', '0%');}, 4000);
            return;
        }

        /** Se finaliza el porcentaje de descarga */
        /** ------------------------------------- */

        intervaloLoaderDescarga = setInterval(() =>
        {
            $('#porcentajeDescarga').html(`${porcentajeDescarga}%`);
            $('#barraProgresoDescarga').css('width', `${porcentajeDescarga}%`);
            if(porcentajeDescarga == 100)
            {
                clearInterval(intervaloLoaderDescarga);
                $('#barraProgresoDescarga').addClass('bg-success');
                $('#porcentajeDescarga').html('<i class="fas fa-check-circle text-success animate__animated animate__fadeIn"></i>');
            }
            porcentajeDescarga ++;
        }, 1);

        /** Se realiza la descarga del archivo excel */
        /** ---------------------------------------- */

        let blob = await consulta.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.download = `${nombreInforme}.xls`;
        a.href = url;
        document.body.appendChild(a);
        a.click();
        a.remove();

        /** Se oculta el loader de descarga */
        /** ------------------------------- */

        setTimeout(() =>{$('#loaderDescargaInforme').css({'opacity' : '0', 'right' : '-260px'})}, 3000);
        setTimeout(() =>{$('#barraProgresoDescarga').removeClass('bg-success').css('width', '0%');}, 4000);
    }

    cerrarErrorInforme()
    {
        /** En esta función se cierra el mensaje de error generado al descargar el informe */
        /** ------------------------------------------------------------------------------ */

        $('#frameErrorInforme').addClass('animate__animated animate__fadeOut');
        setTimeout(() => {$('#frameErrorInforme').html('').hide().removeClass('animate__animated animate__fadeOut')}, 800);
    }
}