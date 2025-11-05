import mensajes from '../central/mensajes';
import { Controller } from "@hotwired/stimulus";

export default class extends Controller 
{
    campos = [];
    editor = null;
    lineaError = -1;
    keyAgrupacion = 0;
    keyTotalizacion = 0;
    camposTotalizacion = [];
    mensaje = new mensajes();
    keyTotalizacionAgrupacion = 0;

    static values = 
    {
        'urlObtenerInforme' : String,
        'urlGuardarInforme' : String,
        'urlEliminarInforme' : String,
        'urlFrameListaInformes' : String
    };

    static targets = 
    [
        'btnGuardarInforme', 'formNuevoInforme', 'formFiltrosInforme'
    ];

    connect()
    {
        var self = this;
        console.log('connect');
        this.actualizarListaInforme();
        $('.selectpicker').selectpicker('refresh');
        $('#modalNuevoInforme').on('hidden.bs.modal', function(){self.hidePopoverEliminarInforme()});
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

        event.preventDefault();
        let btnShowModal = event.currentTarget;
        let formInforme = this.formNuevoInformeTarget;
        let idRegistro = event.currentTarget.dataset.id;
        let idActual = $('#nuevo_informe_idRegistro').val();
        let nombreInforme = event.currentTarget.dataset.nombre;
        setTimeout(() =>
        {
            this.editor.refresh();
            $('.CodeMirror-vscrollbar').addClass('listado');
        }, 500);

        /** Se cargan los campos del informe */
        /** -------------------------------- */

        $('#nuevo_informe_idRegistro').val(0);
        if(idRegistro > 0)
        {
            let form = new FormData();
            form.append('idRegistro', idRegistro);
            $('#nombreInforme').text(nombreInforme);
            $('#cargandoInforme').css('display', '');
            btnShowModal.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            $('#tituloNuevoInforme').text(`Actualizar informe - ${nombreInforme}`);
            $('#iconoNuevoInforme').removeClass('fa-external-link-alt').addClass('fa-edit');
            let consulta = await fetch(this.urlObtenerInformeValue, {'method' : 'POST', 'body' : form});
            let result = await consulta.json();

            /** Se actualiza el modal con los campos del informe */
            /** ------------------------------------------------ */

            this.campos = result.campos;
            $('#jsonContainer').html('');
            this.editor.setValue(result.sql);
            $('#nuevo_informe_tipo').val(result.tipo);
            $('#cargandoInforme').css('display', 'none');
            $('#divConfiguracionJson').css('display', '');
            $('#nuevo_informe_nombre').val(result.nombre);
            $('#nuevo_informe_modulo').val(result.modulo);
            $('#frameCamposJson').html(result.camposJson);
            setTimeout(() =>{this.editor.refresh()}, 200);
            $('#nuevo_informe_idRegistro').val(idRegistro);
            this.camposTotalizacion = result.camposTotalizacion;
            btnShowModal.innerHTML = '<i class="fas fa-cog"></i>';
            let formatter = new JSONFormatter(result.json, 1, { theme: 'light' });
            document.getElementById("jsonContainer").appendChild(formatter.render());
            $('#botonesInforme').removeClass('animate__flipInX').addClass('animate__flipOutX');
            setTimeout(() => 
            {
                $('#btnEliminarInforme').css('display', '');
                $('#botonesInforme').removeClass('animate__flipOutX').addClass('animate__flipInX');               
            }, 800);
        }
        else
        {
            $('#iconoNuevoInforme').removeClass('fa-edit').addClass('fa-external-link-alt');
            $('#divConfiguracionJson').css('display', 'none');
            $('#tituloNuevoInforme').text(`Nuevo informe`);
            if(Number(idActual) > 0)
            {
                formInforme.reset();
                $('#botonesInforme').removeClass('animate__flipInX').addClass('animate__flipOutX');
                setTimeout(() => 
                {
                    $('#btnEliminarInforme').css('display', 'none');
                    $('#botonesInforme').removeClass('animate__flipOutX').addClass('animate__flipInX');               
                }, 800);
            }
            $('#nuevo_informe_modulo').val($('#filtros_busqueda_informes_modulo').val());
            this.editor.setValue('');
        }
        $('#modalNuevoInforme').modal('show');
    }

