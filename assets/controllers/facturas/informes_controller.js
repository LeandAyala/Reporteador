import mensajes from '../central/mensajes';
import { Controller } from "@hotwired/stimulus";

export default class extends Controller 
{
    mensaje = new mensajes();
    estadoSeccionFiltros = 1;
    estadoPaginaSeleccionada = 0;

    static values = 
    {
        'urlGenerarInforme' : String,
    };

    static targets = 
    [
        'formFiltros', 'cargandoFrameNuevoFactura', 'frameNuevoFactura', 'formularioPrincipal', 'frameListaFactura',
        'cargandoFiltros', 'totalRegistrosHidden', 'paginaHidden'
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
        paginaActual = (this.estadoPaginaSeleccionada == 1)?paginaActual:1;
        form.append('pagina', paginaActual);
        this.estadoPaginaSeleccionada = 0;

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
}