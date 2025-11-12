import mensajes from '../central/mensajes';
import { Controller } from "@hotwired/stimulus";

export default class extends Controller 
{
    indexCabecera = -1;
    estadoDescarga = 0;
    graficaPastel = null;
    graficaBarras = null;
    configuraciones = '';
    form = new FormData();
    mensaje = new mensajes();
    estadoSeccionFiltros = 1;
    estadoBusquedaRapida = 0;
    estadoPaginaSeleccionada = 0;
    camposBusquedaAgrupacion = [];
    configuracionesGuardadas = {};
    aplicarConfiguraciones = false;
    colores = 
    [
        "#FF9F40", "#36A2EB", "#FFCE56", "#4BC0C0", "#9966FF", "#FF6384", "#C9CBCF", "#8DD17E", "#F77EB9", "#F49FBC", 
        "#6FB1FC", "#FFB6C1", "#D4A5A5", "#A7C7E7", "#F5B7B1", "#82E0AA", "#F7DC6F", "#85C1E9", "#BB8FCE", "#F0B27A", 
        "#73C6B6", "#AAB7B8", "#E59866", "#AF7AC5", "#48C9B0", "#F5CBA7", "#85929E", "#D7BDE2", "#AED6F1", "#A9DFBF",
        "#FAD7A0", "#F1948A", "#E6B0AA", "#ABB2B9", "#D5DBDB", "#F0F3F4", "#FDEBD0", "#D6EAF8", "#D1F2EB", "#FCF3CF", 
        "#FADBD8", "#E8DAEF", "#A3E4D7", "#AED6F1", "#F5B041", "#DC7633", "#BA4A00", "#117864", "#1F618D", "#6C3483", 
        "#922B21", "#D98880", "#F4D03F", "#7DCEA0", "#5DADE2", "#AF7AC5", "#85929E", "#45B39D", "#E59866", "#BFC9CA",
        "#EDBB99", "#C39BD3", "#73C6B6", "#7FB3D5", "#82E0AA", "#F8C471", "#E74C3C", "#8E44AD", "#2980B9", "#27AE60", 
        "#F39C12", "#D35400", "#2ECC71", "#3498DB", "#9B59B6", "#1ABC9C", "#E67E22", "#E74C3C", "#F1C40F", "#16A085", 
        "#2980B9", "#8E44AD", "#2C3E50", "#95A5A6", "#D35400", "#7F8C8D", "#BDC3C7", "#C0392B", "#E67E22", "#F39C12",
        "#1ABC9C", "#2ECC71", "#3498DB", "#9B59B6", "#34495E", "#16A085", "#27AE60", "#2980B9", "#8E44AD", "#2C3E50"
    ];

    static values = 
    {
        'urlGenerarInforme' : String,
        'urlFrameErrorInforme' : String,
        'urlDescargarInformePdf' : String,
        'urlDescargarInformeExcel' : String,
    };