    async guardarInforme(event)
    {
        /** En esta función se efectúa el registro/edición de un informe */
        /** ------------------------------------------------------------ */

        event.preventDefault();
        let timeMostrarError = 1;
        let configuraciones = {};
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

        /** Se obtienen todas las configuraciones del informe */
        /** ------------------------------------------------- */

        let pdf = {};
        let excel = {};
        let campos = [];
        let cabeceras = [];
        let agrupamiento = [];
        let totalizacion = [];
        let rutaFrameResumen = {};
        let rutaFrameInforme = {};
        let parametrosRutaPdf = {};
        let camposAgrupamiento = [];
        let parametrosRutaExcel = {};
        let parametrosRutaFrameInforme = {};
        let parametrosRutaFrameResumen = {};
        let camposTotalizacionAgrupamiento = [];
        let form = new FormData(event.currentTarget);

        /** Parámetros rutaFrameInforme */
        /** --------------------------- */

        $('.parametrosRutaFrame').each(function()
        {
            let key = $(this).data('key');
            let valor = $('#inputValorParametrosRutaFrame'+key).val();
            let nombre = $('#inputNombreParametrosRutaFrame'+key).val();
            if(nombre != '' && valor != '')
            {
                parametrosRutaFrameInforme[`${nombre}`] = valor;
            }
        });
        if($('#inputRutaFrameInforme').val() != '')
        {
            rutaFrameInforme = {'nombre' : $('#inputRutaFrameInforme').val(), 'parametros' : parametrosRutaFrameInforme};
        }

        /** Cabeceras del informe */
        /** --------------------- */

        $('.cabecera').each(function()
        {
            let key = $(this).data('key');
            let nombre = $('#inputNombreCabecera'+key).val();
            let colspan = $('#inputColspanCabecera'+key).val();
            if(nombre != '' && colspan != '')
            {
                cabeceras.push({'nombre' : nombre, 'colspan' : colspan});
            }
        });

        /** Campos del informe */
        /** ------------------ */

        $('.campos').each(function()
        {
            let ruta = {};
            let parametros = {};
            let key = $(this).data('key');
            let html = $('#inputHtmlCampo'+key).val();
            let nombre = $('#inputNombreCampo'+key).val();
            let titulo = $('#inputTituloCampo'+key).val();
            let nombreRuta = $('#inputRutaCampo'+key).val();
            let tipoDato = $('#selectTipoDatoCampo'+key).val();
            let alineacionCampo = $('#selectAlineacionCampo'+key).val();
            let alineacionTitulo = $('#selectAlineacionTitulo'+key).val();

            /** Se obtienen los parámetros configurados en la ruta de cada campo */
            /** ---------------------------------------------------------------- */

            $('.parametrosRutaCampo'+nombre).each(function()
            {
                let key = $(this).data('key');
                let valor = $(`#inputValorParametrosRutaCampo${nombre}${key}`).val();
                let nombreRutaCampo = $(`#inputNombreParametrosRutaCampo${nombre}${key}`).val();
                if(nombreRutaCampo != '' && valor != '')
                {
                    parametros[`${nombreRutaCampo}`] = valor;
                }
            });
            if(nombreRuta != '')
            {
                ruta = {'nombre' : nombreRuta, 'parametros' : parametros}
            }
            campos.push(
            {
                'nombre' : nombre, 
                'titulo' : titulo, 
                'tipoDato' : tipoDato, 
                'alineacionCampo' : alineacionCampo, 
                'alineacionTitulo' : alineacionTitulo, 
                'ruta' : ruta,
                'html' : html 
            });

        });

        /** Campos de agrupación */
        /** -------------------- */

        $('.agrupamiento').each(function()
        {
            let key = $(this).data('key');
            let nombre = $('#selectAgrupamiento'+key).val();
            let titulo = $('#inputAgrupamiento'+key).val();
            camposAgrupamiento.push({'nombre' : nombre, 'titulo' : titulo});
        });

        /** Totales de agrupación */
        /** --------------------- */

        if(camposAgrupamiento.length > 0)
        {
            $('.totalizacionAgrupamiento').each(function()
            {
                let key = $(this).data('key');
                let campo = $('#selectTotalizacionAgrupamiento'+key).val();
                camposTotalizacionAgrupamiento.push({'campo' : campo});
            });
        }

        /** Totales del informe */
        /** ------------------- */

        $('.totalizacionInforme').each(function()
        {
            let key = $(this).data('key');
            let campo = $('#selectTotalizacionInforme'+key).val();
            totalizacion.push({'campo' : campo});
        });
        agrupamiento = [{'campos' : camposAgrupamiento, 'totalizacion' : camposTotalizacionAgrupamiento}];

        /** PDF del informe */
        /** --------------- */

        pdf = {'tipoHoja' : $('#tipoHoja').val(), 'orientacion' : $('#orientacion').val(), 'ruta' : {}};

        /** Se obtienen los parámetros configurados en la ruta del PDF */
        /** ---------------------------------------------------------- */

        $('.parametrosRutaPdf').each(function()
        {
            let key = $(this).data('key');
            let valor = $('#inputValorParametrosRutaPdf'+key).val();
            let nombre = $('#inputNombreParametrosRutaPdf'+key).val();
            if(valor !== '' && nombre !== '')
            {
                parametrosRutaPdf[`${nombre}`] = valor;
            }
        });
        if($('#inputRutaPdf').val() !== '')
        {
            pdf.ruta = {'nombre' : $('#inputRutaPdf').val(), 'parametros' : parametrosRutaPdf};;
        }

        /** Se obtienen los parámetros configurados en la ruta del EXCEL */
        /** ------------------------------------------------------------ */

        $('.parametrosRutaExcel').each(function()
        {
            let key = $(this).data('key');
            let valor = $('#inputValorParametrosRutaExcel'+key).val();
            let nombre = $('#inputNombreParametrosRutaExcel'+key).val();
            if(valor !== '' && nombre !== '')
            {
                parametrosRutaExcel[`${nombre}`] = valor;
            }
        });
        if($('#inputRutaExcel').val() !== '')
        {
            excel.ruta = {'nombre' : $('#inputRutaExcel').val(), 'parametros' : parametrosRutaExcel};
        }

        /** Parámetros rutaFrameResumen */
        /** --------------------------- */

        $('.parametrosRutaFrameResumen').each(function()
        {
            let key = $(this).data('key');
            let valor = $('#inputValorParametrosRutaFrameResumen'+key).val();
            let nombre = $('#inputNombreParametrosRutaFrameResumen'+key).val();
            if(nombre != '' && valor != '')
            {
                parametrosRutaFrameResumen[`${nombre}`] = valor;
            }
        });
        if($('#inputRutaFrameResumen').val() != '')
        {
            rutaFrameResumen = {'nombre' : $('#inputRutaFrameResumen').val(), 'parametros' : parametrosRutaFrameResumen};
        }

        /** Se crea las configuraciones del informe */
        /** --------------------------------------- */

        configuraciones.rutaFrameInforme = rutaFrameInforme;
        configuraciones.periodo = $('#periodoInforme').val();
        configuraciones.anchoTabla = $('#anchoTablaInforme').val();
        configuraciones.cabecera = cabeceras;
        configuraciones.campos = campos;
        configuraciones.agrupamiento = agrupamiento;
        configuraciones.paginacion = $('#paginacionInforme').val();
        configuraciones.totalizacion = totalizacion;
        configuraciones.pdf = pdf;
        configuraciones.excel = excel;
        configuraciones.rutaFrameResumen = rutaFrameResumen;
        form.append('configuraciones', JSON.stringify(configuraciones));
        
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
            this.hideMensajeError();
            this.campos = result.campos;
            $('#jsonContainer').html('');
            $('#divConfiguracionJson').css('display', '');
            this.camposTotalizacion = result.camposTotalizacion;
            if(Number($('#nuevo_informe_idRegistro').val()) == 0)
            {
                $('#divTituloNuevoInforme').addClass('animate__animated animate__flipOutX');
                setTimeout(() => 
                {
                    $('#iconoNuevoInforme').removeClass('fa-external-link-alt').addClass('fa-edit');
                    $('#tituloNuevoInforme').text(`Actualizar informe - ${$('#nuevo_informe_nombre').val()}`);
                    $('#divTituloNuevoInforme').removeClass('animate__animated animate__flipOutX').addClass('animate__animated animate__flipInX');
                }, 800);
                setTimeout(() => {$('#divTituloNuevoInforme').removeClass('animate__animated animate__flipInX')}, 2000);
            }
            $('#nuevo_informe_idRegistro').val(result.idInforme);
            $('#nombreInforme').text($('#nuevo_informe_nombre').val());
            $('#botonesInforme').removeClass('animate__flipInX').addClass('animate__flipOutX');
            setTimeout(() => 
            {
                $('#btnEliminarInforme').css('display', '');
                $('#botonesInforme').removeClass('animate__flipOutX').addClass('animate__flipInX');               
            }, 800);
            let formatter = new JSONFormatter(result.configuraciones, 1, { theme: 'light' });
            document.getElementById("jsonContainer").appendChild(formatter.render());
            this.mensaje.mostrarMensaje('¡El informe se ha guardado con éxito!', 1);
            $('#frameCamposJson').html(result.camposJson);

            /** Se actualiza la lista de informes */
            /** --------------------------------- */

            await this.actualizarListaInforme();
        }
        else
        {  
            if(result.tipoError == 0)
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
            else
            {
                this.mensaje.mostrarMensaje(result.message, 2);
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

    agregarParametro(event)
    {
        /** En esta función se agregar parámetros en las configuraciones donde está permitido incluir rutas */
        /** ----------------------------------------------------------------------------------------------- */

        let key = 0;
        let clase = event.currentTarget.dataset.clase;
        let lista = event.currentTarget.dataset.lista;
        $('.'+clase).each(function(){key ++});
        let nombreInput = clase[0].toUpperCase() + clase.substring(1) + (key + 1);
        $('#'+lista).append(
        `
            <div class="${clase} animate__animated animate__fadeIn" data-key="${key + 1}" id="${clase + (key + 1)}" style="display:flex; align-items:center; border:1px solid #d0d4da; width:100%; padding:8px 10px; border-radius:5px; gap:7px">
                <div style="display:flex; position:relative; flex:1">
                    <div style="display:flex; align-items:center; justify-content:center; border-radius:50%; width:0px; height:0px; position:absolute; background:#E9ECEF; padding:13px; border: 1px solid #d0d4da;">
                        <i class="fas fa-check" style="font-size:12px"></i>
                    </div>
                    <input id="inputNombre${nombreInput}" type="text" class="form-control" style="font-size:11px; border-radius:15px 7px 7px 15px; padding-left:40px" placeholder="Nombre parámetro">
                </div>
                <div style="display:flex; position:relative; width:150px">
                    <div style="display:flex; align-items:center; justify-content:center; border-radius:50%; width:0px; height:0px; position:absolute; background:#E9ECEF; padding:13px; border: 1px solid #d0d4da;">
                        <i class="fas fa-tag" style="font-size:12px"></i>
                    </div>
                    <input id="inputValor${nombreInput}" type="text" class="form-control" style="font-size:11px; border-radius:15px 7px 7px 15px; padding-left:40px" placeholder="Valor parámetro">
                </div>
                <div style="display:flex; position:relative; width:14px; justify-content:end">
                    <i class="fas fa-trash text-danger" data-key="${clase + (key + 1)}" style="cursor:pointer" data-action="click->facturas--reporteador#eliminarParametro"></i>
                </div>
            </div>
        `);
    }

    eliminarParametro(event)
    {
        /** En esta función se eliminan parámetros en las configuraciones donde está permitido agregar rutas */
        /** ------------------------------------------------------------------------------------------------ */

        let key = event.currentTarget.dataset.key;
        setTimeout(() =>{$('#'+key).remove()}, 1000);
        setTimeout(() =>{$('#'+key).hide(400)}, 600);
        $('#'+key).removeClass('animate__animated animate__fadeIn').addClass('animate__animated animate__fadeOut');
    }

    agregarCabecera()
    {
        /** En esta función se agregar parámetros en las configuraciones donde está permitido incluir rutas */
        /** ----------------------------------------------------------------------------------------------- */

        let key = 0;
        $('.cabecera').each(function(){key ++});
        $('#listCabeceras').append(
        `
            <div class="cabecera animate__animated animate__fadeIn" data-key="${key + 1}" id="cabecera${key + 1}" style="display:flex; align-items:center; border:1px solid #d0d4da; width:100%; padding:8px 10px; border-radius:5px; gap:7px">
                <div style="display:flex; position:relative; flex:1">
                    <div style="display:flex; align-items:center; justify-content:center; border-radius:50%; width:0px; height:0px; position:absolute; background:#E9ECEF; padding:13px; border: 1px solid #d0d4da;">
                        <i class="fas fa-check" style="font-size:12px"></i>
                    </div>
                    <input id="inputNombreCabecera${key + 1}" type="text" class="form-control" style="font-size:11px; border-radius:15px 7px 7px 15px; padding-left:40px" placeholder="Nombre cabecera">
                </div>
                <div style="display:flex; position:relative; width:150px">
                    <div style="display:flex; align-items:center; justify-content:center; border-radius:50%; width:0px; height:0px; position:absolute; background:#E9ECEF; padding:13px; border: 1px solid #d0d4da;">
                        <i class="fas fa-tag" style="font-size:12px"></i>
                    </div>
                    <input id="inputColspanCabecera${key + 1}" type="text" class="form-control" style="font-size:11px; border-radius:15px 7px 7px 15px; padding-left:40px" placeholder="Colspan" onkeypress="return event.charCode >= 48 && event.charCode <= 57">
                </div>
                <div style="display:flex; position:relative; width:14px; justify-content:end">
                    <i class="fas fa-trash text-danger" data-key="cabecera${key + 1}" style="cursor:pointer" data-action="click->facturas--reporteador#eliminarCabecera"></i>
                </div>
            </div>
        `);
    }

    eliminarCabecera(event)
    {
        /** En esta función se eliminan parámetros en las configuraciones donde está permitido agregar rutas */
        /** ------------------------------------------------------------------------------------------------ */

        let key = event.currentTarget.dataset.key;
        setTimeout(() =>{$('#'+key).remove()}, 1000);
        setTimeout(() =>{$('#'+key).hide(400)}, 600);
        $('#'+key).removeClass('animate__animated animate__fadeIn').addClass('animate__animated animate__fadeOut');
    }  

    agregarAgrupamiento()
    {
        /** En esta función se agregan campos de agrupamiento */
        /** ------------------------------------------------- */

        let key = 0;
        let index = 1;
        let campos = '';
        let seleccion = '';
        let camposUtilizados = [];
        let camposAgrupamiento = [];
        $('.agrupamiento').each(function()
        {
            key ++;
            let keyCampo = $(this).data('key');
            camposUtilizados.push($('#selectAgrupamiento'+keyCampo).val());
        });
        if(key == 3)
        {
            this.mensaje.mostrarMensaje('¡Solo es posible agregar 3 campos de agrupación!', 3);
            return;
        }
        if(this.campos.length == 0)
        {
            this.mensaje.mostrarMensaje('¡No se han configurado campos con el tipo de dato texto!', 2);
            return;
        }
        if(this.keyAgrupacion === 0)
        {
            this.keyAgrupacion = key + 1;
            key = this.keyAgrupacion;
        }
        else
        {
            this.keyAgrupacion ++;
            key = this.keyAgrupacion;
        }

        /** Se valida que existan campos disponibles para seleccionar */
        /** --------------------------------------------------------- */

        camposAgrupamiento = this.campos.filter((item) => !camposUtilizados.includes(item));
        if(camposAgrupamiento.length == 0)
        {
            this.mensaje.mostrarMensaje('¡No existen campos de agrupación disponibles!', 2);
            return;
        }
        camposAgrupamiento.forEach((item) => 
        {
            campos += `<option class="optionConfiguraciones" value="${item}">${item}</option>`;
            if(index === 1){seleccion = item;}
            index ++;
        });
        $('#listAgrupamiento').append(
        `
            <div class="agrupamiento animate__animated animate__fadeIn" data-key="${key}" id="agrupamiento${key}" style="display:flex; align-items:center; border:1px solid #d0d4da; width:100%; padding:8px 10px; border-radius:5px; gap:7px">
                <div style="width:100%; display:flex; gap:10px; position:relative; flex:1">
                    <div style="display:flex; align-items:center; justify-content:center; border-radius:50%; width:0px; height:0px; position:absolute; background:#E9ECEF; padding:14px; border: 1px solid #d0d4da;">
                        <i class="fas fa-tag" style="font-size:12px"></i>
                    </div>
                    <select class="form-control custom-select selectAgrupamiento" data-clase="selectAgrupamiento" data-seleccion="${seleccion}" id="selectAgrupamiento${key}" style="font-size:12px; border-radius:15px 7px 7px 15px; padding-left:40px" data-action="facturas--reporteador#seleccionarOpcion">
                        ${campos}
                    </select>
                </div>
                <div style="display:flex; position:relative; flex:1">
                    <div style="display:flex; align-items:center; justify-content:center; border-radius:50%; width:0px; height:0px; position:absolute; background:#E9ECEF; padding:13px; border: 1px solid #d0d4da;">
                        <i class="fas fa-check" style="font-size:12px"></i>
                    </div>
                    <input id="inputAgrupamiento${key}" type="text" class="form-control" style="font-size:11px; border-radius:15px 7px 7px 15px; padding-left:40px" placeholder="Título agrupamiento">
                </div>
                <div style="display:flex; position:relative; width:14px; justify-content:end">
                    <i class="fas fa-trash text-danger" data-key="agrupamiento${key}" style="cursor:pointer" data-action="click->facturas--reporteador#eliminarAgrupamiento"></i>
                </div>
            </div>
        `);
    }

    eliminarAgrupamiento(event)
    {
        /** En esta función se eliminan campos de agrupamiento */
        /** -------------------------------------------------- */

        let keys = 0;
        let key = event.currentTarget.dataset.key;
        $('.agrupamiento').each(function(){keys ++});
        setTimeout(() =>{$('#'+key).remove()}, 1000);
        setTimeout(() =>{$('#'+key).hide(400)}, 600);
        if(this.keyAgrupacion === 0){this.keyAgrupacion = keys;}
        $('#'+key).removeClass('animate__animated animate__fadeIn').addClass('animate__animated animate__fadeOut');
    } 

    agregarTotalizacion(event)
    {
        /** En esta función se agregar campos de totalización */
        /** ------------------------------------------------- */

        let key = 0;
        let index = 1;
        let campos = '';
        let seleccion = '';
        let camposUtilizados = [];
        let camposTotalizacion = [];
        let clase = event.currentTarget.dataset.clase;
        let lista = event.currentTarget.dataset.lista;
        let claseSelect = event.currentTarget.dataset.claseselect;
        if(this.camposTotalizacion.length == 0)
        {
            this.mensaje.mostrarMensaje('¡No se han configurado campos con los tipos de dato: numero o moneda!', 2);
            return;
        }

        /** Se valida que existan campos disponibles para seleccionar */
        /** --------------------------------------------------------- */
        
        $('.'+clase).each(function()
        {
            key ++;
            let keyCampo = $(this).data('key');
            camposUtilizados.push($('#select'+clase[0].toUpperCase() + clase.substring(1) + keyCampo).val());
        });
        if(claseSelect === 'selectTotalizacionAgrupamiento')
        {
            if(this.keyTotalizacionAgrupacion > 0)
            {
                this.keyTotalizacionAgrupacion ++;
                key = this.keyTotalizacionAgrupacion;
            }
            else
            {
                this.keyTotalizacionAgrupacion = key + 1;
                key = this.keyTotalizacionAgrupacion;
            }
        }
        else
        {
            if(this.keyTotalizacion > 0)
            {
                this.keyTotalizacion ++;
                key = this.keyTotalizacion;
            }
            else
            {
                this.keyTotalizacion = key + 1;
                key = this.keyTotalizacion;
            }
        }
        camposTotalizacion = this.camposTotalizacion.filter((item) => !camposUtilizados.includes(item));
        if(camposTotalizacion.length == 0)
        {
            this.mensaje.mostrarMensaje('¡No existen campos de totalización disponibles!', 2);
            return;
        }
        let nombreSelect = clase[0].toUpperCase() + clase.substring(1) + key;
        camposTotalizacion.forEach((item) => 
        {
            campos += `<option class="optionConfiguraciones" value="${item}">${item}</option>`;
            if(index === 1){seleccion = item}
            index ++;
        });
        $('#'+lista).append(
        `
            <div class="${clase} animate__animated animate__fadeIn" data-key="${key}" id="${clase + key}" style="display:flex; align-items:center; border:1px solid #d0d4da; width:100%; padding:8px 10px; border-radius:5px; gap:7px">
                <div style="width:100%; display:flex; gap:10px; position:relative; flex:1">
                    <div style="display:flex; align-items:center; justify-content:center; border-radius:50%; width:0px; height:0px; position:absolute; background:#E9ECEF; padding:14px; border: 1px solid #d0d4da;">
                        <i class="fas fa-tag" style="font-size:12px"></i>
                    </div>
                    <select class="form-control custom-select ${claseSelect}" data-clase="${claseSelect}" data-seleccion="${seleccion}" id="select${nombreSelect}" style="font-size:12px; border-radius:15px 7px 7px 15px; padding-left:40px" data-action="facturas--reporteador#seleccionarOpcion">
                        ${campos}
                    </select>
                </div>
                <div style="display:flex; position:relative; width:14px; justify-content:end">
                    <i class="fas fa-trash text-danger" data-key="${clase + key}" style="cursor:pointer" data-action="click->facturas--reporteador#eliminarTotalizacion"></i>
                </div>
            </div>
        `);
    }

    eliminarTotalizacion(event)
    {
        /** En esta función se eliminan campos de totalización */
        /** -------------------------------------------------- */
        
        let keys = 0;
        let opc = event.currentTarget.dataset.opc;
        let key = event.currentTarget.dataset.key;
        if(opc === 'totalizacionAgrupacion')
        {
            $('.totalizacionAgrupamiento').each(function(){keys ++});
            if(this.keyTotalizacionAgrupacion === 0){this.keyTotalizacionAgrupacion = keys;}
        }
        else
        {
            $('.totalizacionInforme').each(function(){keys ++});
            if(this.keyTotalizacion === 0){this.keyTotalizacion = keys;}
        }
        setTimeout(() =>{$('#'+key).remove()}, 1000);
        setTimeout(() =>{$('#'+key).hide(400)}, 600);
        $('#'+key).removeClass('animate__animated animate__fadeIn').addClass('animate__animated animate__fadeOut');
    }

    async actualizarListaInforme()
    {
        /** En esta función se actualiza la lista de informes */
        /** ------------------------------------------------- */
        
        $('#cargandoListaInformes').css('display', '');
        let form = new FormData(this.formFiltrosInformeTarget);
        let consulta = await fetch(this.urlFrameListaInformesValue, {'method' : 'POST', 'body' : form});
        let result = await consulta.json();
        $('#frameListaInformes').html(result.plantilla);
        $('#cargandoListaInformes').css('display', 'none');
        $('#totalRegistros').removeClass('animate__animated animate__flipInX').addClass('animate__animated animate__flipOutX');
        setTimeout(() =>
        {
            if(result.countRegistros > 0)
            {
                $('#totalRegistros').removeClass('animate__animated animate__flipOutX').addClass('animate__animated animate__flipInX');
                $('#totalRegistros').html(`Total registros: ${result.countRegistros}`);
            }
        }, 800); 
    }

    async enviarFiltrosInforme(event)
    {
        /** En esta función se actualiza la lista de informes de acuerdo a los parámetros de búsqueda seleccionados */
        /** ------------------------------------------------------------------------------------------------------- */

        event.preventDefault();
        $('#btnBuscarInformes').html('<i class="fas fa-spinner fa-spin"></i>');
        await this.actualizarListaInforme();
        $('#btnBuscarInformes').html('<i class="fas fa-search"></i>');
    }

    showPopoverEliminarInforme()
    {
        /** En esta función se hace visible el popover para eliminar un informe */
        /** ------------------------------------------------------------------- */

        $('#popoverEliminarInforme').css('display', '');
    }

    hidePopoverEliminarInforme(event = null)
    {
        /** En esta función se oculta el popover para eliminar un informe */
        /** ------------------------------------------------------------- */

        if(event != null){event.preventDefault();}
        $('#popoverEliminarInforme').removeClass('animate__fadeIn').addClass('animate__fadeOut');
        setTimeout(()=>{$('#popoverEliminarInforme').hide().removeClass('animate__fadeOut').addClass('animate__fadeIn')}, 1000);
    }

    async confirmarEliminarInforme()
    {
        /** En esta función se hace efectiva la eliminación de un informe que consiste en actualizar el estado a 2 */
        /** ------------------------------------------------------------------------------------------------------ */

        let form = new FormData();
        this.hidePopoverEliminarInforme();
        form.append('idRegistro', $('#nuevo_informe_idRegistro').val());
        $('#btnEliminarInforme').html('<i class="fas fa-spinner fa-spin"></i> Eliminando');
        await fetch(this.urlEliminarInformeValue, {'method' : 'POST', 'body' : form});
        this.mensaje.mostrarMensaje('¡El informe se ha eliminado con éxito!', 1);
        $('#btnEliminarInforme').html('<i class="fas fa-trash"></i> Eliminar');
        $('#modalNuevoInforme').modal('hide');
        await this.actualizarListaInforme();
    }

    seleccionarOpcion(event)
    {
        /** En esta función se valida si un campo de agrupación o totalización (General - Agrupación) ya se encuentra seleccionado */
        /** ---------------------------------------------------------------------------------------------------------------------- */

        let opcionesSeleccionadas = [];
        let clase = event.currentTarget.dataset.clase;
        let opcionSeleccionada = event.currentTarget.dataset.seleccion;
        opcionesSeleccionadas = $('.'+clase).filter(function(){return $(this).val() === event.currentTarget.value});
        if(opcionesSeleccionadas.length > 1)
        {
            this.mensaje.mostrarMensaje(`¡El campo ${event.currentTarget.value} ya se encuentra seleccionado!`);
            event.currentTarget.value = opcionSeleccionada;
        }
    }
}   