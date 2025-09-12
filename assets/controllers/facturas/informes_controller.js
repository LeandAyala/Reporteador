import mensajes from '../central/mensajes';
import { Controller } from "@hotwired/stimulus";

export default class extends Controller 
{
    mensaje = new mensajes();
    estadoSeccionFiltros = 1;
    estadoBusquedaRapida = 0;
    estadoPaginaSeleccionada = 0;

    static values = 
    {
        'urlGenerarInforme' : String,
        'urlDescargarInformePdf' : String,
        'urlGuardarFiltrosSesion' : String,
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
        let form = new FormData(event.currentTarget);
        this.cargandoFiltrosTarget.style.display = '';
        if(this.estadoSeccionFiltros == 1){this.showSeccionFiltros()}
        let paginaActual = (this.targets.find('paginaHidden') != undefined)?this.paginaHiddenTarget.value:1;
        let busquedaRapida = (this.targets.find('busquedaRapida') != undefined)?this.busquedaRapidaTarget.value.trim():'';
        let busquedaRapidaActual = (this.targets.find('busquedaRapidaHidden') != undefined)?this.busquedaRapidaHiddenTarget.value.trim():'';
        busquedaRapida = (this.estadoBusquedaRapida == 1)?busquedaRapida:busquedaRapidaActual;
        paginaActual = (this.estadoPaginaSeleccionada == 1)?paginaActual:1;
        form.append('busquedaRapida', busquedaRapida);
        form.append('pagina', paginaActual);
        this.estadoPaginaSeleccionada = 0;
        this.estadoBusquedaRapida = 0;

        /** Se genera el informe a partir de los filtros de búsqueda */
        /** -------------------------------------------------------- */
        
        let consulta = await fetch(this.urlGenerarInformeValue, {'method' : 'POST', 'body' : form});
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
        setTimeout(() => {$('#menuReporteador').hide(); btnMenuReporteador.css('pointer-events', '');}, 1100);
        btnMenuReporteador[0].dataset.opc = 0;

        /** Se guardan los filtros de búsqueda en variables de sesión */
        /** --------------------------------------------------------- */

        await fetch(this.urlGuardarFiltrosSesionValue, {'method' : 'POST', 'body' : form});

        /** Se genera y descarga el informe en formato PDF */
        /** ---------------------------------------------- */

        window.location.href = this.urlDescargarInformePdfValue;
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
        setTimeout(() => {$('#menuReporteador').hide(); btnMenuReporteador.css('pointer-events', '');}, 1100);
        btnMenuReporteador[0].dataset.opc = 0;

        /** Se guardan los filtros de búsqueda en variables de sesión */
        /** --------------------------------------------------------- */

        await fetch(this.urlGuardarFiltrosSesionValue, {'method' : 'POST', 'body' : form});

        /** Se genera y descarga el informe en formato excel */
        /** ------------------------------------------------ */

        window.location.href = this.urlDescargarInformeExcelValue;
    }
}