    static targets = 
    [
        'cargandoFiltros', 'paginaHidden', 'busquedaRapida', 'busquedaRapidaHidden', 'totalRegistrosHidden', 'statusHidden'
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
        let existenConfiguracionesGuardadas = (this.configuraciones != '')?true:false;
        Object.keys(formulario).forEach((item) => {form.append(item, formulario[item])});
        let rutaInforme = $('#filtros_reporteador_informe option:selected').data('rutaframeinforme');
        let rutaResumen = $('#filtros_reporteador_informe option:selected').data('rutaframeresumen');
        let paginaActual = (this.targets.find('paginaHidden') != undefined)?this.paginaHiddenTarget.value:1;
        let busquedaRapida = (this.targets.find('busquedaRapida') != undefined)?this.busquedaRapidaTarget.value.trim():'';
        let busquedaRapidaActual = (this.targets.find('busquedaRapidaHidden') != undefined)?this.busquedaRapidaHiddenTarget.value.trim():'';
        busquedaRapida = (this.estadoBusquedaRapida == 1)?busquedaRapida:busquedaRapidaActual;
        form.append('busquedaAgrupacion', JSON.stringify(this.camposBusquedaAgrupacion));
        paginaActual = (this.estadoPaginaSeleccionada == 1)?paginaActual:1;
        form.append('busquedaRapida', busquedaRapida);
        form.append('pagina', paginaActual);
        this.estadoPaginaSeleccionada = 0;
        this.estadoBusquedaRapida = 0;

        /** Se valida si hay una descarga en proceso */
        /** ---------------------------------------- */

        if(this.estadoDescarga == 1)
        {
            this.mensaje.mostrarMensaje('¡No se puede realizar esta acción porque hay una descarga en proceso!');
            return;
        }

        /** Se valida si existe una ruta configurada para la descarga del excel */
        /** ------------------------------------------------------------------- */

        if(rutaInforme == 'error')
        {
            this.mensaje.mostrarMensaje('¡La ruta configurada para generar el informe no es válida!');
            return;
        }

        /** Se valida si existen configuraciones guardadas */
        /** ---------------------------------------------- */

        if(existenConfiguracionesGuardadas)
        {
            form.append('configuracionesGuardadas', JSON.stringify(this.configuracionesGuardadas));
        }

        /** Se genera el informe a partir de los filtros de búsqueda */
        /** -------------------------------------------------------- */
        
        this.form = form;
        this.cargandoFiltrosTarget.style.display = '';
        if(this.estadoSeccionFiltros == 1){this.showSeccionFiltros()}
        let ruta = (rutaInforme !== '')?rutaInforme:this.urlGenerarInformeValue;
        let consulta = await fetch(ruta, {'method' : 'POST', 'body' : form});
        let result = await consulta.json();
        $('#divResumen').css('display', 'none');
        $('#frameInforme').html(result.plantilla);
        $('#separadorResumen').css('display', 'none');
        this.cargandoFiltrosTarget.style.display = 'none';
        $('#frameResumenAgrupacion').html(result.seccionResumen);
        if(this.configuraciones == '')
        {
            $('#frameConfiguraciones').html(result.seccionConfiguraciones);
            let intervaloTotalRegistros = setInterval(() =>
            {
                if(this.targets.find('totalRegistrosHidden') != undefined)
                {
                    clearInterval(intervaloTotalRegistros);
                    if(parseFloat($('#totalRegistrosHidden').val()) == 0)
                    {
                        $('#opcionConfiguraciones').css({'pointer-events' : 'none', 'opacity' : '0.6'});
                    }
                }
            }, 100);
        }

        /** Se obtiene la sección de resumen */
        /** -------------------------------- */
        
        let intervaloStatus = setInterval(async () =>
        {
            if(this.targets.find('statusHidden') != undefined)
            {
                clearInterval(intervaloStatus);
                if($('#statusHidden').val() == 'success')
                {
                    if(rutaResumen != '' && rutaResumen != 'error')
                    {
                        $('#divResumen').css('display', '');
                        $('#cargandoResumen').css('display', '');
                        $('#separadorResumen').css('display', '');
                        consulta = await fetch(rutaResumen, {'method' : 'POST', 'body' : form});
                        result = await consulta.json();
                        $('#frameResumen').html(result.plantilla);
                        $('#cargandoResumen').css('display', 'none');
                    }
                }
            }
        }, 100);

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
        this.form.delete('configuracionesGuardadas');
        let btnMenuReporteador = $('.menuReporteador');
        btnMenuReporteador.css('pointer-events', 'none');
        let form = new FormData($('#filtrosInforme')[0]);
        icono.removeClass('fa-times').addClass('fa-bars');
        btnMenuReporteador.removeClass('menuReporteadorError');
        form.append('busquedaRapida', $('#busquedaRapidaHidden').val().trim());
        $('#menuReporteador').attr('transition-style', 'out:custom:circle-swoop');
        let rutaPDF = $('#filtros_reporteador_informe option:selected').data('rutapdf');
        setTimeout(() => {$('#menuReporteador').hide(); btnMenuReporteador.css('pointer-events', '');}, 1100);
        let nombreInforme = $('#filtros_reporteador_informe option:selected').text().toLowerCase().replaceAll(' ', '_');
        btnMenuReporteador[0].dataset.opc = 0;

        /** Se valida si existe una ruta configurada para la descarga del PDF */
        /** ----------------------------------------------------------------- */

        if(rutaPDF == 'error')
        {
            this.mensaje.mostrarMensaje('¡La ruta configurada para la descarga del PDF no es válida!');
            return;
        }

        /** Se valida si existen configuraciones guardadas */
        /** ---------------------------------------------- */

        if(this.aplicarConfiguraciones)
        {
            this.form.append('configuracionesGuardadas', JSON.stringify(this.configuracionesGuardadas));
        }

        /** Se hace visible el loader de descarga */
        /** ------------------------------------- */

        this.estadoDescarga = 1;
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
            this.estadoDescarga = 0;
            $('#barraProgresoDescarga').addClass('bg-danger');
            $('#porcentajeDescarga').html('<i class="fas fa-ban text-danger animate__animated animate__fadeIn"></i>');
            consulta = await fetch(this.urlFrameErrorInformeValue);
            $('#frameInformacion').html(await consulta.text()).css('display', '');
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

        this.estadoDescarga = 0;
        setTimeout(() =>{$('#loaderDescargaInforme').css({'opacity' : '0', 'right' : '-260px'})}, 3000);
        setTimeout(() =>{$('#barraProgresoDescarga').removeClass('bg-success').css('width', '0%');}, 4000);
    }

