<?php

namespace App\Controller\Facturas;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Entity\Central\meses;
use App\Entity\Central\compania;
use App\Entity\Facturas\Reporte;
use App\Entity\Facturas\Factura;
use App\Entity\Productos\Producto;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Form\Facturas\FiltrosInformesType;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Doctrine\DBAL\Exception\ConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class InformesController extends AbstractController
{
    private $em;
    private $ultimaColumna;
    private $camposTotalizados;
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->ultimaColumna = '';
        $this->camposTotalizados = [];
    }

    public function informes(Request $request)
    {
        /** 
         * En esta función se crea la vista para generar informes
         * ------------------------------------------------------
         * @access public
        */

        $formFiltros = $this->createForm(FiltrosInformesType::class, null);
        return $this->render('facturas\informes.html.twig', ['formFiltros' => $formFiltros->createView()]);
    }

    public function generarInforme(Request $request)
    {
        /** 
         * En esta función se genera el contenido html del informe con base al sql almacenado para un reporte específico
         * -------------------------------------------------------------------------------------------------------------
         * @access public
        */

        /** Definición de variables */
        /** ----------------------- */

        $index = 1;
        $filtros = [];
        $message = '';
        $periodo = '';
        $paginas = [];
        $cabecera = [];
        $bd = $this->em;
        $plantilla = '';
        $keyCampos = [];
        $indexPagina = 1;
        $paginacion = 100;
        $tablaTotales = [];
        $agrupamiento = [];
        $status = 'success';
        $totalRegistros = 0;
        $indexPaginacion = 1;
        $contenidoInforme = '';
        $indexTotalPaginas = 1;
        $camposAgrupacion = [];
        $botonesPaginator = '';
        $camposTotalizacion = [];
        $camposPeriodoValido = [];
        $contenidoPaginacion = '';
        $configuracionCampos = [];
        $rellenoOpcionPaginator = '';
        $iconoBloqueo = 'color:gray;';
        $listRegistrosPaginacion = [];
        $conexion = $bd->getConnection();
        $listRegistrosBusquedaRapida = [];
        $accionBloqueo = 'pointer-events:none;';
        $form = $request->request->get('filtros_informes');
        $busquedaRapida = $request->request->get('busquedaRapida');
        $compania = $bd->getRepository(compania::class)->findOneBy([]);
        $alineaciones = ['centro' => 'center', 'derecha' => 'right', 'izquierda' => 'left'];
        $informe = $bd->getRepository(Reporte::class)->findOneBy(['id' => $form['informe']]);
        $fondo = base64_encode(file_get_contents($this->getParameter('imgs_directory').'fondo.jpg'));

        /** Se obtienen los filtros de búsqueda seleccionados */
        /** ------------------------------------------------- */

        $sqlInforme = $informe->getSql();
        $nitCompania = $compania->getNit();
        $logo = $compania->getLogocompania();
        $nombreInforme = $informe->getNombre();
        $pagina = $request->request->get('pagina');
        $nombreCompania = strtoupper($compania->getNombre());
        foreach($form as $key => $campo){$filtros['['.$key.']'] = !empty($campo)?$campo:-1;}
        $direccionCompania = substr($compania->getDireccion(), 0, 50).' - '.$compania->getTelefonos();
        $sqlInforme = strtr($sqlInforme, $filtros);

        /** Se obtiene el json que contiene las configuraciones del informe */
        /** --------------------------------------------------------------- */

        $tablaTotales['colspan'] = 0;
        $configuraciones = $informe->getJson();
        if(!empty($configuraciones))
        {
            if(array_key_exists('campos', $configuraciones)){$configuracionCampos = $configuraciones['campos'];}
            if(array_key_exists('paginacion', $configuraciones) && $configuraciones['paginacion'] && $configuraciones['paginacion'] >= 10){$paginacion = $configuraciones['paginacion'];}
            if(array_key_exists('cabecera', $configuraciones) && is_array($configuraciones['cabecera']) && !empty($configuraciones['cabecera'])){$cabecera = $configuraciones['cabecera'];}
            if(array_key_exists('agrupamiento', $configuraciones) && is_array($configuraciones['agrupamiento']) && !empty($configuraciones['agrupamiento'])){$agrupamiento = $configuraciones['agrupamiento'];}
            if(array_key_exists('totalizacion', $configuraciones) && !empty($configuraciones['totalizacion']) && is_array($configuraciones['totalizacion'])){$camposTotalizacion = $configuraciones['totalizacion'];}
            if(array_key_exists('periodo', $configuraciones) && !empty($configuraciones['periodo']))
            {
                preg_match_all('/\[(.*?)\]/', $configuraciones['periodo'], $campos);
                if(!empty($campos))
                {
                    foreach($campos[0] as $campo)
                    {
                        if(array_key_exists($campo, $filtros) && date('Y-m-d', strtotime($filtros[$campo])) == $filtros[$campo])
                        {
                            $fecha = explode('-', $filtros[$campo]);
                            $mes = $bd->getRepository(meses::class)->findOneBy(['numero' => $fecha[1]]);
                            $camposPeriodoValido[$campo] = $fecha[2].' de '.$mes->getNombre().' de '.$fecha[0];
                        }
                    }
                    if(count($camposPeriodoValido) == count($campos[0]))
                    {
                        $periodo = strtr($configuraciones['periodo'], $camposPeriodoValido);
                        $periodo =
                        <<<TWIG
                        <div style="display:flex; align-items:center; gap:5px;">
                            <i class="fas fa-calendar" style="font-size:11px"></i>
                            <span class="montserrat-text" style="font-size:11px;">$periodo</span>
                        </div>
                        TWIG;
                    }
                }
            }
        }

        /** Se realiza la consulta de los registros */
        /** --------------------------------------- */

        try 
        {
            $listRegistros = $conexion->prepare($sqlInforme)->executeQuery()->fetchAll();

            /** Se filtran los registros de acuerdo a la búsqueda rápida */
            /** -------------------------------------------------------- */

            if($busquedaRapida != '')
            {
                foreach($listRegistros as $registro)
                {
                    foreach($registro as $campo)
                    {
                        if(strpos($campo, $busquedaRapida) !== false)
                        {
                            $listRegistrosBusquedaRapida[] = $registro;
                        }
                    }
                }
                $listRegistros = $listRegistrosBusquedaRapida;
            }

            /** Se genera la paginación de los registros */
            /** ---------------------------------------- */

            $totalRegistros = count($listRegistros);
            foreach($listRegistros as $indexRegistro => $registro)
            {
                $dataRegistro[] = $registro;
                if($indexPaginacion == $paginacion || ($indexRegistro == count($listRegistros) - 1))
                {
                    $listRegistrosPaginacion[] = $dataRegistro;
                    $paginas[] = $indexTotalPaginas; 
                    $indexTotalPaginas ++;
                    $indexPaginacion = 0;
                    $dataRegistro = [];
                    $indexPagina ++;
                }
                $indexPaginacion ++;

                /** Se obtiene la totalización de los campos */
                /** ---------------------------------------- */

                foreach($camposTotalizacion as $ct)
                {
                    if(array_key_exists('campo', $ct) && array_key_exists($ct['campo'], $registro))
                    {
                        if(array_key_exists($ct['campo'], $this->camposTotalizados))
                        {
                            $this->camposTotalizados[$ct['campo']] = $this->camposTotalizados[$ct['campo']] + $registro[$ct['campo']];
                        }
                        else
                        {
                            $this->camposTotalizados[$ct['campo']] = $registro[$ct['campo']];
                        }
                    }
                }
            }

            /** Se crean las opciones del paginator */
            /** ----------------------------------- */

            if($pagina > 5)
            {
                $paginas = array_slice($paginas, $pagina - 1, 5);
                if(count($paginas) < 5)
                {
                    $paginasCompletar = 5 - count($paginas);
                    for($i = $paginasCompletar; $i >= 1; $i--)
                    {
                        $paginasControl[] = $pagina - $i; 
                    }
                    $paginas = array_merge($paginasControl, $paginas);
                }
            }
            else
            {
                $paginas = array_slice($paginas, 0, 5);
            }

            foreach($paginas as $p)
            {
                $rellenoOpcionPaginator = ($pagina == $p)?'background:#17A; color:white;':'background:white';
                $botonesPaginator .=
                    <<<TWIG
                    <div class="montserrat paginas" data-action="click->facturas--informes#seleccionarPagina" data-opc="1" data-pagina="$p" style=
                    "
                        width:25px; 
                        height:25px; 
                        display:flex;
                        cursor:pointer;
                        border-radius:50%;
                        align-items:center; 
                        justify-content:center; 
                        $rellenoOpcionPaginator
                    ">
                        $p
                    </div>
                    TWIG;
            }

            /** Se validan las opciones back y next del paginator para asignar los estilos respectivos de acuerdo a la página seleccionada */
            /** -------------------------------------------------------------------------------------------------------------------------- */

            $iconoBotonAnterior = ($pagina == 1)?$iconoBloqueo:'';
            $accionBotonAnterior = ($pagina == 1)?$accionBloqueo:'';
            $iconoBotonSiguiente = ($pagina == count($listRegistrosPaginacion))?$iconoBloqueo:'';
            $accionBotonSiguiente = ($pagina == count($listRegistrosPaginacion))?$accionBloqueo:'';

            if(!empty($listRegistrosPaginacion))
            {
                $listRegistros = $listRegistrosPaginacion[$pagina - 1];

                /** Se valida si existen campos de agrupación configurados */
                /** ------------------------------------------------------ */

                if(array_key_exists('campos', $agrupamiento[0]) && is_array($agrupamiento[0]['campos']) && !empty($agrupamiento[0]['campos']))
                {
                    $keyCampos = array_keys($listRegistros[0]);
                    foreach($keyCampos as $campo)
                    {
                        foreach($agrupamiento[0]['campos'] as $a)
                        {
                            if($a['nombre'] == $campo){$camposAgrupacion[] = $campo;}
                        }
                    }
                }

                if(!empty($camposAgrupacion))
                {
                    /** Se genera el informe con campos de agrupación */
                    /** --------------------------------------------- */
                    
                    $listAgrupada = [];
                    $campoControl = '';
                    $campoAnterior = '';
                    $camposReferencia = [];
                    $divTotalesGenerales = '';
                    
                    /** Se ordena la información de acuerdo a los campos de agrupación configurados en el informe */
                    /** ----------------------------------------------------------------------------------------- */
            
                    foreach($camposAgrupacion as $index => $campo)
                    {
                        if(empty($campoControl))
                        {
                            foreach($listRegistros as $registro)
                            {
                                $listAgrupada[$campo][$registro[$campo]] = $registro[$campo];
                            }
                        }
                        else
                        {
                            if($index == 1)
                            {
                                foreach($listAgrupada[$campoControl] as $c)
                                {
                                    $camposReferencia[] = $c;
                                    foreach($listRegistros as $registro)
                                    {
                                        if($registro[$campoControl] == $c)
                                        {
                                            $listAgrupada[$campo][$c][$registro[$campo]] = $registro[$campo];
                                        }
                                    }
                                }
                                unset($camposReferencia[array_key_last($camposReferencia)]);
                            }
                            else
                            {
                                foreach($camposReferencia as $cr)
                                {
                                    foreach($listAgrupada[$campoControl][$cr] as $c)
                                    {
                                        $camposReferenciaControl[] = $c;
                                        foreach($listRegistros as $registro)
                                        {
                                            if($registro[$campoControl] == $c)
                                            {
                                                $listAgrupada[$campo][$c][$registro[$campo]] = $registro[$campo];
                                            }
                                        }
                                    }
                                }
                                $camposReferencia = $camposReferenciaControl;
                            }
                        }
                        $campoControl = $campo;
                        $listAgrupada[$campo]['referencia'] = array_key_exists($index + 1, $camposAgrupacion)?$camposAgrupacion[$index + 1]:'registros';
            
                        /** Se guardan los registros de tal manera que se asocien al último nivel de agrupación */
                        /** ----------------------------------------------------------------------------------- */
            
                        if($listAgrupada[$campo]['referencia'] == 'registros')
                        {
                            if(empty($camposReferencia))
                            {
                                $campoAnterior = $camposAgrupacion[0];
                                foreach($listAgrupada[$campoAnterior] as $c)
                                {
                                    foreach($listRegistros as $registro)
                                    {
                                        if($registro[$campoAnterior] == $c)
                                        {
                                            foreach($camposAgrupacion as $campo){unset($registro[$campo]);}
                                            $listAgrupada['registros'][$c][] = $registro;
                                        }
                                    }
                                }
                            }
                            else
                            {
                                foreach($camposReferencia as $cr)
                                {
                                    foreach($listAgrupada[$campoControl][$cr] as $c)
                                    {
                                        foreach($listRegistros as $registro)
                                        {
                                            $campoAnterior = $registro[$camposAgrupacion[count($camposAgrupacion) - 2]];
                                            if($campoAnterior == $cr && $registro[$campoControl] == $c)
                                            {
                                                foreach($camposAgrupacion as $campo){unset($registro[$campo]);}
                                                $listAgrupada['registros'][$campoAnterior.$c][] = $registro;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    /** Se crea la sección de agrupamiento con todos los campos seleccionados */
                    /** --------------------------------------------------------------------- */

                    $index = 0;
                    $indexFila = 0;
                    $divAgrupacion = '';
                    $registrosAgrupados = [];
                    $divAgrupacionGeneral = '';
                    $divRegistrosAgrupacion = '';
                    foreach($listAgrupada as $key => $lista)
                    {
                        if($key == 'registros'){break;}
                        $campo = array_filter($agrupamiento[0]['campos'], fn($item) => $item['nombre'] == $key);
                        sort($campo);
                        $titulo = $campo[0]['titulo'];
                        foreach($lista as $keyFila => $items)
                        {
                            if($keyFila == 'referencia'){continue;}
                            $keyFila = str_replace(' ', '_', $keyFila);
                            $marginTopFila = ($indexFila == 0)?'':'margin-top:3px;';
                            if($index == 0)
                            {
                                $registrosAgrupados[] = $keyFila; 
                                $nombreAgrupacion = explode('-', $items);
                                if(count($nombreAgrupacion) > 1)
                                {
                                    unset($nombreAgrupacion[0]);
                                    $nombreAgrupacion = implode('-', $nombreAgrupacion);
                                }
                                $divAgrupacionGeneral .=
                                <<<TWIG
                                <div class="bg-light montserrat" style=
                                "
                                    gap:10px; 
                                    width:100%; 
                                    display:flex; 
                                    $marginTopFila
                                    font-size:11px;
                                    border-radius:5px; 
                                    padding:12px 17px; 
                                    align-items:center; 
                                    border:1px solid #dee2e6; 
                                ">
                                    <div class="titulo" style="transition:all 0.5s ease; font-size:11px; cursor:pointer" onclick="$('#$keyFila').toggle('400')">$titulo</div>
                                    <i class="fas fa-angle-double-right text-info" style="font-size:10px"></i>
                                    <span class="montserrat-text" style="font-size:11px">$nombreAgrupacion</span>
                                </div>
                                <div id="$keyFila" style="width:100%; border: 1px solid #dee2e6; padding: 10px; border-radius: 0px 0px 5px 5px; margin-top:-1px;">
                                    replace_$keyFila
                                </div>
                                TWIG;
                            }
                            else
                            {
                                foreach($items as $keyItem => $item)
                                {
                                    $keyItem = str_replace(' ', '_', $keyItem);
                                    if($index == (count($listAgrupada) - 2))
                                    {
                                        $keyItem = $keyFila.str_replace(' ', '_', $keyItem);
                                        $registrosAgrupados[] = $keyItem; 
                                    }
                                    $nombreAgrupacion = explode('-', $item);
                                    if(count($nombreAgrupacion) > 1)
                                    {
                                        unset($nombreAgrupacion[0]);
                                        $nombreAgrupacion = implode('-', $nombreAgrupacion);
                                    }
                                    $divAgrupacion .=
                                    <<<TWIG
                                    <div class="bg-light montserrat" style=
                                    "
                                        gap:10px; 
                                        width:100%; 
                                        display:flex; 
                                        margin-top:3px; 
                                        font-size:11px;
                                        border-radius:5px; 
                                        padding:12px 17px; 
                                        align-items:center; 
                                        border:1px solid #dee2e6; 
                                    ">
                                        <div class="titulo" style="transition:all 0.5s ease; font-size:11px; cursor:pointer" onclick="$('#$keyItem').toggle('400')">$titulo</div>
                                        <i class="fas fa-angle-double-right text-info" style="font-size:10px"></i>
                                        <span class="montserrat-text" style="font-size:11px">$nombreAgrupacion</span>
                                    </div>
                                    <div id="$keyItem" style="width:100%; border: 1px solid #dee2e6; padding: 10px; border-radius: 0px 0px 5px 5px; margin-top:-1px;">
                                        <div style="display:flex; align-items:center; justify-content:center; width:100%; flex-direction:column">
                                            replace_$keyItem
                                        </div>
                                    </div>
                                    TWIG;
                                }
                                $divAgrupacionGeneral = str_replace('replace_'.$keyFila, $divAgrupacion, $divAgrupacionGeneral);
                                $divAgrupacion = '';

                            }
                            
                            /** Se agrega a los items del último campo de agrupación los registros correspondientes */
                            /** ----------------------------------------------------------------------------------- */

                            if($index == (count($listAgrupada) - 2))
                            {
                                foreach($registrosAgrupados as $indexAgrupacion => $registros)
                                {
                                    if(array_key_exists(str_replace('_', ' ', $registros), $listAgrupada['registros']))
                                    {
                                        $divAgrupacion = $this->crearTablaRegistros($request, $configuraciones, $listAgrupada['registros'][str_replace('_', ' ', $registros)], true);
                                        $divAgrupacionGeneral = str_replace('replace_'.$registros, $divAgrupacion, $divAgrupacionGeneral);
                                    }
                                }
                            }
                            $indexFila ++;
                            $divAgrupacion = '';
                        }
                        $index ++;
                    }

                    /** Se genera la sección de totales obtenidos a partir de los campos de agrupación */
                    /** ------------------------------------------------------------------------------ */

                    if(!empty($this->camposTotalizados))
                    {
                        foreach($this->camposTotalizados as $index => $ct)
                        {
                            $tituloTotal = $ct[0];
                            $valorTotal = number_format($ct[1], 2, ',', '.');
                            $divTotalesGenerales .= 
                            <<<TWIG
                            <tr>
                                <th class="montserrat">
                                    <div style="background:#f8f9fa; display:flex; align-items:center; border:1px solid #dee2e6; height:31px; padding:0px 15px; width:100%; border-right:none; font-size:11px; border-radius:15px 0px 0px 15px; position:relative; z-index:1; overflow:hidden; color:white">
                                        $tituloTotal
                                        <div style="
                                            left: 0;
                                            z-index: -1;
                                            width: 100%;
                                            height: 100%;
                                            position: absolute;
                                            background: #17A;
                                            border-radius: 0px 15px 15px 0px;
                                        "></div>
                                    </div>
                                </th>
                                <th class="montserrat">
                                    <div style="background:#f8f9fa; display:flex; align-items:center; justify-content:center; border:1px solid #dee2e6; height:31px; padding:0px 15px; width:30px; border-right:none; border-left:none;">
                                        <i class="fas fa-angle-double-right" style="font-size:10px; color:#17A"></i>
                                    </div>
                                </th>
                                <td class="montserrat">
                                    <div style="background:#f8f9fa; display:flex; align-items:center; border:1px solid #dee2e6; height:31px; padding:0px 10px; border-left:none; font-size:11px; border-radius:0px 5px 5px 0px">
                                        $valorTotal
                                    </div>
                                </td>
                            </tr>
                            <tr><td style="height:5px"></td></tr>
                            TWIG;
                        }
                        $divTotalesGenerales =
                        <<<TWIG
                        <div class="animate__animated animate__fadeInRight animate__delay-1s" style="display:flex; align-items:center; justify-content:end; margin-top:15px; width:100%">
                            <table class="mb-0" border="0" cellpadding="0" cellspacing="0">
                                $divTotalesGenerales    
                            </table>
                        </div>
                        TWIG;
                    }
                    $contenidoInforme = 
                    <<<TWIG
                    <div style="display:flex; align-items:center; justify-content:center; flex-direction:column; width:100%">
                        $divAgrupacionGeneral
                        $divTotalesGenerales
                    </div>
                    TWIG;
                }
                else
                {
                    /** Se genera el informe sin campos de agrupacion */
                    /** --------------------------------------------- */
                    
                    $contenidoInforme = $this->crearTablaRegistros($request, $configuraciones, $listRegistros);
                }

                /** Contenido paginación */
                /** -------------------- */

                $contenidoPaginacion =
                <<<TWIG
                <div class="list-group-item animate__animated animate__fadeIn" style=
                "    
                    border: none;
                    display:flex;
                    margin-top:4px;
                    width:fit-content;
                    align-items:center;
                    background:#ececec;
                    justify-content:center;
                    border-radius:5px 0px 16px 0px;
                ">
                    <table border="0" cellpadding="0" cellspacing="0" style="width:100%">
                        <tr>   
                            <td>
                                <div style="display:flex; align-items:center; justify-content:center; gap:5px">
                                    <span class="montserrat" style="font-size:12px">Total registros:</span>
                                    <span class="montserrat-text" style="font-size:12px">$totalRegistros</span>
                                </div>
                            </td>
                            <td style="width:40px"></td>
                            <td style="border-left:1px solid #d1d4da; width:40px"></td>
                            <td>
                                <div style="display:flex; align-items:center; justify-content:center; gap:3px">
                                    <div class="montserrat paginas" data-action="click->facturas--informes#seleccionarPagina" data-opc="2" style=
                                    "
                                        width:25px; 
                                        height:25px; 
                                        display:flex;
                                        cursor:pointer; 
                                        background:white;
                                        border-radius:50%;
                                        align-items:center;
                                        $accionBotonAnterior
                                        justify-content:center; 
                                    ">
                                        <i class="fas fa-caret-left" style="$iconoBotonAnterior"></i>
                                    </div>
                                    $botonesPaginator
                                    <div class="montserrat paginas" data-action="click->facturas--informes#seleccionarPagina" data-opc="3" style=
                                    "
                                        width:25px; 
                                        height:25px; 
                                        display:flex;
                                        cursor:pointer; 
                                        background:white;
                                        border-radius:50%;
                                        align-items:center;
                                        $accionBotonSiguiente
                                        justify-content:center; 
                                    ">
                                        <i class="fas fa-caret-right" style="$iconoBotonSiguiente"></i>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
                TWIG;
            }
            else
            {
                $contenidoInforme =
                <<<TWIG
                    <div class="text-danger" style="height:50px; font-weight:bold; display:flex; align-items:center; justify-content:center">¡No se encontraron registros para listar!</div>
                TWIG;
            }
        } 
        catch(\Exception $e) 
        {
            $status = 'error';
            $message = $e->getMessage().' - '.$e->getFile().' - '.$e->getLine();    
        }

        /** Se genera la plantilla del informe */
        /** ---------------------------------- */

        $plantilla =
        <<<TWIG
        <div class="list-group-item p-0" style="width:100%; border:1px solid #d1d4da; border-radius:6px 6px 0px 0px; display:flex; align-items:center; justify-content:center" contenteditable="false">
            <div class="row animate__animated animate__fadeIn" style=
            "
                width:100%; 
                overflow:hidden; 
                background:white; 
                position:relative; 
                border-radius:5px;
                padding:12px 15px 20px 15px; 
            ">
                <div class="col-12">
                    <div style=
                    "   
                        top: -28px;
                        z-index: -1;
                        left: -15px;
                        height: 112px;
                        overflow: hidden;
                        position: absolute;
                        background: #f8f9fa;
                        width: calc(100% + 30px);
                        border-radius: 0px 0px 12px 12px;
                        filter: drop-shadow(2px 2px 6px gray);
                    ">
                        <img src="data:image;base64,$fondo" style=
                        "
                            left: 0px;
                            opacity: 0.1;
                            width: 520px;
                            height: 330px;
                            position: absolute;
                        ">
                        <img src="data:image;base64,$fondo" style=
                        "
                            left: 520px;
                            opacity: 0.1;
                            width: 520px;
                            height: 330px;
                            position: absolute;
                        ">
                        <img src="data:image;base64,$fondo" style=
                        "
                            left: 1040px;
                            opacity: 0.1;
                            width: 520px;
                            height: 330px;
                            position: absolute;
                        ">
                        <img src="data:image;base64,$fondo" style=
                        "
                            left: 1560px;
                            opacity: 0.1;
                            width: 520px;
                            height: 330px;
                            position: absolute;
                        ">
                    </div>
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:10px">
                        <div style="display:flex; align-items:center; gap:20px; flex:4">
                            <div style=
                            "
                                width: 70px;
                                display: flex;
                                padding: 9px;
                                height: 70px;
                                background: white;
                                border-radius: 50%;
                                align-items: center;
                                justify-content: center;
                                border: 2px solid #d2d4da;
                            ">
                                <img src="data:image;base64,$logo" style="width:100%; height:100%; object-fit:contain">
                            </div>
                            <div style="display:flex; justify-content:center; flex-direction:column; gap:3px">
                                <span class="montserrat">$nombreCompania</span>
                                <div style="display:flex; align-items:center; gap:5px">
                                    <i class="fas fa-circle-check" style="font-size:11px"></i>
                                    <span class="montserrat" style="font-size:11px; color:#2f2f2f">NIT:</span>
                                    <span class="montserrat-text" style="font-size:11px;">$nitCompania</span>
                                </div>
                                <div style="display:flex; align-items:center; gap:5px">
                                    <i class="fas fa-location-dot" style="font-size:11px"></i>
                                    <span class="montserrat-text" style="font-size:11px; margin-left:4px">$direccionCompania</span>
                                </div>
                            </div>
                        </div>
                        <div class="animate__animated animate__fadeIn" style="position:relative; width:fit-content; display:flex; align-items:center; justify-content:center; gap:5px; flex:4;">
                            <div style="display:flex; justify-content:center; flex-direction:column; gap:2px">
                                <div style="display:flex; align-items:center; gap:5px;">
                                    <i class="fas fa-info-circle" style="font-size:11px"></i>
                                    <span class="montserrat" style="font-size:13px;">$nombreInforme</span>
                                </div>
                                $periodo
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; justify-content:end; flex:4; position:relative">
                            <div class="menuReporteador" data-action="click->facturas--informes#showMenuReporteador" data-opc="0" style=
                                "
                                    width:0px; 
                                    height:0px; 
                                    display:flex; 
                                    padding:15px;
                                    cursor:pointer; 
                                    border-radius:50%;
                                    background:#d2d4da; 
                                    align-items:center; 
                                    justify-content:center; 
                                    transition:all 0.5s ease; 
                                ">
                                <i class="fas fa-bars" style="transition:all 0.5s ease; font-size:13px"></i>
                            </div>  
                            <div id="menuReporteador" transition-style="in:custom:circle-swoop" style=
                                "
                                    top:36px; 
                                    display:none; 
                                    width: 220px;
                                    position:absolute; 
                                    align-items:start; 
                                    padding: 15px 2px; 
                                    background: white; 
                                    border-radius: 5px; 
                                    flex-direction:column; 
                                    transition:all 0.5s ease; 
                                    border: 1px solid #d2d4da; 
                                ">
                                <div class="itemMenu" style="cursor:pointer; border-radius:3px; display:flex; align-items:center; gap:10px; padding:5px 15px; width:100%" data-action="click->facturas--informes#descargarPDF">
                                    <div style="width:16px">
                                        <i class="far fa-file-pdf text-danger" style="font-size:15px"></i>
                                    </div>
                                    <i class="fas fa-angle-double-right flecha text-danger" style="opacity:0; font-size:9px; transition:all 0.5s ease"></i>
                                    <span class="montserrat-text" style="font-size:12px; margin-left:-19px; transition:all 0.5s ease">Descargar PDF</span>
                                </div>
                                <div class="itemMenu" style="cursor:pointer; border-radius:3px; display:flex; align-items:center; gap:10px; padding:5px 15px; width:100%" data-action="click->facturas--informes#descargarExcel">
                                    <div style="width:16px">
                                        <i class="far fa-file-excel text-success" style="font-size:15px"></i>
                                    </div>
                                    <i class="fas fa-angle-double-right flecha text-success" style="opacity:0; font-size:9px; transition:all 0.5s ease"></i>
                                    <span class="montserrat-text" style="font-size:12px; margin-left:-19px; transition:all 0.5s ease">Descargar EXCEL</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="animate__animated animate__fadeInLeft" style="position:relative; margin-top:50px; margin-left:15px; width:fit-content; display:flex; align-items:center;">
                        <button id="btnBusquedaRapida" style="transition:all 0.5s ease; position:absolute; border-radius:50%; right:6px; background:#17A; color:white" class="btn btn-sm" data-action="facturas--informes#busquedaRapida" data-opc="1"><i class="fas fa-search" style="font-size:12px"></i></button>
                        <input id="busquedaRapida" class="form-control buscar montserrat-text" type="text" placeholder="Búsqueda rápida" data-facturas--informes-target="busquedaRapida" style=
                        "
                            width:220px; 
                            height:36px; 
                            font-size:12px; 
                            transition:all 0.5s ease;
                            padding:0px 53px 0px 19px;
                            border-radius:20px 20px 20px 5px; 
                        " data-action="keypress->facturas--informes#busquedaRapida" data-opc="2" value="$busquedaRapida">
                    </div>
                    <hr style="margin-left:15px; margin-right:15px">
                    <div class="animate__animated animate__fadeIn animate__delay-1s" style="margin-top:25px; padding:3px; overflow-y:auto; overflow-x:hidden; transition:all 0.5s ease">
                        <div style="display:flex; align-items:center; justify-content:center;">
                            $contenidoInforme
                        </div>
                    </div>
                </div>
            </div>
        </div>
        $contenidoPaginacion
        <input type="hidden" id="paginaHidden" value="$pagina" data-facturas--informes-target="paginaHidden">
        <input type="hidden" id="busquedaRapidaHidden" value="$busquedaRapida" data-facturas--informes-target="busquedaRapidaHidden">
        TWIG;
        return new Response(json_encode(['status' => $status, 'message' => $message, 'plantilla' => $plantilla]));
    }

    public function crearTablaRegistros(Request $request, $configuraciones, $listRegistros, $agrupacion = false)
    {   
        /** 
         * En esta función se crea la tabla principal del informe, la cual contiene todos los registros de la página seleccionada
         * ----------------------------------------------------------------------------------------------------------------------
         * @access public
        */

        /** Definición de variables */
        /** ----------------------- */

        $index = 1;
        $tdCampo = '';
        $cabecera = [];
        $trTotales = '';
        $thCabecera = '';
        $trCabecera = '';
        $divRelleno = '';
        $estiloBordes = '';
        $rellenoCampo = '';
        $filasInforme = '';
        $tablaTotales = [];
        $titulosInforme = '';
        $contenidoInforme = '';
        $camposTotalizacion = [];
        $tablaTotales['colspan'] = 0;
        $camposTotalizacionAgrupamiento = [];
        $camposTotalizados = $this->camposTotalizados;
        $ruta = $request->getScheme().'://'.$request->server->get('HTTP_HOST');
        $alineaciones = ['centro' => 'center', 'derecha' => 'right', 'izquierda' => 'left'];

        /** Se obtiene el json que contiene las configuraciones del informe */
        /** --------------------------------------------------------------- */

        if(!empty($configuraciones))
        {
            if(array_key_exists('campos', $configuraciones)){$configuracionCampos = $configuraciones['campos'];}
            if(array_key_exists('cabecera', $configuraciones) && is_array($configuraciones['cabecera']) && !empty($configuraciones['cabecera'])){$cabecera = $configuraciones['cabecera'];}
            if(array_key_exists('totalizacion', $configuraciones['agrupamiento'][0]) && !empty($configuraciones['agrupamiento'][0]['totalizacion']) && is_array($configuraciones['agrupamiento'][0]['totalizacion']))
            {
                $camposTotalizados = [];
                $camposTotalizacion = $configuraciones['agrupamiento'][0]['totalizacion'];
            }
        }

        /** Se obtiene la totalización de los campos */
        /** ---------------------------------------- */

        if($agrupacion)
        {
            foreach($listRegistros as $indexRegistro => $registro)
            {
                foreach($camposTotalizacion as $ct)
                {
                    if(array_key_exists('campo', $ct) && array_key_exists($ct['campo'], $registro))
                    {
                        if(array_key_exists($ct['campo'], $camposTotalizados))
                        {
                            $camposTotalizados[$ct['campo']] = $camposTotalizados[$ct['campo']] + $registro[$ct['campo']];
                        }
                        else
                        {
                            $camposTotalizados[$ct['campo']] = $registro[$ct['campo']];
                        }
                    }
                }
            }
        }

        /** Se genera la tabla de registros */
        /** ------------------------------- */
        
        foreach($listRegistros as $indexRegistro => $registro)
        {   
            $finColspan = false;
            $rellenoCampo = (($indexRegistro + 1) % 2 == 0) ? '#17A2B814' : '';
            foreach($registro as $key => $campo)
            {
                /** Se crean los títulos del informe con sus respectivos estilos */
                /** ------------------------------------------------------------ */
                
                $alineacionCampo = 'left';
                $alineacionTitulo = 'center';
                $titulo = ucfirst(str_replace('_', ' ', $key));
                $configuracionCampo = array_filter($configuracionCampos, fn($item) => $item['nombre'] == $key);

                /** Se validan las configuraciones de cada campo */
                /** -------------------------------------------- */

                sort($configuracionCampo);
                if(!empty($configuracionCampo))
                {
                    /** Configuraciones del título */
                    /** -------------------------- */

                    if(array_key_exists('titulo', $configuracionCampo[0])){$titulo = $configuracionCampo[0]['titulo'];}
                    if(array_key_exists('alineacionTitulo', $configuracionCampo[0]) && array_key_exists($configuracionCampo[0]['alineacionTitulo'], $alineaciones))
                    {
                        $alineacionTitulo = $alineaciones[$configuracionCampo[0]['alineacionTitulo']];
                    }

                    /** Configuraciones de campos */
                    /** ------------------------- */

                    if(array_key_exists('alineacionCampo', $configuracionCampo[0]) && array_key_exists($configuracionCampo[0]['alineacionCampo'], $alineaciones))
                    {
                        $alineacionCampo = $alineaciones[$configuracionCampo[0]['alineacionCampo']];
                    }

                    if(array_key_exists('tipoDato', $configuracionCampo[0]) && $configuracionCampo[0]['tipoDato'] == 'moneda')
                    {
                        $campo = number_format($campo, 2, ',', '.');
                    }

                    if(array_key_exists('tipoDato', $configuracionCampo[0]) && $configuracionCampo[0]['tipoDato'] == 'numero')
                    {
                        $campo = number_format($campo, 2, '.', '');
                    }

                    if(array_key_exists('tipoDato', $configuracionCampo[0]) && $configuracionCampo[0]['tipoDato'] == 'fecha')
                    {
                        $campo = (new \DateTime($campo))->format('Y-m-d');
                    }

                    if(array_key_exists('html', $configuracionCampo[0]) && !empty($configuracionCampo[0]['html']))
                    {
                        $html = $configuracionCampo[0]['html'];
                        if(is_array($html))
                        {
                            $valorCondicion = array_key_exists('valor', $html)?$html['valor']:'';
                            if(!empty($valorCondicion))
                            {
                                if($valorCondicion == $campo)
                                {
                                    $html = array_key_exists('si', $html)?$html['si']:$campo;
                                }
                                else
                                {
                                    $html = array_key_exists('no', $html)?$html['no']:$campo;
                                }
                            }
                            else
                            {
                                $html = $campo;
                            }
                        }
                        $html = str_replace('$campo', $campo, $html);

                        /** Se valida si el campo tiene una ruta configurada */
                        /** ------------------------------------------------ */

                        if(array_key_exists('ruta', $configuracionCampo[0]) && !empty($configuracionCampo[0]['ruta']))
                        {
                            /** Se valida si existen parámetros configurados */
                            /** -------------------------------------------- */

                            $parametros = [];
                            if(array_key_exists('parametros', $configuracionCampo[0]) && is_array($configuracionCampo[0]['parametros']) && !empty($configuracionCampo[0]['parametros']))
                            {
                                $parametros = str_replace('$campo', $campo, json_encode($configuracionCampo[0]['parametros']));
                                $parametros = json_decode($parametros, true);
                            }
                            $rutaCampo = $ruta.$this->generateUrl($configuracionCampo[0]['ruta'], $parametros);
                            $html = str_replace('$ruta', $rutaCampo, $html);
                        }
                        $campo = $html;
                    }
                }

                if($index == 1)
                {
                    $estiloBordesCampo = 'border-left:1px solid #d0d4da';
                    $claseTitulo = empty($cabecera)?'class="tituloInicial"':'';
                    $estiloBordesTitulo = empty($cabecera)?'border-radius:10px 0px 0px 3px':'';
                }

                if($index == count($registro))
                {
                    $estiloBordesCampo = 'border-right:1px solid #d0d4da';
                    $claseTitulo = empty($cabecera)?'class="tituloFinal"':'';
                    $estiloBordesTitulo = empty($cabecera)?'border-radius:0px 10px 3px 0px; border-right:1px solid #d0d4da':'border-right:1px solid #d0d4da';
                }

                /** Se crean los títulos del informe */
                /** -------------------------------- */

                if($indexRegistro == 0)
                {   
                    $titulosInforme .=
                    <<<TWIG
                    <th>
                        <div $claseTitulo style="transition:all 0.5s ease; background:#f8f9fa; display:flex; align-items:center; justify-content:$alineacionTitulo; padding:9px 10px 9px 12px; font-size:12px; border:1px solid #d0d4da; height:38px; border-right:none; $estiloBordesTitulo">
                            $titulo
                        </div>
                    </th>
                    TWIG;
                    $divRelleno = '';
                    $claseTitulo = '';
                    $estiloBordesTitulo = '';
                }

                /** Se crea cada registro del informe */
                /** --------------------------------- */

                $tdCampo .= 
                <<<TWIG
                <td style="padding:7px; font-size:12px; border-bottom:1px solid #E2E2E2; text-align:$alineacionCampo; $estiloBordesCampo">$campo</td>
                TWIG;

                /** Se diseña la tabla de acuerdo a los totales configurados */
                /** -------------------------------------------------------- */

                if($indexRegistro == array_key_last($listRegistros))
                {
                    if(array_key_exists($key, $camposTotalizados))
                    {
                        $finColspan = true;
                        $total = $camposTotalizados[$key];
                        if(!empty($configuracionCampo))
                        {
                            if(array_key_exists('tipoDato', $configuracionCampo[0]) && $configuracionCampo[0]['tipoDato'] == 'moneda')
                            {
                                $total = number_format($total, 2, ',', '.');
                            }

                            if(array_key_exists('tipoDato', $configuracionCampo[0]) && $configuracionCampo[0]['tipoDato'] == 'numero')
                            {
                                $total = number_format($total, 2, '.', '');
                            }

                            if(array_key_exists('alineacionCampo', $configuracionCampo[0]) && array_key_exists($configuracionCampo[0]['alineacionCampo'], $alineaciones))
                            {
                                $alineacionCampo = $alineaciones[$configuracionCampo[0]['alineacionCampo']];
                            }
                        }
                        $tablaTotales['campo'.$index] = [$total, $alineacionCampo];
                    }
                    else
                    {
                        if(!$finColspan)
                        {
                            $tablaTotales['colspan'] = $tablaTotales['colspan'] + 1;
                        }
                        else
                        {
                            $tablaTotales['campo'.$index] = '';
                        }
                    }

                    /** Se obtienen los títulos de los totales generales */
                    /** ------------------------------------------------ */

                    if(array_key_exists($key, $this->camposTotalizados))
                    {
                        if(!is_array($this->camposTotalizados[$key]))
                        {
                            $this->camposTotalizados[$key] = [$titulo, $this->camposTotalizados[$key]];
                        }
                    }
                }
                $estiloBordesCampo = '';
                $index ++;
            }
            $filasInforme .=
            <<<TWIG
                <tr style="background:$rellenoCampo">
                    $tdCampo
                </tr>
            TWIG;
            $tdCampo = '';
            $index = 1;
        }

        /** Se crea la sección de la cabecera */
        /** --------------------------------- */
        
        if(!empty($cabecera))
        {
            foreach($cabecera as $index => $c)
            {
                $tituloCabecera = $c['nombre'];
                $colSpanCabecera = $c['colspan'];

                if($index == 0)
                {
                    $estiloBordesTitulo = 'border-radius:10px 0px 0px 0px';
                }

                if($index == (count($cabecera) - 1))
                {
                    $estiloBordesTitulo = 'border-radius:0px 10px 0px 0px; border-right:1px solid #d0d4da';
                }

                if(count($cabecera) == 1)
                {
                    $estiloBordesTitulo = 'border-radius:10px 10px 0px 0px; border-right:1px solid #d0d4da';
                }

                $thCabecera .=
                <<<TWIG
                <th colspan="$colSpanCabecera">
                    <div style="transition:all 0.5s ease; background:#f8f9fa; display:flex; align-items:center; justify-content:center; padding:9px 10px 9px 12px; font-size:12px; border:1px solid #d0d4da; border-bottom:none; border-right:none; $estiloBordesTitulo">
                        $tituloCabecera
                    </div>
                </th>
                TWIG;
                $estiloBordes = '';
            }
            $trCabecera = 
            <<<TWIG
            <tr class="montserrat text-primary" style="position:sticky; top:-3px">
                $thCabecera
            </tr>
            TWIG;
        }

        /** Se crea la sección de totales */
        /** ----------------------------- */

        $index = 0;
        $tdTotal = '';
        if(!empty($camposTotalizados))
        {
            foreach($tablaTotales as $key => $campoTotal)
            {
                if($key == 'colspan' && $campoTotal > 0)
                {
                    $tdTotal .= 
                    <<<TWIG
                    <th colspan="$campoTotal">
                        <div style="background:#f8f9fa; display:flex; align-items:center; justify-content:right; padding:0px 10px 0px 12px; height:38px; font-size:12px; border:1px solid #d0d4da; border-right:none; border-top:none; border-radius:0px 0px 0px 10px">
                            <div>
                                <div class="ripple" style="position:relative">
                                    <i 
                                        style="position: absolute; top:1px; left:1px; font-size:12px" 
                                        class="fas fa-info-circle text-primary" 
                                    ></i>
                                </div>
                            </div>
                            <div style="display:flex; align-items:center; justify-content:center; gap:7px">
                                <span class="montserrat text-primary" style="margin-left:8px; font-size:12px; margin-top:1px">Totales</span>
                                <i class="fas fa-angle-double-right text-primary" style="font-size:10px; margin-top:1px"></i>
                            </div>
                        </div>
                    </th>
                    TWIG;
                }
                else
                {
                    $campo = !empty($campoTotal)?$campoTotal[0]:'';
                    $alineacionCampo = !empty($campoTotal)?$campoTotal[1]:'';
                    $estiloBordes = ($index == (count($tablaTotales) - 1))?'border-bottom:1px solid #d0d4da; border-right:1px solid #d0d4da; border-radius:0px 0px 10px 0px;':'border-bottom:1px solid #d0d4da; border-right:none';
                    $tdTotal .= 
                    <<<TWIG
                    <th>
                        <div style="background:#f8f9fa; display:flex; align-items:center; justify-content:$alineacionCampo; padding:0px 10px 0px 12px; height:38px; font-size:12px; $estiloBordes">
                            $campo
                        </div>
                    </th>
                    TWIG;
                }
                $index ++;
            }
            $trTotales = 
            <<<TWIG
                <tr>
                    $tdTotal
                </tr>
            TWIG;
        }

        /** Contenido del informe */
        /** --------------------- */

        $contenidoInforme =
        <<<TWIG
        <table border="0" cellpadding="0" cellspacing="0" class="mb-0" style="width:100%">
            $trCabecera
            <tr class="montserrat text-primary" style="position:sticky; top:-3px">
                $titulosInforme
            </tr>
            $filasInforme
            $trTotales
        </table>
        TWIG;
        return $contenidoInforme;
    }

    public function mostrarProducto($producto)
    {
        /** 
         * En esta función se genera la vista para observar los detalles de un producto
         * ----------------------------------------------------------------------------
         * @access public
        */

        $bd = $this->em;
        $producto = $bd->getRepository(Producto::class)->find($producto);
        return $this->render('Productos\mostrarProducto.html.twig', ['producto' => $producto]);
    }

    public function guardarFiltrosSesion(Request $request)
    {
        /** 
         * En esta función se guardan los filtros de búsqueda seleccionados como variables de sesión; 
         * de manera que, estos se puedan utilizar en distintas operaciones.
         * ------------------------------------------------------------------------------------------
         * @access public
        */

        $session = $request->getSession();
        $form = $request->request->get('filtros_informes');
        $form['busquedaRapida'] = $request->request->get('busquedaRapida');
        $session->set('filtrosInformes', $form);
        return new Response(json_encode(['status' => 'success']));
    }

    public function descargarInformePDF(Request $request)
    {
        /** 
         * En esta función se descarga el informe en formato PDF. Para ello, se emplea el sql configurado en el informe y se obtiene
         * la información respectiva a partir de los filtros de búsqueda seleccionados. Además, se genera la plantila html con las
         * especificaciones que se hayan definido para cada campo.
         * -------------------------------------------------------------------------------------------------------------------------
         * @access public
        */
        
        /** Definición de variables */
        /** ----------------------- */

        $index = 1;
        $filtros = [];
        $message = '';
        $periodo = '';
        $cabecera = [];
        $bd = $this->em;
        $plantilla = '';
        $keyCampos = [];
        set_time_limit(0);
        $tablaTotales = [];
        $agrupamiento = [];
        $contenidoPDF = '';
        $totalRegistros = 0;
        $camposAgrupacion = [];
        $configuracionesPDF = [];
        $camposTotalizacion = [];
        $camposPeriodoValido = [];
        $contenidoPaginacion = '';
        $configuracionCampos = [];
        $pdfOptions = new Options();
        $conexion = $bd->getConnection();
        $listRegistrosBusquedaRapida = [];
        $session = $request->getSession();
        $form = $session->get('filtrosInformes');
        $busquedaRapida = $form['busquedaRapida'];
        $compania = $bd->getRepository(compania::class)->findOneBy([]);
        $alineaciones = ['centro' => 'center', 'derecha' => 'right', 'izquierda' => 'left'];
        $informe = $bd->getRepository(Reporte::class)->findOneBy(['id' => $form['informe']]);
        $pdfOptions->set('defaultFont', 'Helvetica')->set('sizeFont', '9')->setIsRemoteEnabled(true);
        $fechaActual = (new \DateTime('now', new \DateTimeZone('America/Bogota')))->format('Y-m-d H:i:s');

        /** Se obtienen los filtros de búsqueda seleccionados */
        /** ------------------------------------------------- */

        $sqlInforme = $informe->getSql();
        $nitCompania = $compania->getNit();
        $logo = $compania->getLogocompania();
        $nombreInforme = $informe->getNombre();
        $pagina = $request->request->get('pagina');
        $telefonoCompania = $compania->getTelefonos();
        $direccionCompania = $compania->getDireccion();
        $nombreCompania = strtoupper($compania->getNombre());
        foreach($form as $key => $campo){$filtros['['.$key.']'] = !empty($campo)?$campo:-1;}
        $sqlInforme = strtr($sqlInforme, $filtros);

        /** Se obtiene el json que contiene las configuraciones del informe */
        /** --------------------------------------------------------------- */

        $tablaTotales['colspan'] = 0;
        $configuraciones = $informe->getJson();
        if(!empty($configuraciones))
        {
            if(array_key_exists('campos', $configuraciones)){$configuracionCampos = $configuraciones['campos'];}
            if(array_key_exists('pdf', $configuraciones) && !empty($configuraciones['pdf']) && is_array($configuraciones['pdf'])){$configuracionesPDF = $configuraciones['pdf'];}
            if(array_key_exists('paginacion', $configuraciones) && $configuraciones['paginacion'] && $configuraciones['paginacion'] >= 10){$paginacion = $configuraciones['paginacion'];}
            if(array_key_exists('cabecera', $configuraciones) && is_array($configuraciones['cabecera']) && !empty($configuraciones['cabecera'])){$cabecera = $configuraciones['cabecera'];}
            if(array_key_exists('agrupamiento', $configuraciones) && is_array($configuraciones['agrupamiento']) && !empty($configuraciones['agrupamiento'])){$agrupamiento = $configuraciones['agrupamiento'];}
            if(array_key_exists('totalizacion', $configuraciones) && !empty($configuraciones['totalizacion']) && is_array($configuraciones['totalizacion'])){$camposTotalizacion = $configuraciones['totalizacion'];}
            if(array_key_exists('periodo', $configuraciones) && !empty($configuraciones['periodo']))
            {
                preg_match_all('/\[(.*?)\]/', $configuraciones['periodo'], $campos);
                if(!empty($campos))
                {
                    foreach($campos[0] as $campo)
                    {
                        if(array_key_exists($campo, $filtros) && date('Y-m-d', strtotime($filtros[$campo])) == $filtros[$campo])
                        {
                            $fecha = explode('-', $filtros[$campo]);
                            $mes = $bd->getRepository(meses::class)->findOneBy(['numero' => $fecha[1]]);
                            $camposPeriodoValido[$campo] = $fecha[2].' de '.$mes->getNombre().' de '.$fecha[0];
                        }
                    }
                    if(count($camposPeriodoValido) == count($campos[0]))
                    {
                        $periodo = strtr($configuraciones['periodo'], $camposPeriodoValido);
                    }
                }
            }
        }

        /** Se realiza la consulta de los registros */
        /** --------------------------------------- */

        try 
        {
            $listRegistros = $conexion->prepare($sqlInforme)->executeQuery()->fetchAll();

            /** Se filtran los registros de acuerdo a la búsqueda rápida */
            /** -------------------------------------------------------- */

            if($busquedaRapida != '')
            {
                foreach($listRegistros as $registro)
                {
                    foreach($registro as $campo)
                    {
                        if(strpos($campo, $busquedaRapida) !== false)
                        {
                            $listRegistrosBusquedaRapida[] = $registro;
                        }
                    }
                }
                $listRegistros = $listRegistrosBusquedaRapida;
            }

            /** Se obtiene la totalización de los campos */
            /** ---------------------------------------- */

            $totalRegistros = count($listRegistros);
            foreach($listRegistros as $indexRegistro => $registro)
            {
                /** Se obtiene la totalización de los campos */
                /** ---------------------------------------- */

                foreach($camposTotalizacion as $ct)
                {
                    if(array_key_exists('campo', $ct) && array_key_exists($ct['campo'], $registro))
                    {
                        if(array_key_exists($ct['campo'], $this->camposTotalizados))
                        {
                            $this->camposTotalizados[$ct['campo']] = $this->camposTotalizados[$ct['campo']] + $registro[$ct['campo']];
                        }
                        else
                        {
                            $this->camposTotalizados[$ct['campo']] = $registro[$ct['campo']];
                        }
                    }
                }
            }

            /** Se valida si existen campos de agrupación configurados */
            /** ------------------------------------------------------ */

            if(array_key_exists('campos', $agrupamiento[0]) && is_array($agrupamiento[0]['campos']) && !empty($agrupamiento[0]['campos']))
            {
                $keyCampos = array_keys($listRegistros[0]);
                foreach($keyCampos as $campo)
                {
                    foreach($agrupamiento[0]['campos'] as $a)
                    {
                        if($a['nombre'] == $campo){$camposAgrupacion[] = $campo;}
                    }
                }
            }

            if(!empty($camposAgrupacion))
            {
                /** Se genera el informe con campos de agrupación */
                /** --------------------------------------------- */
                
                $listAgrupada = [];
                $campoControl = '';
                $campoAnterior = '';
                $camposReferencia = [];
                $divTotalesGenerales = '';
                
                /** Se ordena la información de acuerdo a los campos de agrupación configurados en el informe */
                /** ----------------------------------------------------------------------------------------- */
        
                foreach($camposAgrupacion as $index => $campo)
                {
                    if(empty($campoControl))
                    {
                        foreach($listRegistros as $registro)
                        {
                            $listAgrupada[$campo][$registro[$campo]] = $registro[$campo];
                        }
                    }
                    else
                    {
                        if($index == 1)
                        {
                            foreach($listAgrupada[$campoControl] as $c)
                            {
                                $camposReferencia[] = $c;
                                foreach($listRegistros as $registro)
                                {
                                    if($registro[$campoControl] == $c)
                                    {
                                        $listAgrupada[$campo][$c][$registro[$campo]] = $registro[$campo];
                                    }
                                }
                            }
                            unset($camposReferencia[array_key_last($camposReferencia)]);
                        }
                        else
                        {
                            foreach($camposReferencia as $cr)
                            {
                                foreach($listAgrupada[$campoControl][$cr] as $c)
                                {
                                    $camposReferenciaControl[] = $c;
                                    foreach($listRegistros as $registro)
                                    {
                                        if($registro[$campoControl] == $c)
                                        {
                                            $listAgrupada[$campo][$c][$registro[$campo]] = $registro[$campo];
                                        }
                                    }
                                }
                            }
                            $camposReferencia = $camposReferenciaControl;
                        }
                    }
                    $campoControl = $campo;
                    $listAgrupada[$campo]['referencia'] = array_key_exists($index + 1, $camposAgrupacion)?$camposAgrupacion[$index + 1]:'registros';
        
                    /** Se guardan los registros de tal manera que se asocien al último nivel de agrupación */
                    /** ----------------------------------------------------------------------------------- */
        
                    if($listAgrupada[$campo]['referencia'] == 'registros')
                    {
                        if(empty($camposReferencia))
                        {
                            $campoAnterior = $camposAgrupacion[0];
                            foreach($listAgrupada[$campoAnterior] as $c)
                            {
                                foreach($listRegistros as $registro)
                                {
                                    if($registro[$campoAnterior] == $c)
                                    {
                                        foreach($camposAgrupacion as $campo){unset($registro[$campo]);}
                                        $listAgrupada['registros'][$c][] = $registro;
                                    }
                                }
                            }
                        }
                        else
                        {
                            foreach($camposReferencia as $cr)
                            {
                                foreach($listAgrupada[$campoControl][$cr] as $c)
                                {
                                    foreach($listRegistros as $registro)
                                    {
                                        $campoAnterior = $registro[$camposAgrupacion[count($camposAgrupacion) - 2]];
                                        if($campoAnterior == $cr && $registro[$campoControl] == $c)
                                        {
                                            foreach($camposAgrupacion as $campo){unset($registro[$campo]);}
                                            $listAgrupada['registros'][$campoAnterior.$c][] = $registro;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                /** Se crea la sección de agrupamiento con todos los campos seleccionados */
                /** --------------------------------------------------------------------- */

                $index = 0;
                $indexFila = 0;
                $divAgrupacion = '';
                $registrosAgrupados = [];
                $divAgrupacionGeneral = '';
                $divRegistrosAgrupacion = '';
                foreach($listAgrupada as $key => $lista)
                {
                    if($key == 'registros'){break;}
                    $campo = array_filter($agrupamiento[0]['campos'], fn($item) => $item['nombre'] == $key);
                    sort($campo);
                    $titulo = $campo[0]['titulo'];
                    foreach($lista as $keyFila => $items)
                    {
                        if($keyFila == 'referencia'){continue;}
                        $keyFila = str_replace(' ', '_', $keyFila);
                        $marginTopFila = ($indexFila == 0)?'':'margin-top:3px;';
                        if($index == 0)
                        {
                            $registrosAgrupados[] = $keyFila; 
                            $nombreAgrupacion = explode('-', $items);
                            if(count($nombreAgrupacion) > 1)
                            {
                                unset($nombreAgrupacion[0]);
                                $nombreAgrupacion = implode('-', $nombreAgrupacion);
                            }
                            $divAgrupacionGeneral .=
                            <<<TWIG
                            <div style=
                            "
                                $marginTopFila
                                padding:12px 17px; 
                                background:#f2f2f2;
                                border:1px solid gray; 
                                border-radius:5px 5px 0px 0px; 
                            ">
                                <table border="0" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <th>$titulo</th>
                                        <th style="padding-left:5px; padding-right:5px">»</th>
                                        <td>$nombreAgrupacion</td>
                                    </tr>
                                </table>
                            </div>
                            <div style="border: 1px solid gray; padding:10px; border-radius: 0px 0px 5px 5px; margin-top:-1px;">
                                replace_$keyFila
                            </div>
                            TWIG;
                        }
                        else
                        {
                            foreach($items as $keyItem => $item)
                            {
                                $keyItem = str_replace(' ', '_', $keyItem);
                                if($index == (count($listAgrupada) - 2))
                                {
                                    $keyItem = $keyFila.str_replace(' ', '_', $keyItem);
                                    $registrosAgrupados[] = $keyItem; 
                                }
                                $nombreAgrupacion = explode('-', $item);
                                if(count($nombreAgrupacion) > 1)
                                {
                                    unset($nombreAgrupacion[0]);
                                    $nombreAgrupacion = implode('-', $nombreAgrupacion);
                                }
                                $divAgrupacion .=
                                <<<TWIG
                                <div style=
                                "
                                    margin-top:3px;
                                    padding:12px 17px;
                                    background:#f2f2f2;
                                    border:1px solid gray; 
                                    border-radius:5px 5px 0px 0px; 
                                ">
                                    <table border="0" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <th>$titulo</th>
                                            <th style="padding-left:5px; padding-right:5px">»</th>
                                            <td>$nombreAgrupacion</td>
                                        </tr>
                                    </table>
                                </div>
                                <div style="border: 1px solid gray; padding:10px; border-radius: 0px 0px 5px 5px; margin-top:-1px;">
                                    <div>
                                        replace_$keyItem
                                    </div>
                                </div>
                                TWIG;
                            }
                            $divAgrupacionGeneral = str_replace('replace_'.$keyFila, $divAgrupacion, $divAgrupacionGeneral);
                            $divAgrupacion = '';

                        }
                        
                        /** Se agrega a los items del último campo de agrupación los registros correspondientes */
                        /** ----------------------------------------------------------------------------------- */

                        if($index == (count($listAgrupada) - 2))
                        {
                            foreach($registrosAgrupados as $indexAgrupacion => $registros)
                            {
                                if(array_key_exists(str_replace('_', ' ', $registros), $listAgrupada['registros']))
                                {
                                    $divAgrupacion = $this->crearTablaRegistrosPDF($request, $configuraciones, $listAgrupada['registros'][str_replace('_', ' ', $registros)], true);
                                    $divAgrupacionGeneral = str_replace('replace_'.$registros, $divAgrupacion, $divAgrupacionGeneral);
                                }
                            }
                        }
                        $indexFila ++;
                        $divAgrupacion = '';
                    }
                    $index ++;
                }

                /** Se genera la sección de totales obtenidos a partir de los campos de agrupación */
                /** ------------------------------------------------------------------------------ */

                if(!empty($this->camposTotalizados))
                {
                    foreach($this->camposTotalizados as $ct)
                    {
                        $tituloTotal = $ct[0];
                        $valorTotal = number_format($ct[1], 2, ',', '.');
                        $divTotalesGenerales .= 
                        <<<TWIG
                            <tr>
                                <td style="text-align:center; padding:5px 7px">
                                    $tituloTotal
                                </td>
                                <td style="text-align:right; padding:5px 7px">
                                    $valorTotal
                                </td>
                            </tr>
                        </div>
                        TWIG;
                    }
                    $divTotalesGenerales =
                    <<<TWIG
                    <div style="margin-top:20px;">
                        <div style="background:#f2f2f2; text-align:center; font-weight:bold; padding:7px; border:1px solid gray; border-bottom:none; border-radius:5px 5px 0px 0px;">
                            TOTALES DEL INFORME
                        </div>
                        <table border="1" cellpadding="0" cellspacing="0" style="width:100%">
                            $divTotalesGenerales
                        </table>
                    </div>
                    TWIG;
                }
                $contenidoPDF = 
                <<<TWIG
                <div style="width:100%">
                    $divAgrupacionGeneral
                    $divTotalesGenerales
                </div>
                TWIG;
            }
            else
            {
                /** Se genera el informe sin campos de agrupacion */
                /** --------------------------------------------- */
                
                $contenidoPDF = $this->crearTablaRegistrosPDF($request, $configuraciones, $listRegistros);
            }
        } 
        catch(\Exception $e) 
        {
            $status = 'error';
            $message = $e->getMessage().' - '.$e->getFile().' - '.$e->getLine();    
        }

        /** Se asignan las configuraciones del PDF */
        /** -------------------------------------- */

        $tipoHoja = 'letter';
        $orientacion = 'portrait';
        $anchoInformacionEmpresa = '280px';
        if(!empty($configuracionesPDF))
        {
            if(array_key_exists('tipoHoja', $configuracionesPDF) && !empty($configuracionesPDF['tipoHoja'])){$tipoHoja = $configuracionesPDF['tipoHoja'];}
            if(array_key_exists('orientacion', $configuracionesPDF) && !empty($configuracionesPDF['orientacion'])){$orientacion = $configuracionesPDF['orientacion'];}
            if($orientacion == 'landscape'){$anchoInformacionEmpresa = '400px';}
        }

        /** Se genera la plantilla del informe */
        /** ---------------------------------- */

        $dompdf = new Dompdf($pdfOptions);
        $html =
        <<<TWIG
        <style>
            * {
                font-size: 10px;
            }

            @page {
                margin: 220px 20px 40px 20px;
            }

            header {
                position: fixed;
                top: -160px;
                left: 0px;
                right: 0px;
                height: 50px;
            }
        </style>
        <body>
            <header>
                <table border="0" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="width:70px">
                            <img src="data:application/image;base64,$logo" style="width:70px">
                        </td>
                        <td style="padding-left:15px; width:$anchoInformacionEmpresa">
                            <div style="font-weight:bold; font-size:14px">$nombreCompania</div>
                            <div style="margin-top:2px">N.I.T: $nitCompania</div>
                            <div style="margin-top:2px">Dirección: $direccionCompania</div>
                            <div style="margin-top:2px">Teléfono: $telefonoCompania</div>
                        </td>
                        <td style="padding-left:10px">
                            <table border="0" style="width:100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td>
                                        <div style="font-weight:bold; background:#f2f2f2; border-radius:5px 0px 0px 0px; padding:5px 7px; border:1px solid gray; border-right:none">Informe</div>
                                    </td>
                                    <td>
                                        <div style="background:#f2f2f2; border-radius:0px 5px 0px 0px; padding:5px 7px; border:1px solid gray">$nombreInforme</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div style="font-weight:bold; padding:5px 7px; border:1px solid gray; border-right:none; border-top:none">Periodo</div>
                                    </td>
                                    <td>
                                        <div style="; padding:5px 7px; border:1px solid gray; border-top:none">$periodo</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div style="font-weight:bold; background:#f2f2f2; border-radius:0px 0px 0px 5px; padding:5px 7px; border:1px solid gray; border-top:none; border-right:none">Fecha imprime</div>
                                    </td>
                                    <td>
                                        <div style="background:#f2f2f2; border-radius:0px 0px 5px 0px; padding:5px 7px; border:1px solid gray; border-top:none;">$fechaActual</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </header>
            <div>
                $contenidoPDF
            </div>
        </body>
        TWIG;

        /** Se genera el PDF del informe */
        /** ---------------------------- */

        $dompdf->loadHtml($html);
        $dompdf->setPaper($tipoHoja, $orientacion);
        $dompdf->render();
        $nombreInforme = strtolower(str_replace(' ', '_', $nombreInforme));
        $dompdf->get_canvas()->page_text(282, 766, "Pagina: {PAGE_NUM} de {PAGE_COUNT}", 'Helvetica', 6, array(0, 0, 0));
        $pdf = base64_encode($dompdf->output());
        $dompdf->stream($nombreInforme.'.pdf', ['Attachment' => true]);
    }

    public function crearTablaRegistrosPDF(Request $request, $configuraciones, $listRegistros, $agrupacion = false)
    {   
        /** 
         * En esta función se crea la tabla del PDF con todos los registros del informe
         * ----------------------------------------------------------------------------
         * @access public
        */

        /** Definición de variables */
        /** ----------------------- */

        $index = 1;
        $tdCampo = '';
        $cabecera = [];
        $filasPDF = '';
        $trTotales = '';
        $thCabecera = '';
        $trCabecera = '';
        $titulosPDF = '';
        $divRelleno = '';
        $estiloBordes = '';
        $rellenoCampo = '';
        $contenidoPDF = '';
        $tablaTotales = [];
        $camposTotalizacion = [];
        $tablaTotales['colspan'] = 0;
        $camposTotalizacionAgrupamiento = [];
        $camposTotalizados = $this->camposTotalizados;
        $ruta = $request->getScheme().'://'.$request->server->get('HTTP_HOST');
        $alineaciones = ['centro' => 'center', 'derecha' => 'right', 'izquierda' => 'left'];

        /** Se obtiene el json que contiene las configuraciones del informe */
        /** --------------------------------------------------------------- */

        if(!empty($configuraciones))
        {
            if(array_key_exists('campos', $configuraciones)){$configuracionCampos = $configuraciones['campos'];}
            if(array_key_exists('cabecera', $configuraciones) && is_array($configuraciones['cabecera']) && !empty($configuraciones['cabecera'])){$cabecera = $configuraciones['cabecera'];}
            if(array_key_exists('totalizacion', $configuraciones['agrupamiento'][0]) && !empty($configuraciones['agrupamiento'][0]['totalizacion']) && is_array($configuraciones['agrupamiento'][0]['totalizacion']))
            {
                $camposTotalizados = [];
                $camposTotalizacion = $configuraciones['agrupamiento'][0]['totalizacion'];
            }
        }

        /** Se obtiene la totalización de los campos */
        /** ---------------------------------------- */

        if($agrupacion)
        {
            foreach($listRegistros as $indexRegistro => $registro)
            {
                foreach($camposTotalizacion as $ct)
                {
                    if(array_key_exists('campo', $ct) && array_key_exists($ct['campo'], $registro))
                    {
                        if(array_key_exists($ct['campo'], $camposTotalizados))
                        {
                            $camposTotalizados[$ct['campo']] = $camposTotalizados[$ct['campo']] + $registro[$ct['campo']];
                        }
                        else
                        {
                            $camposTotalizados[$ct['campo']] = $registro[$ct['campo']];
                        }
                    }
                }
            }
        }

        /** Se genera la tabla de registros */
        /** ------------------------------- */
        
        foreach($listRegistros as $indexRegistro => $registro)
        {   
            $finColspan = false;
            $rellenoCampo = (($indexRegistro + 1) % 2 == 0) ? '#17A2B814' : '';
            foreach($registro as $key => $campo)
            {
                /** Se crean los títulos del informe con sus respectivos estilos */
                /** ------------------------------------------------------------ */
                
                $alineacionCampo = 'left';
                $alineacionTitulo = 'center';
                $titulo = ucfirst(str_replace('_', ' ', $key));
                $configuracionCampo = array_filter($configuracionCampos, fn($item) => $item['nombre'] == $key);

                /** Se validan las configuraciones de cada campo */
                /** -------------------------------------------- */

                sort($configuracionCampo);
                if(!empty($configuracionCampo))
                {
                    /** Configuraciones del título */
                    /** -------------------------- */

                    if(array_key_exists('titulo', $configuracionCampo[0])){$titulo = $configuracionCampo[0]['titulo'];}
                    if(array_key_exists('alineacionTitulo', $configuracionCampo[0]) && array_key_exists($configuracionCampo[0]['alineacionTitulo'], $alineaciones))
                    {
                        $alineacionTitulo = $alineaciones[$configuracionCampo[0]['alineacionTitulo']];
                    }

                    /** Configuraciones de campos */
                    /** ------------------------- */

                    if(array_key_exists('alineacionCampo', $configuracionCampo[0]) && array_key_exists($configuracionCampo[0]['alineacionCampo'], $alineaciones))
                    {
                        $alineacionCampo = $alineaciones[$configuracionCampo[0]['alineacionCampo']];
                    }

                    if(array_key_exists('tipoDato', $configuracionCampo[0]) && $configuracionCampo[0]['tipoDato'] == 'moneda')
                    {
                        $campo = number_format($campo, 2, ',', '.');
                    }

                    if(array_key_exists('tipoDato', $configuracionCampo[0]) && $configuracionCampo[0]['tipoDato'] == 'numero')
                    {
                        $campo = number_format($campo, 2, '.', '');
                    }

                    if(array_key_exists('tipoDato', $configuracionCampo[0]) && $configuracionCampo[0]['tipoDato'] == 'fecha')
                    {
                        $campo = (new \DateTime($campo))->format('Y-m-d');
                    }

                    /** Se valida si el campo tiene una ruta configurada */
                    /** ------------------------------------------------ */

                    if(array_key_exists('ruta', $configuracionCampo[0]) && !empty($configuracionCampo[0]['ruta']))
                    {
                        $parametros = [];
                        $alineacionCampo = 'center';
                        if(array_key_exists('parametros', $configuracionCampo[0]) && is_array($configuracionCampo[0]['parametros']) && !empty($configuracionCampo[0]['parametros']))
                        {
                            $parametros = str_replace('$campo', $campo, json_encode($configuracionCampo[0]['parametros']));
                            $parametros = json_decode($parametros, true);
                        }
                        $rutaCampo = $ruta.$this->generateUrl($configuracionCampo[0]['ruta'], $parametros);
                        $campo = 
                        <<<TWIG
                            <a href="$rutaCampo" target="_blank" style="color:#007BFF; text-decoration:none">$campo</a>
                        TWIG;
                    }
                }

                /** Se crean los títulos del informe */
                /** -------------------------------- */

                if($indexRegistro == 0)
                {   
                    $titulosPDF .=
                    <<<TWIG
                    <td style="font-weight:bold; padding:4px; text-align:$alineacionTitulo; background:#f2f2f2; border:1px solid gray">$titulo</td>
                    TWIG;
                    $divRelleno = '';
                    $claseTitulo = '';
                    $estiloBordesTitulo = '';
                }

                /** Se crea cada registro del informe */
                /** --------------------------------- */

                $tdCampo .= 
                <<<TWIG
                <td style="padding:3px 7px; border:1px solid gray; text-align:$alineacionCampo">$campo</td>
                TWIG;

                /** Se diseña la tabla de acuerdo a los totales configurados */
                /** -------------------------------------------------------- */

                if($indexRegistro == array_key_last($listRegistros))
                {
                    if(array_key_exists($key, $camposTotalizados))
                    {
                        $finColspan = true;
                        $total = $camposTotalizados[$key];
                        if(!empty($configuracionCampo))
                        {
                            if(array_key_exists('tipoDato', $configuracionCampo[0]) && $configuracionCampo[0]['tipoDato'] == 'moneda')
                            {
                                $total = number_format($total, 2, ',', '.');
                            }

                            if(array_key_exists('tipoDato', $configuracionCampo[0]) && $configuracionCampo[0]['tipoDato'] == 'numero')
                            {
                                $total = number_format($total, 2, '.', '');
                            }

                            if(array_key_exists('alineacionCampo', $configuracionCampo[0]) && array_key_exists($configuracionCampo[0]['alineacionCampo'], $alineaciones))
                            {
                                $alineacionCampo = $alineaciones[$configuracionCampo[0]['alineacionCampo']];
                            }
                        }
                        $tablaTotales['campo'.$index] = [$total, $alineacionCampo];
                    }
                    else
                    {
                        if(!$finColspan)
                        {
                            $tablaTotales['colspan'] = $tablaTotales['colspan'] + 1;
                        }
                        else
                        {
                            $tablaTotales['campo'.$index] = '';
                        }
                    }

                    /** Se obtienen los títulos de los totales generales */
                    /** ------------------------------------------------ */

                    if(array_key_exists($key, $this->camposTotalizados))
                    {
                        if(!is_array($this->camposTotalizados[$key]))
                        {
                            $this->camposTotalizados[$key] = [$titulo, $this->camposTotalizados[$key]];
                        }
                    }
                }
                $estiloBordesCampo = '';
                $index ++;
            }
            $filasPDF .=
            <<<TWIG
                <tr>
                    $tdCampo
                </tr>
            TWIG;
            $tdCampo = '';
            $index = 1;
        }

        /** Se crea la sección de la cabecera */
        /** --------------------------------- */
        
        if(!empty($cabecera))
        {
            foreach($cabecera as $index => $c)
            {
                $colSpanCabecera = $c['colspan'];
                $tituloCabecera = strip_tags($c['nombre']);

                if($index == 0)
                {
                    $estiloBordesTitulo = 'border-radius:5px 0px 0px 0px';
                }

                if($index == (count($cabecera) - 1))
                {
                    $estiloBordesTitulo = 'border-radius:0px 5px 0px 0px; border-right:1px solid gray';
                }

                if(count($cabecera) == 1)
                {
                    $estiloBordesTitulo = 'border-radius:5px 5px 0px 0px; border-right:1px solid gray';
                }

                $thCabecera .=
                <<<TWIG
                <th colspan="$colSpanCabecera">
                    <div style="background:#f2f2f2; text-align:center; padding:7px; border:1px solid gray; border-bottom:none; border-right:none; $estiloBordesTitulo">
                        $tituloCabecera
                    </div>
                </th>
                TWIG;
                $estiloBordes = '';
            }
            $trCabecera = 
            <<<TWIG
            <tr>
                $thCabecera
            </tr>
            TWIG;
        }

        /** Se crea la sección de totales */
        /** ----------------------------- */

        $index = 0;
        $tdTotal = '';
        if(!empty($camposTotalizados))
        {
            foreach($tablaTotales as $key => $campoTotal)
            {
                if($key == 'colspan' && $campoTotal > 0)
                {
                    $tdTotal .= 
                    <<<TWIG
                    <th colspan="$campoTotal">
                        <div style="background:#f2f2f2; text-align:right; padding:7px; border:1px solid gray; border-right:1px solid #f2f2f2; border-top:none; border-radius:0px 0px 0px 5px">
                            Total &raquo;
                        </div>
                    </th>
                    TWIG;
                }
                else
                {
                    $campo = !empty($campoTotal)?$campoTotal[0]:'';
                    $alineacionCampo = !empty($campoTotal)?$campoTotal[1]:'';
                    $estiloBordes = ($index == (count($tablaTotales) - 1))?'border-bottom:1px solid gray; border-right:1px solid gray; border-radius:0px 0px 5px 0px;':'border-bottom:1px solid gray; border-right:1px solid #f2f2f2';
                    $tdTotal .= 
                    <<<TWIG
                    <th>
                        <div style="background:#f2f2f2; text-align:$alineacionCampo; padding:7px; height:12px; $estiloBordes">
                            $campo
                        </div>
                    </th>
                    TWIG;
                }
                $index ++;
            }
            $trTotales = 
            <<<TWIG
                <tr>
                    $tdTotal
                </tr>
            TWIG;
        }

        /** Contenido del PDF */
        /** ----------------- */

        $contenidoPDF =
        <<<TWIG
        <table border="0" cellpadding="0" cellspacing="0" style="width:100%">
            $trCabecera
            <tr>
                $titulosPDF
            </tr>
            $filasPDF
            $trTotales
        </table>
        TWIG;
        return $contenidoPDF;
    }

    public function descargarInformeExcel(Request $request)
    {
        /** 
         * En esta función se descarga el informe en formato excel. Para ello, se emplea el sql configurado en el informe y se obtiene
         * la información respectiva a partir de los filtros de búsqueda seleccionados. Además, se genera la plantila html con las
         * especificaciones que se hayan definido para cada campo.
         * ---------------------------------------------------------------------------------------------------------------------------
         * @access public
        */
        
        /** Definición de variables */
        /** ----------------------- */

        $index = 1;
        $filtros = [];
        $message = '';
        $periodo = '';
        $cabecera = [];
        $bd = $this->em;
        $plantilla = '';
        $keyCampos = [];
        set_time_limit(0);
        $tablaTotales = [];
        $agrupamiento = [];
        $contenidoPDF = '';
        $totalRegistros = 0;
        $nit = new RichText();
        $camposAgrupacion = [];
        $configuracionesPDF = [];
        $camposTotalizacion = [];
        $camposPeriodoValido = [];
        $contenidoPaginacion = '';
        $configuracionCampos = [];
        $telefono = new RichText();
        $direccion = new RichText();
        $fsObject = new Filesystem();
        $spreadsheet = new Spreadsheet();
        $conexion = $bd->getConnection();
        $listRegistrosBusquedaRapida = [];
        $session = $request->getSession();
        $sheet = $spreadsheet->getActiveSheet();
        $form = $session->get('filtrosInformes');
        $busquedaRapida = $form['busquedaRapida'];
        $logoTmp = 'logo_tmp_'.date('his').'.png';
        $rutaLogo = $this->getParameter('imgs_directory');
        $compania = $bd->getRepository(compania::class)->findOneBy([]);
        $alineaciones = ['centro' => 'center', 'derecha' => 'right', 'izquierda' => 'left'];
        $informe = $bd->getRepository(Reporte::class)->findOneBy(['id' => $form['informe']]);
        $fechaActual = (new \DateTime('now', new \DateTimeZone('America/Bogota')))->format('Y-m-d H:i:s');

        /** Se obtienen los filtros de búsqueda seleccionados */
        /** ------------------------------------------------- */
        
        $sqlInforme = $informe->getSql();
        $nitCompania = $compania->getNit();
        $logo = $compania->getLogocompania();
        $nombreInforme = $informe->getNombre();
        $pagina = $request->request->get('pagina');
        $telefonoCompania = $compania->getTelefonos();
        $direccionCompania = $compania->getDireccion();
        $nombreCompania = strtoupper($compania->getNombre());
        $logoCompania = base64_decode($compania->getLogocompania());
        foreach($form as $key => $campo){$filtros['['.$key.']'] = !empty($campo)?$campo:-1;}
        file_put_contents($rutaLogo.$logoTmp, $logoCompania);
        $sqlInforme = strtr($sqlInforme, $filtros);

        /** Se obtiene el json que contiene las configuraciones del informe */
        /** --------------------------------------------------------------- */

        $tablaTotales['colspan'] = 0;
        $configuraciones = $informe->getJson();
        if(!empty($configuraciones))
        {
            if(array_key_exists('campos', $configuraciones)){$configuracionCampos = $configuraciones['campos'];}
            if(array_key_exists('pdf', $configuraciones) && !empty($configuraciones['pdf']) && is_array($configuraciones['pdf'])){$configuracionesPDF = $configuraciones['pdf'];}
            if(array_key_exists('paginacion', $configuraciones) && $configuraciones['paginacion'] && $configuraciones['paginacion'] >= 10){$paginacion = $configuraciones['paginacion'];}
            if(array_key_exists('cabecera', $configuraciones) && is_array($configuraciones['cabecera']) && !empty($configuraciones['cabecera'])){$cabecera = $configuraciones['cabecera'];}
            if(array_key_exists('agrupamiento', $configuraciones) && is_array($configuraciones['agrupamiento']) && !empty($configuraciones['agrupamiento'])){$agrupamiento = $configuraciones['agrupamiento'];}
            if(array_key_exists('totalizacion', $configuraciones) && !empty($configuraciones['totalizacion']) && is_array($configuraciones['totalizacion'])){$camposTotalizacion = $configuraciones['totalizacion'];}
            if(array_key_exists('periodo', $configuraciones) && !empty($configuraciones['periodo']))
            {
                preg_match_all('/\[(.*?)\]/', $configuraciones['periodo'], $campos);
                if(!empty($campos))
                {
                    foreach($campos[0] as $campo)
                    {
                        if(array_key_exists($campo, $filtros) && date('Y-m-d', strtotime($filtros[$campo])) == $filtros[$campo])
                        {
                            $fecha = explode('-', $filtros[$campo]);
                            $mes = $bd->getRepository(meses::class)->findOneBy(['numero' => $fecha[1]]);
                            $camposPeriodoValido[$campo] = $fecha[2].' de '.$mes->getNombre().' de '.$fecha[0];
                        }
                    }
                    if(count($camposPeriodoValido) == count($campos[0]))
                    {
                        $periodo = strtr($configuraciones['periodo'], $camposPeriodoValido);
                    }
                }
            }
        }

        /** Se realiza la consulta de los registros */
        /** --------------------------------------- */

        try 
        {
            $listRegistros = $conexion->prepare($sqlInforme)->executeQuery()->fetchAll();

            /** Se filtran los registros de acuerdo a la búsqueda rápida */
            /** -------------------------------------------------------- */

            if($busquedaRapida != '')
            {
                foreach($listRegistros as $registro)
                {
                    foreach($registro as $campo)
                    {
                        if(strpos($campo, $busquedaRapida) !== false)
                        {
                            $listRegistrosBusquedaRapida[] = $registro;
                        }
                    }
                }
                $listRegistros = $listRegistrosBusquedaRapida;
            }

            /** Se obtiene la totalización de los campos */
            /** ---------------------------------------- */

            $totalRegistros = count($listRegistros);
            foreach($listRegistros as $indexRegistro => $registro)
            {
                /** Se obtiene la totalización de los campos */
                /** ---------------------------------------- */

                foreach($camposTotalizacion as $ct)
                {
                    if(array_key_exists('campo', $ct) && array_key_exists($ct['campo'], $registro))
                    {
                        if(array_key_exists($ct['campo'], $this->camposTotalizados))
                        {
                            $this->camposTotalizados[$ct['campo']] = $this->camposTotalizados[$ct['campo']] + $registro[$ct['campo']];
                        }
                        else
                        {
                            $this->camposTotalizados[$ct['campo']] = $registro[$ct['campo']];
                        }
                    }
                }
            }

            /** Se valida si existen campos de agrupación configurados */
            /** ------------------------------------------------------ */

            if(array_key_exists('campos', $agrupamiento[0]) && is_array($agrupamiento[0]['campos']) && !empty($agrupamiento[0]['campos']))
            {
                $keyCampos = array_keys($listRegistros[0]);
                foreach($keyCampos as $campo)
                {
                    foreach($agrupamiento[0]['campos'] as $a)
                    {
                        if($a['nombre'] == $campo){$camposAgrupacion[] = $campo;}
                    }
                }
            }

            if(!empty($camposAgrupacion))
            {
                /** Se genera el informe con campos de agrupación */
                /** --------------------------------------------- */
                
                $listAgrupada = [];
                $campoControl = '';
                $campoAnterior = '';
                $camposReferencia = [];
                $divTotalesGenerales = '';
                
                /** Se ordena la información de acuerdo a los campos de agrupación configurados en el informe */
                /** ----------------------------------------------------------------------------------------- */
        
                foreach($camposAgrupacion as $index => $campo)
                {
                    if(empty($campoControl))
                    {
                        foreach($listRegistros as $registro)
                        {
                            $listAgrupada[$campo][$registro[$campo]] = $registro[$campo];
                        }
                    }
                    else
                    {
                        if($index == 1)
                        {
                            foreach($listAgrupada[$campoControl] as $c)
                            {
                                $camposReferencia[] = $c;
                                foreach($listRegistros as $registro)
                                {
                                    if($registro[$campoControl] == $c)
                                    {
                                        $listAgrupada[$campo][$c][$registro[$campo]] = $registro[$campo];
                                    }
                                }
                            }
                            unset($camposReferencia[array_key_last($camposReferencia)]);
                        }
                        else
                        {
                            foreach($camposReferencia as $cr)
                            {
                                foreach($listAgrupada[$campoControl][$cr] as $c)
                                {
                                    $camposReferenciaControl[] = $c;
                                    foreach($listRegistros as $registro)
                                    {
                                        if($registro[$campoControl] == $c)
                                        {
                                            $listAgrupada[$campo][$c][$registro[$campo]] = $registro[$campo];
                                        }
                                    }
                                }
                            }
                            $camposReferencia = $camposReferenciaControl;
                        }
                    }
                    $campoControl = $campo;
                    $listAgrupada[$campo]['referencia'] = array_key_exists($index + 1, $camposAgrupacion)?$camposAgrupacion[$index + 1]:'registros';
        
                    /** Se guardan los registros de tal manera que se asocien al último nivel de agrupación */
                    /** ----------------------------------------------------------------------------------- */
        
                    if($listAgrupada[$campo]['referencia'] == 'registros')
                    {
                        if(empty($camposReferencia))
                        {
                            $campoAnterior = $camposAgrupacion[0];
                            foreach($listAgrupada[$campoAnterior] as $c)
                            {
                                foreach($listRegistros as $registro)
                                {
                                    if($registro[$campoAnterior] == $c)
                                    {
                                        foreach($camposAgrupacion as $campo){unset($registro[$campo]);}
                                        $listAgrupada['registros'][$c][] = $registro;
                                    }
                                }
                            }
                        }
                        else
                        {
                            foreach($camposReferencia as $cr)
                            {
                                foreach($listAgrupada[$campoControl][$cr] as $c)
                                {
                                    foreach($listRegistros as $registro)
                                    {
                                        $campoAnterior = $registro[$camposAgrupacion[count($camposAgrupacion) - 2]];
                                        if($campoAnterior == $cr && $registro[$campoControl] == $c)
                                        {
                                            foreach($camposAgrupacion as $campo){unset($registro[$campo]);}
                                            $listAgrupada['registros'][$campoAnterior.$c][] = $registro;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                /** Se crea la sección de agrupamiento con todos los campos seleccionados */
                /** --------------------------------------------------------------------- */

                $index = 0;
                $indexFila = 0;
                $divAgrupacion = '';
                $registrosAgrupados = [];
                $divAgrupacionGeneral = '';
                $divRegistrosAgrupacion = '';
                foreach($listAgrupada as $key => $lista)
                {
                    if($key == 'registros'){break;}
                    $campo = array_filter($agrupamiento[0]['campos'], fn($item) => $item['nombre'] == $key);
                    sort($campo);
                    $titulo = $campo[0]['titulo'];
                    foreach($lista as $keyFila => $items)
                    {
                        if($keyFila == 'referencia'){continue;}
                        $keyFila = str_replace(' ', '_', $keyFila);
                        $marginTopFila = ($indexFila == 0)?'':'margin-top:3px;';
                        if($index == 0)
                        {
                            $registrosAgrupados[] = $keyFila; 
                            $nombreAgrupacion = explode('-', $items);
                            if(count($nombreAgrupacion) > 1)
                            {
                                unset($nombreAgrupacion[0]);
                                $nombreAgrupacion = implode('-', $nombreAgrupacion);
                            }
                            $divAgrupacionGeneral .=
                            <<<TWIG
                            <div style=
                            "
                                $marginTopFila
                                padding:12px 17px; 
                                background:#f2f2f2;
                                border:1px solid gray; 
                                border-radius:5px 5px 0px 0px; 
                            ">
                                <table border="0" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <th>$titulo</th>
                                        <th style="padding-left:5px; padding-right:5px">»</th>
                                        <td>$nombreAgrupacion</td>
                                    </tr>
                                </table>
                            </div>
                            <div style="border: 1px solid gray; padding:10px; border-radius: 0px 0px 5px 5px; margin-top:-1px;">
                                replace_$keyFila
                            </div>
                            TWIG;
                        }
                        else
                        {
                            foreach($items as $keyItem => $item)
                            {
                                $keyItem = str_replace(' ', '_', $keyItem);
                                if($index == (count($listAgrupada) - 2))
                                {
                                    $keyItem = $keyFila.str_replace(' ', '_', $keyItem);
                                    $registrosAgrupados[] = $keyItem; 
                                }
                                $nombreAgrupacion = explode('-', $item);
                                if(count($nombreAgrupacion) > 1)
                                {
                                    unset($nombreAgrupacion[0]);
                                    $nombreAgrupacion = implode('-', $nombreAgrupacion);
                                }
                                $divAgrupacion .=
                                <<<TWIG
                                <div style=
                                "
                                    margin-top:3px;
                                    padding:12px 17px;
                                    background:#f2f2f2;
                                    border:1px solid gray; 
                                    border-radius:5px 5px 0px 0px; 
                                ">
                                    <table border="0" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <th>$titulo</th>
                                            <th style="padding-left:5px; padding-right:5px">»</th>
                                            <td>$nombreAgrupacion</td>
                                        </tr>
                                    </table>
                                </div>
                                <div style="border: 1px solid gray; padding:10px; border-radius: 0px 0px 5px 5px; margin-top:-1px;">
                                    <div>
                                        replace_$keyItem
                                    </div>
                                </div>
                                TWIG;
                            }
                            $divAgrupacionGeneral = str_replace('replace_'.$keyFila, $divAgrupacion, $divAgrupacionGeneral);
                            $divAgrupacion = '';

                        }
                        
                        /** Se agrega a los items del último campo de agrupación los registros correspondientes */
                        /** ----------------------------------------------------------------------------------- */

                        if($index == (count($listAgrupada) - 2))
                        {
                            foreach($registrosAgrupados as $indexAgrupacion => $registros)
                            {
                                if(array_key_exists(str_replace('_', ' ', $registros), $listAgrupada['registros']))
                                {
                                    $divAgrupacion = $this->crearTablaRegistrosPDF($request, $configuraciones, $listAgrupada['registros'][str_replace('_', ' ', $registros)], true);
                                    $divAgrupacionGeneral = str_replace('replace_'.$registros, $divAgrupacion, $divAgrupacionGeneral);
                                }
                            }
                        }
                        $indexFila ++;
                        $divAgrupacion = '';
                    }
                    $index ++;
                }

                /** Se genera la sección de totales obtenidos a partir de los campos de agrupación */
                /** ------------------------------------------------------------------------------ */

                if(!empty($this->camposTotalizados))
                {
                    foreach($this->camposTotalizados as $ct)
                    {
                        $tituloTotal = $ct[0];
                        $valorTotal = number_format($ct[1], 2, ',', '.');
                        $divTotalesGenerales .= 
                        <<<TWIG
                            <tr>
                                <td style="text-align:center; padding:5px 7px">
                                    $tituloTotal
                                </td>
                                <td style="text-align:right; padding:5px 7px">
                                    $valorTotal
                                </td>
                            </tr>
                        </div>
                        TWIG;
                    }
                    $divTotalesGenerales =
                    <<<TWIG
                    <div style="margin-top:20px;">
                        <div style="background:#f2f2f2; text-align:center; font-weight:bold; padding:7px; border:1px solid gray; border-bottom:none; border-radius:5px 5px 0px 0px;">
                            TOTALES DEL INFORME
                        </div>
                        <table border="1" cellpadding="0" cellspacing="0" style="width:100%">
                            $divTotalesGenerales
                        </table>
                    </div>
                    TWIG;
                }
                $contenidoPDF = 
                <<<TWIG
                <div style="width:100%">
                    $divAgrupacionGeneral
                    $divTotalesGenerales
                </div>
                TWIG;
            }
            else
            {
                /** Se genera el informe sin campos de agrupacion */
                /** --------------------------------------------- */
                
                $contenidoPDF = $this->crearTablaRegistrosExcel($request, $configuraciones, $listRegistros, false, $sheet);
            }
        } 
        catch(\Exception $e) 
        {
            $status = 'error';
            $message = $e->getMessage().' - '.$e->getFile().' - '.$e->getLine();    
        }

        $ultimaColumna = $this->ultimaColumna; 
        $sheet->getRowDimension('1')->setRowHeight(35);
        $sheet->getRowDimension('2')->setRowHeight(20);
        $sheet->getRowDimension('3')->setRowHeight(20);
        $sheet->getRowDimension('4')->setRowHeight(20);
        $sheet->getRowDimension('5')->setRowHeight(20);
        $sheet->getRowDimension('6')->setRowHeight(20);

        $sheet->mergeCells('A1:A5');
        $sheet->mergeCells('H1:'.$ultimaColumna.'5');
        $sheet->mergeCells('A6:'.$ultimaColumna.'6');
        $sheet->mergeCells('B1:'.$ultimaColumna.'1');
        $sheet->mergeCells('B2:'.$ultimaColumna.'2');
        $sheet->mergeCells('B3:'.$ultimaColumna.'3');
        $sheet->mergeCells('B4:'.$ultimaColumna.'4');
        $sheet->mergeCells('B5:'.$ultimaColumna.'5');
        $sheet->getStyle('B2:B5')->getFont()->setSize(12);
        $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('B2:B5')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

        /** Información cabecera */
        /** -------------------- */

        $nitText = $nit->createTextRun('  NIT: ');
        $nitText->getFont()->setBold(true);
        $nit->createText($compania->getNit());

        $telefonoText = $telefono->createTextRun('  Teléfono: ');
        $telefonoText->getFont()->setBold(true);
        $telefono->createText($compania->getTelefonos());

        $direccionText = $direccion->createTextRun('  Dirección: ');
        $direccionText->getFont()->setBold(true);
        $direccion->createText($compania->getDireccion());

        $sheet->setCellValue('B1', '  '.strtoupper($compania->getNombre()));
        $sheet->setCellValue('B2', $nit);
        $sheet->setCellValue('B3', $direccion);
        $sheet->setCellValue('B4', $telefono);

    
        /** Información detalles */
        /** -------------------- */

        /*foreach($movimiento->detallesOrdenados() as $det)
        {
            $styles = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE,
                        'color' => ['argb' => '000000'],
                    ],
                ],
            ];

            $totalDetalle = ($det->getVrUnidadSinIva()*$det->getCantAprobada())+$det->getVrIva();
            $totalDescuento += $det->getVrDescuento();
            $totalIva += $det->getVrIva();
            $totalOrden += $totalDetalle;
            $sheet->getStyle('A'.$index.':H'.$index)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            $sheet->getStyle('A'.$index.':H'.$index)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            $sheet->getStyle('A'.$index.':H'.$index)->applyFromArray($styles);
            $sheet->getRowDimension($index)->setRowHeight(18);
            $sheet->setCellValue('A'.$index, $det->getProducto()->getCodigo1());
            $sheet->setCellValue('B'.$index, $det->getProducto()->getNombre1());
            $sheet->setCellValue('C'.$index, $det->getProducto()->getUnidadMedida()->getNombre());
            $sheet->setCellValue('D'.$index, round($det->getCantAprobada(),2));
            $sheet->setCellValue('E'.$index, round($det->getVrUnidad(),2));
            $sheet->setCellValue('F'.$index, round($det->getVrDescuento(),2));
            $sheet->setCellValue('G'.$index, round($det->getVrIva(),2));
            $sheet->setCellValue('H'.$index, round($totalDetalle,2));
            $index ++;
        }

        $sheet->getStyle('A'.$index.':H'.$index)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('dddddd')
        ;

        $styles = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE,
                    'color' => ['argb' => '000000'],
                ],
            ],
        ];

        $sheet->mergeCells('A'.$index.':E'.$index);
        $sheet->getRowDimension($index)->setRowHeight(18);
        $sheet->getStyle('A7:H'.$index)->getFont()->setSize(10);
        $sheet->getStyle('A1:H'.$index)->getFont()->setName('Arial');
        $sheet->getStyle('A'.$index.':H'.$index)->applyFromArray($styles);
        $sheet->getStyle('A'.$index.':H'.$index)->getFont()->setBold(true);
        $sheet->getStyle('A'.$index)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('A'.$index.':H'.$index)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('F'.$index.':H'.$index)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A'.$index, 'Total  ');
        $sheet->setCellValue('F'.$index, round($totalDescuento,2));
        $sheet->setCellValue('G'.$index, round($totalIva,2));
        $sheet->setCellValue('H'.$index, round($totalOrden,2));*/

        /* Color de fondo Title */
        /* -------------------- */

        $sheet->getStyle('A7:'.$ultimaColumna.'7')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('f2f2f2')
        ;

        /* Estilo de Bordes */
        /* ---------------- */

        $styles = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'd1d4da'],
                ],
            ],
        ];

        $stylesCabecera = [
            'borders' => [
                'outline' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'd1d4da'],
                ],
            ],
        ];

        $stylesCabeceraInterior = [
            'borders' => [
                'inside' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FFFFFF'],
                ],
            ],
        ];

        $sheet->getStyle('A1:'.$ultimaColumna.'5')->applyFromArray($stylesCabecera);
        $sheet->getStyle('A1:'.$ultimaColumna.'5')->applyFromArray($stylesCabeceraInterior);
        $sheet->getSheetView()->setZoomScale(80);
        $sheet->setTitle($nombreInforme);

        /* Configuración del logo */
        /* ---------------------- */
        
        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawing->setName('Informe');
        $drawing->setDescription('Informe');
        $drawing->setPath($rutaLogo.$logoTmp);
        $drawing->setCoordinates('A1');
        $drawing->setWidthAndHeight(130, 44);
        $drawing->setResizeProportional(true);
        $drawing->setOffsetX(8);
        $drawing->setOffsetY(65);
        $drawing->setWorksheet($spreadsheet->getActiveSheet());

        /* Crear y guardar archivo */
        /* ----------------------- */

        $writer = new Xlsx($spreadsheet);
        $temp_file = tempnam(sys_get_temp_dir(), 'informe.xls');
        $writer->save($temp_file);
        $fsObject->remove($rutaLogo.$logoTmp);
        $nombreInforme = strtolower(str_replace(' ', '_', $nombreInforme));
        return $this->file($temp_file, $nombreInforme.'.xls', ResponseHeaderBag::DISPOSITION_INLINE);
    }

    public function crearTablaRegistrosExcel(Request $request, $configuraciones, $listRegistros, $agrupacion = false, $sheet)
    {   
        /** 
         * En esta función se crea la tabla del PDF con todos los registros del informe
         * ----------------------------------------------------------------------------
         * @access public
        */

        /** Definición de variables */
        /** ----------------------- */

        $index = 1;
        $tdCampo = '';
        $cabecera = [];
        $filasPDF = '';
        $trTotales = '';
        $filaTitulo = 7;
        $rutaCampo = '';
        $thCabecera = '';
        $trCabecera = '';
        $titulosPDF = '';
        $divRelleno = '';
        $estiloBordes = '';
        $rellenoCampo = '';
        $contenidoPDF = '';
        $tablaTotales = [];
        $indexCabecera = 0;
        $inicioRegistros = 8;
        $camposTotalizacion = [];
        $tablaTotales['colspan'] = 0;
        $camposTotalizacionAgrupamiento = [];
        $camposTotalizados = $this->camposTotalizados;
        $ruta = $request->getScheme().'://'.$request->server->get('HTTP_HOST');
        $alineaciones = ['centro' => 'center', 'derecha' => 'right', 'izquierda' => 'left'];

        /** Se obtiene el json que contiene las configuraciones del informe */
        /** --------------------------------------------------------------- */

        if(!empty($configuraciones))
        {
            if(array_key_exists('campos', $configuraciones)){$configuracionCampos = $configuraciones['campos'];}
            if(array_key_exists('cabecera', $configuraciones) && is_array($configuraciones['cabecera']) && !empty($configuraciones['cabecera'])){$cabecera = $configuraciones['cabecera'];}
            if(array_key_exists('totalizacion', $configuraciones['agrupamiento'][0]) && !empty($configuraciones['agrupamiento'][0]['totalizacion']) && is_array($configuraciones['agrupamiento'][0]['totalizacion']))
            {
                $camposTotalizados = [];
                $camposTotalizacion = $configuraciones['agrupamiento'][0]['totalizacion'];
            }
        }

        /** Se obtiene la totalización de los campos */
        /** ---------------------------------------- */

        if($agrupacion)
        {
            foreach($listRegistros as $indexRegistro => $registro)
            {
                foreach($camposTotalizacion as $ct)
                {
                    if(array_key_exists('campo', $ct) && array_key_exists($ct['campo'], $registro))
                    {
                        if(array_key_exists($ct['campo'], $camposTotalizados))
                        {
                            $camposTotalizados[$ct['campo']] = $camposTotalizados[$ct['campo']] + $registro[$ct['campo']];
                        }
                        else
                        {
                            $camposTotalizados[$ct['campo']] = $registro[$ct['campo']];
                        }
                    }
                }
            }
        }

        /** Se genera la tabla de registros */
        /** ------------------------------- */
        
        foreach($listRegistros as $indexRegistro => $registro)
        {   
            $finColspan = false;
            $rellenoCampo = (($indexRegistro + 1) % 2 == 0) ? '#17A2B814' : '';
            $this->ultimaColumna = Coordinate::stringFromColumnIndex(count($registro));
            foreach($registro as $key => $campo)
            {
                /** Se crean los títulos del informe con sus respectivos estilos */
                /** ------------------------------------------------------------ */
                
                $alineacionCampo = 'left';
                $alineacionTitulo = 'center';
                $titulo = ucfirst(str_replace('_', ' ', $key));
                $columna = Coordinate::stringFromColumnIndex($index);
                $configuracionCampo = array_filter($configuracionCampos, fn($item) => $item['nombre'] == $key);

                /** Se validan las configuraciones de cada campo */
                /** -------------------------------------------- */

                sort($configuracionCampo);
                if(!empty($configuracionCampo))
                {
                    /** Configuraciones del título */
                    /** -------------------------- */

                    if(array_key_exists('titulo', $configuracionCampo[0])){$titulo = $configuracionCampo[0]['titulo'];}
                    if(array_key_exists('alineacionTitulo', $configuracionCampo[0]) && array_key_exists($configuracionCampo[0]['alineacionTitulo'], $alineaciones))
                    {
                        $alineacionTitulo = $alineaciones[$configuracionCampo[0]['alineacionTitulo']];
                    }

                    /** Configuraciones de campos */
                    /** ------------------------- */

                    if(array_key_exists('alineacionCampo', $configuracionCampo[0]) && array_key_exists($configuracionCampo[0]['alineacionCampo'], $alineaciones))
                    {
                        $alineacionCampo = $alineaciones[$configuracionCampo[0]['alineacionCampo']];
                    }

                    if(array_key_exists('tipoDato', $configuracionCampo[0]) && $configuracionCampo[0]['tipoDato'] == 'moneda')
                    {
                        $campo = number_format($campo, 2, ',', '.');
                    }

                    if(array_key_exists('tipoDato', $configuracionCampo[0]) && $configuracionCampo[0]['tipoDato'] == 'numero')
                    {
                        $campo = number_format($campo, 2, '.', '');
                    }

                    if(array_key_exists('tipoDato', $configuracionCampo[0]) && $configuracionCampo[0]['tipoDato'] == 'fecha')
                    {
                        $campo = (new \DateTime($campo))->format('Y-m-d');
                    }

                    /** Se valida si el campo tiene una ruta configurada */
                    /** ------------------------------------------------ */

                    if(array_key_exists('ruta', $configuracionCampo[0]) && !empty($configuracionCampo[0]['ruta']))
                    {
                        $parametros = [];
                        $alineacionCampo = 'center';
                        if(array_key_exists('parametros', $configuracionCampo[0]) && is_array($configuracionCampo[0]['parametros']) && !empty($configuracionCampo[0]['parametros']))
                        {
                            $parametros = str_replace('$campo', $campo, json_encode($configuracionCampo[0]['parametros']));
                            $parametros = json_decode($parametros, true);
                        }
                        $rutaCampo = $ruta.$this->generateUrl($configuracionCampo[0]['ruta'], $parametros);
                    }
                }

                /** Se crean los títulos del informe */
                /** -------------------------------- */

                if($indexRegistro == 0)
                {   
                    /** Se crea la sección de la cabecera */
                    /** --------------------------------- */
                    
                    if(!empty($cabecera))
                    {
                        if($indexCabecera == 0)
                        {
                            $filaTitulo = 8;
                            $columnaInicio = 1;
                            $inicioRegistros = 9;
                            $sheet->getRowDimension('7')->setRowHeight(25);
                            $sheet->getStyle('A7:'.$this->ultimaColumna.'7')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                            $sheet->getStyle('A7:'.$this->ultimaColumna.'7')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
                            foreach($cabecera as $index => $c)
                            {
                                $colSpanCabecera = $c['colspan'];
                                $tituloCabecera = strip_tags($c['nombre']);
                                $columnaFinal = Coordinate::stringFromColumnIndex(($columnaInicio + $colSpanCabecera) - 1);
                                $columnaInicial = Coordinate::stringFromColumnIndex($columnaInicio);
                                $sheet->setCellValue($columnaInicial.'7', $tituloCabecera);
                                $sheet->mergeCells($columnaInicial.'7:'.$columnaFinal.'7');
                                $columnaInicio += $colSpanCabecera;
                            }
    
                            /** Se aplican estilos a la cabecera del informe */
                            /** -------------------------------------------- */
    
                            $sheet->getStyle('A7:'.$this->ultimaColumna.'7')->getFill()
                                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                ->getStartColor()->setARGB('f2f2f2')
                            ;
                            $styles = 
                            [
                                'borders' => 
                                [
                                    'allBorders' => 
                                    [
                                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                        'color' => ['argb' => 'gray'],
                                    ],
                                ],
                            ];
                            $sheet->getStyle('A7:'.$this->ultimaColumna.'7')->getFont()->setBold(true)->setSize(11);
                            $sheet->getStyle('A7:'.$this->ultimaColumna.'7')->applyFromArray($styles);
                            $indexCabecera ++;
                        }
                    }
                    $anchoCampo = ($index == 1)?20:30;
                    $sheet->setCellValue($columna.$filaTitulo, $titulo);
                    $sheet->getRowDimension($filaTitulo)->setRowHeight(25);
                    $sheet->getColumnDimension($columna)->setWidth($anchoCampo);
                    $sheet->getStyle('A'.$filaTitulo.':'.$this->ultimaColumna.$filaTitulo)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                    $sheet->getStyle('A'.$filaTitulo.':'.$this->ultimaColumna.$filaTitulo)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                    /** Se aplican estilos a los títulos del informe */
                    /** -------------------------------------------- */

                    $sheet->getStyle('A'.$filaTitulo.':'.$this->ultimaColumna.$filaTitulo)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('f2f2f2')
                    ;
                    $styles = 
                    [
                        'borders' => 
                        [
                            'allBorders' => 
                            [
                                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                'color' => ['argb' => 'gray'],
                            ],
                        ],
                    ];
                    $sheet->getStyle('A'.$filaTitulo.':'.$this->ultimaColumna.$filaTitulo)->applyFromArray($styles);
                    $sheet->getStyle('A'.$filaTitulo.':'.$this->ultimaColumna.$filaTitulo)->getFont()->setBold(true)->setSize(11);
                }

                /** Se crea cada registro del informe */
                /** --------------------------------- */

                $sheet->getStyle($columna.$inicioRegistros)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                if($alineacionCampo == 'center')
                {
                    $sheet->getStyle($columna.$inicioRegistros)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                }
                if($alineacionCampo == 'left')
                {
                    $sheet->getStyle($columna.$inicioRegistros)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                }
                if($alineacionCampo == 'right')
                {
                    $sheet->getStyle($columna.$inicioRegistros)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                }
                $sheet->setCellValue($columna.$inicioRegistros, $campo);

                /** Se asignan estilos a cada campo */
                /** ------------------------------- */

                $styles = 
                [
                    'borders' => 
                    [
                        'allBorders' => 
                        [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['argb' => 'd1d4da'],
                        ],
                    ],
                ];
                $sheet->getStyle('A'.$inicioRegistros.':'.$this->ultimaColumna.$inicioRegistros)->applyFromArray($styles);
                $sheet->getRowDimension($inicioRegistros)->setRowHeight(20);

                /** Se diseña la tabla de acuerdo a los totales configurados */
                /** -------------------------------------------------------- */

                if($indexRegistro == array_key_last($listRegistros))
                {
                    if(array_key_exists($key, $camposTotalizados))
                    {
                        $finColspan = true;
                        $total = $camposTotalizados[$key];
                        if(!empty($configuracionCampo))
                        {
                            if(array_key_exists('tipoDato', $configuracionCampo[0]) && $configuracionCampo[0]['tipoDato'] == 'moneda')
                            {
                                $total = number_format($total, 2, ',', '.');
                            }

                            if(array_key_exists('tipoDato', $configuracionCampo[0]) && $configuracionCampo[0]['tipoDato'] == 'numero')
                            {
                                $total = number_format($total, 2, '.', '');
                            }

                            if(array_key_exists('alineacionCampo', $configuracionCampo[0]) && array_key_exists($configuracionCampo[0]['alineacionCampo'], $alineaciones))
                            {
                                $alineacionCampo = $alineaciones[$configuracionCampo[0]['alineacionCampo']];
                            }
                        }
                        $tablaTotales['campo'.$index] = [$total, $alineacionCampo];
                    }
                    else
                    {
                        if(!$finColspan)
                        {
                            $tablaTotales['colspan'] = $tablaTotales['colspan'] + 1;
                        }
                        else
                        {
                            $tablaTotales['campo'.$index] = '';
                        }
                    }

                    /** Se obtienen los títulos de los totales generales */
                    /** ------------------------------------------------ */

                    if(array_key_exists($key, $this->camposTotalizados))
                    {
                        if(!is_array($this->camposTotalizados[$key]))
                        {
                            $this->camposTotalizados[$key] = [$titulo, $this->camposTotalizados[$key]];
                        }
                    }
                }
                $index ++;
            }
            $index = 1;
            $inicioRegistros ++;
        }

        /** Se crea la sección de totales */
        /** ----------------------------- */

        $index = 0;
        $tdTotal = '';
        if(!empty($camposTotalizados))
        {
            foreach($tablaTotales as $key => $campoTotal)
            {
                if($key == 'colspan' && $campoTotal > 0)
                {
                    $tdTotal .= 
                    <<<TWIG
                    <th colspan="$campoTotal">
                        <div style="background:#f2f2f2; text-align:right; padding:7px; border:1px solid gray; border-right:1px solid #f2f2f2; border-top:none; border-radius:0px 0px 0px 5px">
                            Total &raquo;
                        </div>
                    </th>
                    TWIG;
                }
                else
                {
                    $campo = !empty($campoTotal)?$campoTotal[0]:'';
                    $alineacionCampo = !empty($campoTotal)?$campoTotal[1]:'';
                    $estiloBordes = ($index == (count($tablaTotales) - 1))?'border-bottom:1px solid gray; border-right:1px solid gray; border-radius:0px 0px 5px 0px;':'border-bottom:1px solid gray; border-right:1px solid #f2f2f2';
                    $tdTotal .= 
                    <<<TWIG
                    <th>
                        <div style="background:#f2f2f2; text-align:$alineacionCampo; padding:7px; height:12px; $estiloBordes">
                            $campo
                        </div>
                    </th>
                    TWIG;
                }
                $index ++;
            }
            $trTotales = 
            <<<TWIG
                <tr>
                    $tdTotal
                </tr>
            TWIG;
        }

        /** Contenido del PDF */
        /** ----------------- */

        $contenidoPDF =
        <<<TWIG
        <table border="0" cellpadding="0" cellspacing="0" style="width:100%">
            $trCabecera
            <tr>
                $titulosPDF
            </tr>
            $filasPDF
            $trTotales
        </table>
        TWIG;
        return $contenidoPDF;
    }
}