    async descargarExcel()
    {
        /** En esta función se realiza la descarga del informe en formato excel */
        /** ------------------------------------------------------------------- */

        let icono = $('.menuReporteador').find('i');
        this.form.delete('configuracionesGuardadas');
        let btnMenuReporteador = $('.menuReporteador');
        btnMenuReporteador.css('pointer-events', 'none');
        let form = new FormData($('#filtrosInforme')[0]);
        icono.removeClass('fa-times').addClass('fa-bars');
        btnMenuReporteador.removeClass('menuReporteadorError');
        form.append('busquedaRapida', $('#busquedaRapidaHidden').val().trim());
        $('#menuReporteador').attr('transition-style', 'out:custom:circle-swoop');
        let rutaExcel = $('#filtros_reporteador_informe option:selected').data('rutaexcel');
        setTimeout(() => {$('#menuReporteador').hide(); btnMenuReporteador.css('pointer-events', '');}, 1100);
        let nombreInforme = $('#filtros_reporteador_informe option:selected').text().toLowerCase().replaceAll(' ', '_');
        btnMenuReporteador[0].dataset.opc = 0;

        /** Se valida si existe una ruta configurada para la descarga del excel */
        /** ------------------------------------------------------------------- */

        if(rutaExcel == 'error')
        {
            this.mensaje.mostrarMensaje('¡La ruta configurada para la descarga del excel no es válida!');
            return;
        }

        /** Se valida si existen configuraciones guardadas */
        /** ---------------------------------------------- */

        if(this.aplicarConfiguraciones)
        {
            this.form.append('configuracionesGuardadas', JSON.stringify(this.configuracionesGuardadas));
        }

        /** Se hace visible el loader de descarga */
        /** ------------------------------------- */

        this.estadoDescarga = 1;
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
            this.estadoDescarga = 0;
            $('#barraProgresoDescarga').addClass('bg-danger');
            $('#porcentajeDescarga').html('<i class="fas fa-ban text-danger animate__animated animate__fadeIn"></i>');
            consulta = await fetch(this.urlFrameErrorInformeValue);
            $('#frameInformacion').html(await consulta.text()).css('display', '');
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

        this.estadoDescarga = 0;
        setTimeout(() =>{$('#loaderDescargaInforme').css({'opacity' : '0', 'right' : '-260px'})}, 3000);
        setTimeout(() =>{$('#barraProgresoDescarga').removeClass('bg-success').css('width', '0%');}, 4000);
    }

    cerrarErrorInforme(event)
    {
        /** En esta función se cierra el mensaje de error generado al descargar el informe */
        /** ------------------------------------------------------------------------------ */

        $('#frameInformacion').addClass('animate__animated animate__fadeOut');
        setTimeout(() => 
        {
            $('#frameInformacion').html('');
            $('#frameInformacion').hide().removeClass('animate__animated animate__fadeOut');
        }, 800);
    }

    seleccionarInforme()
    {
        /** En esta función se limpia el valor de la búsqueda dinámica cuando se realiza la selección de un informe */
        /** ------------------------------------------------------------------------------------------------------- */

        this.configuraciones = '';
        this.camposBusquedaAgrupacion = [];
        $('#busquedaRapidaHidden').val('');
    }

    limpiarCampos()
    {
        /** En esta función se limpian los filtros de búsqueda */
        /** -------------------------------------------------- */

        $('#filtrosInforme')[0].reset();
        $('.selectpicker').selectpicker('refresh');
    }

    showConfiguraciones()
    {
        /** En esta función se hace visible la sección de configuraciones */
        /** ------------------------------------------------------------- */
        
        $('body').css('overflow', 'hidden');
        let icono = $('.menuReporteador').find('i');
        $('#frameConfiguraciones').css('display', '');
        let btnMenuReporteador = $('.menuReporteador');
        btnMenuReporteador.css('pointer-events', 'none');
        icono.removeClass('fa-times').addClass('fa-bars');
        btnMenuReporteador.removeClass('menuReporteadorError');
        $('#menuReporteador').attr('transition-style', 'out:custom:circle-swoop');
        if(this.configuraciones != ''){$('#frameConfiguraciones').html(this.configuraciones)}
        setTimeout(() => {$('#menuReporteador').hide(); btnMenuReporteador.css('pointer-events', '');}, 1100);
        btnMenuReporteador[0].dataset.opc = 0;
    }

    seleccionarConfiguracion(event)
    {
        /** En esta función se actualizan los estilos de la opción seleccionada en las configuraciones */
        /** ------------------------------------------------------------------------------------------ */

        let opc = event.currentTarget.dataset.opc;
        let icono = event.currentTarget.querySelector('i');
        let texto = event.currentTarget.querySelector('span');
        $('.btnConfiguraciones').each(function()
        {
            $(this).css('background', 'white');
            $(this).find('i').css('color', 'gray');
            $(this).find('span').css('color', 'gray');
        });
        event.currentTarget.style.background = '#e9ecef';
        icono.style.color = '#17A';
        texto.style.color = '#17A';
    }

    seleccionarCampoAgrupacion(event)
    {
        /** En esta función se activan/desactivan opciones de agrupación y se habilitan en la sección de campos */
        /** --------------------------------------------------------------------------------------------------- */

        let estado = event.currentTarget.checked;
        let campo = event.currentTarget.dataset.campo;
        if(estado)
        {
            $('#check_'+campo).removeAttr('checked');
            $('#div_'+campo).css({'pointer-events' : 'none', 'opacity' : '0.6'});
        }
        else
        {
            $('#div_'+campo).css({'pointer-events' : '', 'opacity' : '1'});
        }
    }

    guardarConfiguraciones()
    {
        /** En esta función se guardan las configuraciones del informe */
        /** ---------------------------------------------------------- */

        let camposConfiguracion = [];
        let totalColspanCabeceras = 0;
        let camposBusquedaDinamica = [];
        let cabecerasConfiguracion = [];
        let countCamposSeleccionados = 0;
        let existenFechasInvalidas = false;
        let camposAgrupacionConfiguracion = [];

        /** Se valida si existe al menos un campo seleccionado */
        /** -------------------------------------------------- */

        let self = this;
        $('.camposConfiguracion').each(function(){if($(this).is(':checked')){countCamposSeleccionados ++}});
        $('.camposBusquedaDinamica').each(function()
        {
            let campo = $(this).data('campo');
            let tipoDato = $(this).data('tipo');
            let tipoBusqueda = $('#select_'+campo).val();
            if($(this).is(':checked'))
            {
                /** Se valida para los campos fecha si el rango seleccionado es válido */
                /** ------------------------------------------------------------------ */

                if(tipoDato === 'fecha' && tipoBusqueda === 'entre')
                {
                    if(($('#input_'+campo).val() > $('#input_hasta_'+campo).val()) || $('#input_'+campo).val() == '' || $('#input_hasta_'+campo).val() == '')
                    {
                        existenFechasInvalidas = true;
                        $('#input_'+campo).addClass('is-invalid');
                        $('#input_hasta_'+campo).addClass('is-invalid');
                        setTimeout(() =>
                        {
                            $('#input_'+campo).removeClass('is-invalid');
                            $('#input_hasta_'+campo).removeClass('is-invalid');
                        }, 2000);
                    }
                }

            }
        });

        /** Se obtienen las cabeceras configuradas */
        /** -------------------------------------- */

        $('.cabeceraConfiguracion').each(function()
        {
            let opc = $(this).data('opc');
            let nombre = $('#input_nombre_'+opc).val();
            let colspan = $('#input_campos_'+opc).val();
            if($(this).is(':checked'))
            {
                let dataCabecera =
                {
                    'index' : opc,
                    'nombre' : nombre,
                    'colspan' : colspan
                };
                cabecerasConfiguracion.push(dataCabecera);
                totalColspanCabeceras += parseFloat(colspan);
            }
        });
        if(existenFechasInvalidas)
        {
            self.mensaje.mostrarMensaje('¡Seleccione un rango de fechas válido antes de continuar!', 2);
            return;
        }
        if(cabecerasConfiguracion.length > 0 && (totalColspanCabeceras != countCamposSeleccionados))
        {
            self.mensaje.mostrarMensaje('¡El colspan de las cabeceras debe ser igual a la cantidad de campos habilitados para el informe!', 2);
            return;
        }
        if(countCamposSeleccionados > 0)
        {
            /** Se obtienen los campos configurados */
            /** ----------------------------------- */

            $('.camposConfiguracion').each(function()
            {
                if($(this).is(':checked'))
                {
                    camposConfiguracion.push($(this).data('campo'));
                }
            });

            /** Se obtienen los campos de agrupación configurados */
            /** ------------------------------------------------- */

            $('.camposAgrupacionConfiguracion').each(function()
            {
                if($(this).is(':checked'))
                {
                    camposAgrupacionConfiguracion.push($(this).data('campo'));
                }
            });

            /** Se obtienen los campos de búsqueda dinámica configurados */
            /** -------------------------------------------------------- */

            $('.camposBusquedaDinamica').each(function()
            {
                if($(this).is(':checked'))
                {
                    let valorBusqueda = 
                        ($(this).data('tipo') != 'numero')
                        ?$('#input_'+$(this).data('campo')).val()
                        :$('#input_'+$(this).data('campo')).val().replaceAll('.','').replace(',','.')
                    ;
                    let valorFechaHasta = ($(this).data('tipo') != 'fecha')?'':$('#input_hasta_'+$(this).data('campo')).val();
                    let dataBusquedaDinamica =
                    {
                        'input' : valorBusqueda,
                        'hasta' : valorFechaHasta,
                        'tipo' : $(this).data('tipo'),
                        'campo' : $(this).data('campo'),
                        'select' : $('#select_'+$(this).data('campo')).val()
                    }
                    camposBusquedaDinamica.push(dataBusquedaDinamica);
                }
            });
            $('#busquedaRapida').val('');
            $('#busquedaRapidaHidden').val('');
            this.camposBusquedaAgrupacion = [];
            this.configuraciones = $('#frameConfiguraciones').html();
            this.mensaje.mostrarMensaje('¡Las configuraciones se han guardado con éxito!', 1);
            this.aplicarConfiguraciones = $('#aplicarConfiguracionesDescarga').is(':checked');
            this.configuracionesGuardadas = {'campos' : camposConfiguracion, 'agrupacion' : camposAgrupacionConfiguracion, 'busquedaDinamica' : camposBusquedaDinamica, 'cabeceras' : cabecerasConfiguracion};
            $('#btnGenerarInforme').click();
        }
        else
        {
            this.mensaje.mostrarMensaje('¡Seleccione al menos un campo antes de continuar!', 2);
        }
    }

    seleccionarCampoConfiguracion(event)
    {
        /** En esta función se habilita o deshabilita un campo en la sección de configuraciones */
        /** ----------------------------------------------------------------------------------- */

        let id = event.currentTarget.id;
        let estado = event.currentTarget.checked;
        if(estado)
        {
            $('#'+id).attr('checked', true);
        }
        else
        {
            $('#'+id).removeAttr('checked');
        }
    }

    seleccionarCampoBusquedaDinamica(event)
    {
        /** En esta función se habilita o deshabilita un campo de búsqueda dinámica */
        /** ----------------------------------------------------------------------- */

        let estado = event.currentTarget.checked;
        let campo = event.currentTarget.dataset.campo;
        if(estado)
        {
            $('#select_'+campo).removeAttr('disabled');
            $('#input_hasta_'+campo).removeAttr('disabled');
            $('#input_'+campo).removeAttr('disabled').select();
        }
        else
        {
            $('#input_'+campo).attr('disabled', true);
            $('#select_'+campo).attr('disabled', true);
            $('#input_hasta_'+campo).attr('disabled', true);
        }
    }

    formatearCampo(event)
    {
        /** En esta función se formatea el valor ingresado en los inputs */
        /** ------------------------------------------------------------ */

        new Cleave(event.currentTarget, { numeral: true, numeralPositiveOnly: true, numeralDecimalScale: 2, numeralDecimalMark: ',', delimiter: '.' });
    }

    seleccionarTipoBusqueda(event)
    {
        /** En esta función se hace visible un campo de fecha (Representa el rango final de una búsqueda), si la opción seleccionada equivale a "entre"; de lo contrario se oculta este campo */
        /** --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */

        let id = event.currentTarget.id;
        let tipo = event.currentTarget.value;
        let campo = event.currentTarget.dataset.campo;
        $('#'+id+' option:selected').attr('selected', true);
        let icono = $('#div_texto_busqueda_dinamica_'+campo).find('i');
        let texto = $('#div_texto_busqueda_dinamica_'+campo).find('.montserrat');
        if(tipo === 'entre')
        {
            texto.css('opacity', '0');
            setTimeout(() => {icono.css('opacity', '1').css('display', '')}, 400);
            setTimeout(() => 
            {
                texto.css('display', 'none');
                $('#div_select_busqueda_dinamica_'+campo).css('width', '170px');
                $('#div_input_busqueda_dinamica_'+campo).css('border-radius', '0px');
                $('#div_texto_busqueda_dinamica_'+campo).css('width', '38px').addClass('msg');
                $('#div_hasta_'+campo).addClass('animate__animated animate__fadeIn').css('display', 'flex');
            }, 300);
        }
        else
        {
            icono.css('opacity', '0');
            setTimeout(() => {texto.css('opacity', '1').css('display', '')}, 400);
            setTimeout(() => 
            {
                icono.css('display', 'none');
                $('#div_select_busqueda_dinamica_'+campo).css('width', '186px');
                $('#div_input_busqueda_dinamica_'+campo).css('border-radius', '0px 5px 5px 0px');
                $('#div_texto_busqueda_dinamica_'+campo).css('width', '207px').removeClass('msg');
                $('#div_hasta_'+campo).addClass('animate__animated animate__fadeIn').css('display', 'none');
            }, 300);
        }
    }

    cerrarConfiguraciones()
    {
        /** En esta función se cierra la sección de configuraciones */
        /** ------------------------------------------------------- */

        $('body').css('overflow', '');
        $('#frameConfiguraciones').addClass('animate__animated animate__fadeOut');
        setTimeout(() => {$('#frameConfiguraciones').hide().removeClass('animate__animated animate__fadeOut')}, 800);
    }

    ingresarBusqueda(event)
    {
        /** En esta función se asigna en el value del input el valor ingresado */
        /** ------------------------------------------------------------------ */

        let id = event.currentTarget.id;
        $('#'+id).attr('value', event.currentTarget.value);
    }

    seleccionarCabecera(event)
    {
        /** En esta función se habilita o deshabilita una cabecera */
        /** ------------------------------------------------------ */

        let estado = event.currentTarget.checked;
        let opc = event.currentTarget.dataset.opc;
        if(estado)
        {
            $('#input_nombre_'+opc).removeAttr('disabled');
            $('#input_campos_'+opc).removeAttr('disabled');
        }
        else
        {
            $('#input_nombre_'+opc).attr('disabled', true);
            $('#input_campos_'+opc).attr('disabled', true);
        }
    }

    agregarCabecera()
    {
        /** En esta función se agrega una nueva cabecera al informe */
        /** ------------------------------------------------------- */

        let self = this;
        let counCheckActivos = 0;
        if(this.indexCabecera == -1)
        {
            $('.cabeceraConfiguracion').each(function()
            {
                if($(this).is(':checked')){counCheckActivos ++}
                self.indexCabecera = counCheckActivos;
            });
        }

        /** Se agrega la nueva cabecera */
        /** --------------------------- */

        $('#divCabeceras').append(
        `
            <div class="animate__animated animate__fadeIn" id="div_cabecera_${this.indexCabecera}" style="display:flex; align-items:center; width:100%;">
            <div style=
            "
                gap:5px; 
                height:40px;
                display:flex;
                padding:7px 12px; 
                background:white;
                align-items:center;
                background:#e9ecef; 
                border:1px solid #d0d4da; 
                border-radius:5px 0px 0px 5px; 
            ">
                <input class="cabeceraConfiguracion" type="checkbox" id="check_${this.indexCabecera}" data-opc="${this.indexCabecera}" data-action="central--reporteador#seleccionarCampoConfiguracion central--reporteador#seleccionarCabecera">
            </div>
            <div style=
            "
                flex:1;
                gap:5px;
                padding:1px; 
                height:40px;
                display:flex;
                background:white;
                align-items:center; 
                border:1px solid #d0d4da; 
                padding-left:12px;
                border-left:none;
                border-right:none;
            ">
                <div style="display:flex;align-items:center; gap:5px; width:85px">
                    <span class="montserrat" style="font-size:10px">Nombre</span>
                    <i class="fas fa-angle-double-right text-info" style="font-size:9px"></i>
                </div>
                <input
                    disabled 
                    type="text" 
                    class="form-control" 
                    placeholder="Nombre"
                    style="font-size:11px; height:28px" 
                    id="input_nombre_${this.indexCabecera}" 
                    data-action="central--reporteador#ingresarBusqueda"
                >
            </div>
            <div style=
            "
                gap:5px; 
                padding:1px; 
                height:40px;
                width:200px;
                display:flex;
                background:white;
                padding-left:12px;
                align-items:center;
                border:1px solid #d0d4da;
                border-right:none;
                border-left:none;
            ">
                <div style="display:flex;align-items:center; gap:5px; width:110px">
                    <span class="montserrat" style="font-size:10px">Colspan</span>
                    <i class="fas fa-angle-double-right text-info" style="font-size:9px"></i>
                </div>
                <input
                    disabled 
                    type="text" 
                    class="form-control" 
                    placeholder="Colspan" 
                    id="input_campos_${this.indexCabecera}" 
                    style="font-size:11px; height:28px; text-align:center;" 
                    onkeypress="return event.charCode >= 48 && event.charCode <= 57"
                    data-action="central--reporteador#ingresarBusqueda central--reporteador#validarColspan"
                >
            </div>
            <div style=
            "
                gap:5px; 
                width:40px;
                padding:1px; 
                height:40px;
                display:flex;
                background:white;
                align-items:center; 
                justify-content:center;
                border:1px solid #d0d4da; 
                border-radius:0px 5px 5px 0px; 
                border-left:none;
            ">
                <i class="fas fa-trash text-danger" style="cursor:pointer" data-opc="${this.indexCabecera}" data-action="click->central--reporteador#eliminarCabecera"></i>
            </div>
        </div>
        `);
        this.indexCabecera ++;
        $('.divAgregarCabecera').css('margin-top', '5px');
        this.mensaje.mostrarMensaje('¡La cabecera se agregó con éxito!', 1);
    }

    eliminarCabecera(event)
    {
        /** En esta función se elimina una cabecera del informe */
        /** --------------------------------------------------- */

        let counCheckActivos = 0;
        let opc = event.currentTarget.dataset.opc;
        setTimeout(() => {$('#div_cabecera_'+opc).hide('400')}, 700);
        $('.cabeceraConfiguracion').each(function(){counCheckActivos ++});
        this.mensaje.mostrarMensaje('¡La cabecera se eliminó con éxito!', 1);
        $('#div_cabecera_'+opc).removeClass('animate__animated animate__fadeIn').addClass('animate__animated animate__fadeOut');
        setTimeout(() => {$('#div_cabecera_'+opc).remove(); if(counCheckActivos == 1){$('.divAgregarCabecera').css('margin-top', '-6px')}}, 800);
    }

    validarColspan()
    {
        /** En esta función se valida que la sumatoria de los colspan configurados en las cabeceras, no supere el total de campos habilitados en el informe */
        /** ----------------------------------------------------------------------------------------------------------------------------------------------- */

        let totalColspanCabeceras = 0;
        let counCheckCamposActivos = 0;
        $('.camposConfiguracion').each(function(){if($(this).is(':checked')){counCheckCamposActivos ++}});
        $('.cabeceraConfiguracion').each(function()
        {
            let opc = $(this).data('opc');
            if($(this).is(':checked'))
            {
                totalColspanCabeceras += parseFloat($('#input_campos_'+opc).val());
            }
        });
        if(totalColspanCabeceras != counCheckCamposActivos)
        {
            this.mensaje.mostrarMensaje('¡El colspan de las cabeceras debe ser igual a la cantidad de campos habilitados para el informe!');
        }
    }

    showResumen()
    {
        /** En esta función se hace visible la sección de configuraciones */
        /** ------------------------------------------------------------- */
        
        $('body').css('overflow', 'hidden');
        let icono = $('.menuReporteador').find('i');
        $('#frameResumenAgrupacion').css('display', '');
        let btnMenuReporteador = $('.menuReporteador');
        btnMenuReporteador.css('pointer-events', 'none');
        icono.removeClass('fa-times').addClass('fa-bars');
        btnMenuReporteador.removeClass('menuReporteadorError');
        $('#menuReporteador').attr('transition-style', 'out:custom:circle-swoop');
        setTimeout(() => {$('#menuReporteador').hide(); btnMenuReporteador.css('pointer-events', '');}, 1100);
        btnMenuReporteador[0].dataset.opc = 0;
    }

    hideResumen()
    {
        /** En esta función se cierra la sección de configuraciones */
        /** ------------------------------------------------------- */

        $('body').css('overflow', '');
        $('#frameResumenAgrupacion').addClass('animate__animated animate__fadeOut');
        setTimeout(() => {$('#frameResumenAgrupacion').hide().removeClass('animate__animated animate__fadeOut')}, 800);
    }

    showGraficas(event)
    {
        /** En esta función se hacen visibles las gráficas de acuerdo al campo de totalización seleccionado */
        /** ----------------------------------------------------------------------------------------------- */

        let index = 0;
        let self = this;
        let titulos = [];
        let titulosGrafica = [];
        let valoresGrafica = [];
        let coloresUtilizados = [];
        let valoresGraficaBarras = [];
        $('#divTitulosResumen').html('');
        let btnGrafica = event.currentTarget;
        let icono = btnGrafica.querySelector('i');
        let grafica = event.currentTarget.dataset.grafica;
        $('#divGraficas').show('400').css('display', 'flex');
        setTimeout(() => {$('#divGraficas').css('opacity', '1')}, 1000);
        let nombreGrafica = grafica[0].toUpperCase() + grafica.substring(1);
        let claseBtnSerach = (this.camposBusquedaAgrupacion.length > 0)?'msg':'';
        let graficaPastel = document.getElementById('graficaPastel').getContext('2d');
        let graficaBarras = document.getElementById('graficaBarras').getContext('2d');
        let borderBtnBuscarAgrupacion = (this.camposBusquedaAgrupacion.length > 0)?'2':'1';
        let iconoBtnBuscarAgrupacion = (this.camposBusquedaAgrupacion.length > 0)?'times':'search';
        if(this.graficaPastel !== null){this.graficaPastel.destroy(); this.graficaBarras.destroy();}
        let claseBtnBuscarAgrupacion = (this.camposBusquedaAgrupacion.length > 0)?'btnEliminarAgrupacionActiva':'btnBuscarAgrupacion';
        let dataActionBtnBuscarAgrupacion = (this.camposBusquedaAgrupacion.length > 0)?'eliminarBusquedaAgrupacion':'buscarAgrupacion';

        /** Se actualizan los estilos de los botones que muestran las gráficas */
        /** ------------------------------------------------------------------ */

        $('.btnGraficas').each(function()
        {
            let icono = $(this).find('i');
            icono.css('color', '#007BFF');
            $(this).css('background', 'white');
        });
        icono.style.color = 'white';
        btnGrafica.style.background = '#007BFF';

        /** Se obtienen los títulos de las agrupaciones */
        /** ------------------------------------------- */

        $('#tituloResumen').text(nombreGrafica);
        $('.tituloResumen').each(function(index)
        {   
            let titulo = [];
            let opc = $(this).data('opc');
            coloresUtilizados.push(self.colores[index]);
            $('.tituloResumen'+opc).each(function(){titulo.push($(this).text().trim())});
            titulos.push({'titulo' : titulo.join(' | ', titulo), 'color' : self.colores[index]});
        });
        titulos.forEach((item) => 
        {
            titulosGrafica.push(item.titulo);
            $('#divTitulosResumen').append(
            `
                <div class="${claseBtnBuscarAgrupacion}" style="display:flex; align-items:center; justify-content:space-between; border-radius:5px; border:1px solid #d0d4da; padding:10px 12px; position:relative">
                    <div class="animate__animated animate__fadeIn montserrat" style="display:flex; align-items:center; justify-content:center; gap: 5px;">
                        <div 
                            class="btnSearch ${claseBtnSerach}" 
                            data-opc="${index}"
                            data-action="click->central--reporteador#${dataActionBtnBuscarAgrupacion}"
                            style=
                            "
                                width:22px; 
                                height:22px; 
                                display:flex; 
                                cursor:pointer; 
                                background:gray; 
                                position:relative;
                                border-radius:50%; 
                                align-items:center; 
                                justify-content:center; 
                                transition:all 0.5s ease;
                                border:${borderBtnBuscarAgrupacion}px solid white;
                            "
                        >
                            <i class="fas fa-${iconoBtnBuscarAgrupacion}" style="font-size:10px; color:white"></i>
                            <span class="tooltip tooltipEliminar" style="background-color:#DC3545e3; left:27px;">
                                <i class="fas fa-chart-simple" style="font-size:10px; margin-top:-1px"></i> 
                                <span style="font-size:10px">Eliminar agrupación</span>
                            </span>
                        </div>
                        <span style="font-size:11px">${item.titulo}</span>
                    </div>
                    <i class="fas fa-circle" style="font-size:16px; color:${item.color}; position:relative; border:2px solid white; border-radius:50%"></i>
                </div>     
            `);
            index ++;
        });

        /** Se obtienen los campos de la totalización seleccionada */
        /** ------------------------------------------------------ */
        
        $('.campoTotalizacion'+grafica).each(function(index)
        {
            valoresGrafica.push(parseFloat($(this).text().trim().replaceAll('.', '').replace(',', '.')));
            valoresGraficaBarras.push(
            {
                label: titulosGrafica[index],
                backgroundColor:[coloresUtilizados[index]],
                data: [parseFloat($(this).text().trim().replaceAll('.', '').replace(',', '.'))]
            });
        });

        /** Se crea la gráfica de pastel */
        /** ---------------------------- */

        this.graficaPastel = new Chart(graficaPastel, 
        {
            type: 'doughnut',
            data:
            {
                labels: titulosGrafica,
                datasets: 
                [
                    {
                        label: nombreGrafica,
                        data: valoresGrafica,
                        backgroundColor: coloresUtilizados
                    }
                ]
            },
            options: 
            {
                responsive: true,
                legend: {display: true},
                plugins: 
                {
                    legend: 
                    {
                        position: 'bottom',
                        display: false,
                    }
                }
            }   
        });

        /** Se crea la gráfica de barras */
        /** ---------------------------- */

        this.graficaBarras = new Chart(graficaBarras, 
        {
            type: 'bar',
            data:
            {
                labels: [nombreGrafica],
                datasets: valoresGraficaBarras
            },
            options: {
                responsive: true,
                legend: {display: true},
                plugins: 
                {
                    legend: 
                    {
                        position: 'bottom',
                        display: false,
                    }
                }
            }   
        });
    }

    buscarAgrupacion(event)
    {
        /** En esta función se genera el informe a partir de los campos de agrupación seleccionados */
        /** --------------------------------------------------------------------------------------- */
        
        let self = this;
        this.hideResumen();
        $('#busquedaRapida').val('');
        $('#busquedaRapidaHidden').val('');
        let opc = event.currentTarget.dataset.opc;
        $('.tituloResumen'+opc).each(function()
        {
            self.camposBusquedaAgrupacion.push({'campo' : $(this).data('campo'), 'valor' : $(this).data('nombre')});
        });
        $('#btnGenerarInforme').click();
    }

    eliminarBusquedaAgrupacion()
    {
        /** En esta función se elimina la agrupación seleccionada en la búsqueda de registros */
        /** --------------------------------------------------------------------------------- */

        this.hideResumen();
        this.camposBusquedaAgrupacion = [];
        $('#btnGenerarInforme').click();
    }
}   