<?php

namespace App\Controller\Central\Reporteador;

use Dompdf\Dompdf;
use Dompdf\Options;
use Knp\Snappy\Pdf;
use App\Entity\Central\meses;
use App\Entity\Central\compania;
use App\Entity\Central\reportes;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Doctrine\DBAL\Exception\ConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ReporteadorController extends AbstractController
{
    private $em;
    private $pdf;
    private $filaGeneral;
    private $ultimaColumna;
    private $camposTotalizados;
    public function __construct(EntityManagerInterface $em, Pdf $pdf)
    {
        $this->em = $em;
        $this->pdf = $pdf;
        $this->filaGeneral = 6;
        $this->ultimaColumna = '';
        $this->camposTotalizados = [];
        $this->camposConfigurados = [];
        $this->configuracionesGuardadas = [];
    }

    /**
     * @Route("/Central/Reporteador/generarInforme", name="central_reporteador_generar_informe")
    */
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
        $anchoContenidoInforme = '';
        $rellenoOpcionPaginator = '';
        $iconoBloqueo = 'color:gray;';
        $listRegistrosPaginacion = [];
        $camposConfiguradosVista = '';
        $bloqueoOpcionesDescarga = ';';
        $cabeceraConfiguradaVista = '';
        $conexion = $bd->getConnection();
        $listRegistrosBusquedaRapida = [];
        $listRegistrosBusquedaDinamica = [];
        $accionBloqueo = 'pointer-events:none;';
        $camposAgrupacionConfiguradosVista = '';
        $camposBusquedaDinamicaConfiguradosVista = '';
        $displayConfiguracionCabecera = 'display:none;';
        $displayConfiguracionAgrupacion = 'display:none;';
        $form = $request->request->get('filtros_reporteador');
        $busquedaRapida = $request->request->get('busquedaRapida');
        $bloqueoMenu = 'pointer-events:none; opacity:0.6 !important;';
        $compania = $bd->getRepository(compania::class)->findOneBy([]);
        $alineaciones = ['centro' => 'center', 'derecha' => 'right', 'izquierda' => 'left'];
        $informe = $bd->getRepository(reportes::class)->findOneBy(['id' => $form['informe']]);
        $fondo = base64_encode(file_get_contents($this->getParameter('imgs_directory').'fondo.jpg'));
        $logoError = base64_encode(file_get_contents($this->getParameter('imgs_directory').'logoActualizado.png'));
        $this->configuracionesGuardadas = !empty($request->request->get('configuracionesGuardadas'))?json_decode($request->request->get('configuracionesGuardadas'), true):[];

        /** Se obtienen los filtros de búsqueda seleccionados */
        /** ------------------------------------------------- */

        $sqlInforme = $informe->getSql();
        $nitCompania = $compania->getNit();
        $logo = $compania->getLogocompania();
        $nombreInforme = $informe->getNombre();
        $pagina = $request->request->get('pagina');
        $nombreCompania = strtoupper($compania->getNombre());
        preg_match_all('/\[(.*?)\]/', $sqlInforme, $camposSQL);
        foreach($form as $key => $campo){$filtros['['.$key.']'] = !empty($campo)?$campo:-1;}
        $direccionCompania = substr($compania->getDireccion(), 0, 50).' - '.$compania->getTelefonos();

        /** Se valida si las variables definidas en el sql se encuentran en los filtros de búsqueda */
        /** --------------------------------------------------------------------------------------- */

        foreach($camposSQL[0] as $campoSQL)
        {
            if(!array_key_exists($campoSQL, $filtros))
            {
                $filtros[$campoSQL] = '-1';
            }
        }
        $sqlInforme = strtr($sqlInforme, $filtros);

        /** Se obtiene el json que contiene las configuraciones del informe */
        /** --------------------------------------------------------------- */

        $tablaTotales['colspan'] = 0;
        $configuraciones = $informe->getJson();
        if(!empty($configuraciones))
        {
            if(array_key_exists('campos', $configuraciones)){$configuracionCampos = $configuraciones['campos'];}
            if(array_key_exists('anchoTabla', $configuraciones) && !empty($configuraciones['anchoTabla'])){$anchoContenidoInforme = 'width:'.$configuraciones['anchoTabla'];}
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
                            <span class="montserrat-text" style="font-size:11px; width:max-content">$periodo</span>
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
            if(count($listRegistros) > 0){$bloqueoMenu = '';}
            if($busquedaRapida != '')
            {
                /** Se filtran los registros de acuerdo a la búsqueda rápida */
                /** -------------------------------------------------------- */

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
            else
            {
                /** Se filtran los registro de acuerdo a la búsqueda dinámica */
                /** --------------------------------------------------------- */

                $condicionesValidas = 0;
                if(!empty($this->configuracionesGuardadas) && !empty($this->configuracionesGuardadas['busquedaDinamica']))
                {
                    foreach($listRegistros as $registro)
                    {
                        foreach($this->configuracionesGuardadas['busquedaDinamica'] as $busqueda)
                        {
                            $campoBusqueda = $busqueda['campo'];
                            if(!empty($busqueda['input']))
                            {
                                if($busqueda['tipo'] == 'fecha')
                                {
                                    if($busqueda['select'] == 'igual')
                                    {
                                        if(new \DateTime($busqueda['input']) == new \DateTime($registro[$campoBusqueda])){$condicionesValidas ++;}
                                    }
                                    if($busqueda['select'] == 'entre')
                                    {
                                        if((new \DateTime($registro[$campoBusqueda]) >= new \DateTime($busqueda['input'])) && (new \DateTime($registro[$campoBusqueda]) <= new \DateTime($busqueda['hasta'])))
                                        {
                                            $condicionesValidas ++;
                                        }
                                    }
                                    if($busqueda['select'] == 'mayor')
                                    {
                                        if(new \DateTime($registro[$campoBusqueda]) > new \DateTime($busqueda['input'])){$condicionesValidas ++;}
                                    }
                                    if($busqueda['select'] == 'menor')
                                    {
                                        if(new \DateTime($registro[$campoBusqueda]) < new \DateTime($busqueda['input'])){$condicionesValidas ++;}
                                    }
                                }
                                else
                                {
                                    if($busqueda['select'] == 'igual')
                                    {
                                        if($busqueda['input'] == $registro[$campoBusqueda]){$condicionesValidas ++;}
                                    }
                                    if($busqueda['select'] == 'mayor')
                                    {
                                        if($registro[$campoBusqueda] > $busqueda['input']){$condicionesValidas ++;}
                                    }
                                    if($busqueda['select'] == 'menor')
                                    {
                                        if($registro[$campoBusqueda] < $busqueda['input']){$condicionesValidas ++;}
                                    }
                                    if($busqueda['select'] == 'contiene')
                                    {
                                        if(strpos($registro[$campoBusqueda], $busqueda['input']) !== false){$condicionesValidas ++;}
                                    }
                                    if($busqueda['select'] == 'no_contiene')
                                    {
                                        if(strpos($registro[$campoBusqueda], $busqueda['input']) === false){$condicionesValidas ++;}
                                    }
                                }
                            }
                            else
                            {
                                if($registro[$campoBusqueda] == ''){$condicionesValidas ++;}
                            }
                        }
                        if($condicionesValidas == count($this->configuracionesGuardadas['busquedaDinamica']))
                        {
                            $listRegistrosBusquedaDinamica[] = $registro;
                        }
                        $condicionesValidas = 0;
                    }
                    $listRegistros = $listRegistrosBusquedaDinamica;
                }
            }

            /** Se bloquean las opciones de descarga si no se encuentran registros aplicando la búsqueda rápida o dinámica */
            /** ---------------------------------------------------------------------------------------------------------- */

            if((!empty($this->configuracionesGuardadas) && !empty($this->configuracionesGuardadas['busquedaDinamica']) || $busquedaRapida != '') && count($listRegistros) == 0)
            {
                $bloqueoOpcionesDescarga = 'pointer-events:none; opacity:0.6 !important;';
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
                    <div class="montserrat paginas" data-action="click->central--reporteador#seleccionarPagina" data-opc="1" data-pagina="$p" style=
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

            $totalPaginas = count($listRegistrosPaginacion);
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
                    $displayConfiguracionAgrupacion = '';
                    $keyCampos = array_keys($listRegistros[0]);
                    foreach($keyCampos as $campo)
                    {
                        foreach($agrupamiento[0]['campos'] as $a)
                        {
                            if($a['nombre'] == $campo){$camposAgrupacion[] = $campo;}
                        }
                    }
                }

                if(!empty($this->configuracionesGuardadas)){$camposAgrupacion = $this->configuracionesGuardadas['agrupacion'];}
                if(!empty($camposAgrupacion))
                {
                    /** Se genera el informe con campos de agrupación */
                    /** --------------------------------------------- */
                    
                    $listAgrupada = [];
                    $campoControl = '';
                    $campoAnterior = '';
                    $camposReferencia = [];
                    $divTotalesGenerales = '';
                    $camposAgrupacion = array_slice($camposAgrupacion, 0, 3);
                    
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
                                    <div class="montserrat paginas" data-action="click->central--reporteador#seleccionarPagina" data-pagina="1" data-opc="1" style=
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
                                        <i class="fas fa-forward" style="transform:rotate(180deg); font-size:11px; margin-right:2px; $iconoBotonAnterior"></i>
                                    </div>
                                    <div class="montserrat paginas" data-action="click->central--reporteador#seleccionarPagina" data-opc="2" style=
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
                                    <div class="montserrat paginas" data-action="click->central--reporteador#seleccionarPagina" data-opc="3" style=
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
                                    <div class="montserrat paginas" data-action="click->central--reporteador#seleccionarPagina" data-pagina="$totalPaginas" data-opc="1" style=
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
                                        <i class="fas fa-forward" style="font-size:11px; margin-left:1px; $iconoBotonSiguiente"></i>
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
            $bloqueoMenu = 'pointer-events:none; opacity:0.6 !important;';
            $contenidoInforme = $this->renderView('Central/Reporteador/frameErrorInforme.html.twig', 
            [
                'noCerrar' => true,
                'line' => $e->getLine(), 
                'file' => $e->getFile(), 
                'message' => $e->getMessage()
            ]);
        }

        /** Se añaden los campos de agrupación y se crean las secciones de campos que se visualizarán en las configuraciones del informe */
        /** ---------------------------------------------------------------------------------------------------------------------------- */

        foreach($camposAgrupacion as $campo)
        {
            $tipoDato = 'texto';
            $titulo = ucfirst(str_replace('_', ' ', $campo));
            $configuracionCampo = array_filter($configuracionCampos, fn($item) => $item['nombre'] == $campo);
            sort($configuracionCampo);
            if(!empty($configuracionCampo))
            {
                if(array_key_exists('titulo', $configuracionCampo[0])){$titulo = $configuracionCampo[0]['titulo'];}
                if(array_key_exists('tipoDato', $configuracionCampo[0]))
                {
                    $tipoDato = $configuracionCampo[0]['tipoDato'];
                }
                $this->camposConfigurados[$campo] = 
                [
                    'agrupacion' => true,
                    'tipoDato' => $tipoDato, 
                    'titulo' => empty(strip_tags($titulo))?ucfirst(str_replace('_', ' ', $campo)):strip_tags($titulo)
                ];
            }
        }

        /** Se crean las cabeceras de las configuraciones */
        /** --------------------------------------------- */

        if(!empty($cabecera))
        {
            $displayConfiguracionCabecera = '';
            foreach($cabecera as $index => $c)
            {
                $nombreCabecera = strip_tags($c['nombre']);
                $colspanCabecera = strip_tags($c['colspan']);
                $cabeceraConfiguradaVista .=
                <<<TWIG
                <div id="div_cabecera_$index" style="display:flex; align-items:center; width:100%;">
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
                        <input class="cabeceraConfiguracion" type="checkbox" id="check_$index" data-opc="$index" checked data-action="central--reporteador#seleccionarCampoConfiguracion central--reporteador#seleccionarCabecera">
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
                            type="text" 
                            class="form-control" 
                            placeholder="Nombre" 
                            value="$nombreCabecera"
                            id="input_nombre_$index" 
                            style="font-size:11px; height:28px" 
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
                            type="text" 
                            class="form-control" 
                            placeholder="Colspan" 
                            id="input_campos_$index" 
                            value="$colspanCabecera"
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
                        <i class="fas fa-trash text-danger" data-opc="$index" style="cursor:pointer" data-action="click->central--reporteador#eliminarCabecera"></i>
                    </div>
                </div>
                TWIG;
            }
        }

        /** Se crean las secciones de configuraciones */
        /** ----------------------------------------- */

        $cantidadCampos = 0;
        $fechaBusquedaDinamica = date('Y-m-d');
        foreach($this->camposConfigurados as $key => $campo)
        {
            $tipo = '';
            $cantidadCampos ++;
            $titulo = $campo['titulo'];
            $opcionesBusquedaDinamica = '';
            $bloqueoCampoConfiguracion = '';
            $divFechaHastaBusquedaDinamica = '';
            $checkCampoConfiguracion = 'checked';
            
            /** Se crea el selector y el input para cada campo de la agrupación dinámica de acuerdo a su tipo de dato */
            /** ----------------------------------------------------------------------------------------------------- */

            if($campo['tipoDato'] == 'texto')
            {
                $tipo = 'texto';
                $opcionesBusquedaDinamica .=
                <<<TWIG
                <option value="igual">Igual</option>
                <option value="contiene">Contiene</option>
                <option value="no_contiene">No contiene</option>
                TWIG;

                $inputBusquedaDinamica = 
                <<<TWIG
                <input type="text" class="form-control" style="font-size:11px; height:28px" placeholder="Buscar" id="input_$key" disabled data-action="central--reporteador#ingresarBusqueda">
                TWIG;   
            }
            elseif($campo['tipoDato'] == 'fecha')
            {
                $tipo = 'fecha';
                $opcionesBusquedaDinamica .=
                <<<TWIG
                <option value="igual">Igual</option>
                <option value="entre">Entre</option>
                <option value="mayor">Mayor que</option>
                <option value="menor">Menor que</option>
                TWIG;

                $inputBusquedaDinamica = 
                <<<TWIG
                <input type="date" class="form-control" style="font-size:11px; height:28px" id="input_$key" value="$fechaBusquedaDinamica" disabled data-action="central--reporteador#ingresarBusqueda">
                TWIG; 

                $inputHastaBusquedaDinamica = 
                <<<TWIG
                <input type="date" class="form-control" style="font-size:11px; height:28px" id="input_hasta_$key" value="$fechaBusquedaDinamica" disabled data-action="central--reporteador#ingresarBusqueda">
                TWIG;

                $divFechaHastaBusquedaDinamica =
                <<<TWIG
                <div id="div_hasta_$key" style=
                "
                    flex:1;
                    gap:5px; 
                    height:32px;
                    padding:1px; 
                    display:none;
                    background:white;
                    align-items:center; 
                    border:1px solid #d0d4da;
                    border-radius:0px 5px 5px 0px;
                    border-left:none;
                ">
                    $inputHastaBusquedaDinamica
                </div>
                TWIG;
            }
            else
            {
                $tipo = 'numero';
                $opcionesBusquedaDinamica .=
                <<<TWIG
                <option value="igual">Igual</option>
                <option value="mayor">Mayor que</option>
                <option value="menor">Menor que</option>
                TWIG;

                $inputBusquedaDinamica = 
                <<<TWIG
                <input type="text" class="form-control" style="font-size:11px; height:28px" placeholder="Buscar" id="input_$key" data-action="central--reporteador#formatearCampo central--reporteador#ingresarBusqueda" disabled>
                TWIG; 
            }

            if($campo['agrupacion'])
            {
                $checkCampoConfiguracion = '';
                $bloqueoCampoConfiguracion = 'pointer-events:none; opacity:0.5;';
            }
            $camposConfiguradosVista .=
            <<<TWIG
            <div id="div_$key" style="display:flex; align-items:center; width:100%; $bloqueoCampoConfiguracion">
                <div style=
                "
                    gap:5px; 
                    height:32px;
                    display:flex;
                    padding:7px 12px; 
                    background:white;
                    align-items:center;
                    background:#e9ecef; 
                    border:1px solid #d0d4da; 
                    border-radius:5px 0px 0px 5px; 
                ">
                    <input class="camposConfiguracion" type="checkbox" id="check_$key" data-campo="$key" $checkCampoConfiguracion data-action="central--reporteador#seleccionarCampoConfiguracion">
                </div>
                <div style=
                "
                    flex:1;
                    gap:5px; 
                    height:32px;
                    display:flex;
                    padding:7px 12px; 
                    background:white;
                    align-items:center; 
                    border:1px solid #d0d4da; 
                    border-radius:0px 5px 5px 0px; 
                    border-left:none;
                ">
                    <span class="montserrat" style="font-size:11px;">$titulo</span>
                </div>
            </div>
            TWIG;

            $camposBusquedaDinamicaConfiguradosVista .=
            <<<TWIG
            <div style="display:flex; align-items:center; width:100%;">
                <div style=
                "
                    gap:5px; 
                    height:32px;
                    display:flex;
                    padding:7px 12px; 
                    background:white;
                    align-items:center;
                    background:#e9ecef; 
                    border:1px solid #d0d4da; 
                    border-radius:5px 0px 0px 5px; 
                ">
                    <input class="camposBusquedaDinamica" type="checkbox" id="check_busqueda_dinamica_$key" data-campo="$key" data-tipo="$tipo" data-action="central--reporteador#seleccionarCampoBusquedaDinamica central--reporteador#seleccionarCampoConfiguracion">
                </div>
                <div id="div_texto_busqueda_dinamica_$key" style=
                "
                    gap:5px; 
                    height:32px;
                    width:207px;
                    display:flex;
                    padding:7px 12px; 
                    background:white;
                    align-items:center;
                    position:relative;
                    transition:all 0.5s ease;
                    border:1px solid #d0d4da;
                    border-left:none;
                ">
                    <i class="animate__animated animate__flipInX fas fa-info-circle" style="color:#17A; display:none; position:relative; transition:all 0.2s ease; opacity:0"></i>
                    <span class="animate__animated animate__flipInX montserrat" style="font-size:11px; transition:all 0.2s ease; opacity:1">$titulo</span>
                    <span class="tooltip">
                        <i class="fas fa-info-circle" style="font-size:10px;"></i> 
                        <span style="font-size:10px">$titulo</span>
                    </span>
                </div>
                <div id="div_select_busqueda_dinamica_$key" style=
                "
                    gap:5px; 
                    height:32px;
                    padding:1px; 
                    width:186px;
                    display:flex;
                    background:white;
                    align-items:center; 
                    transition:all 0.5s ease;
                    border:1px solid #d0d4da;
                    border-left:none;
                ">
                    <select class="custom-select form-control selectBusquedaDinamica" style="font-size:11px; height:28px" data-campo="$key" id="select_$key" data-action="central--reporteador#seleccionarTipoBusqueda" disabled>
                        $opcionesBusquedaDinamica
                    </select>
                </div>
                <div id="div_input_busqueda_dinamica_$key" style=
                "
                    flex:1;
                    gap:5px; 
                    height:32px;
                    padding:1px; 
                    display:flex;
                    background:white;
                    align-items:center; 
                    border:1px solid #d0d4da;
                    transition:all 0.5s ease;
                    border-radius:0px 5px 5px 0px;
                    border-left:none;
                ">
                    $inputBusquedaDinamica
                </div>
                $divFechaHastaBusquedaDinamica
            </div>
            TWIG;

            if($campo['agrupacion'])
            {
                $titulo = $campo['titulo'];
                $camposAgrupacionConfiguradosVista .=
                <<<TWIG
                <div style="display:flex; align-items:center; width:100%">
                    <div style=
                    "
                        gap:5px; 
                        height:32px;
                        display:flex;
                        padding:7px 12px; 
                        background:white;
                        align-items:center;
                        background:#e9ecef; 
                        border:1px solid #d0d4da; 
                        border-radius:5px 0px 0px 5px; 
                    ">
                        <input class="camposAgrupacionConfiguracion" type="checkbox" id="check_agrupacion_$key" data-campo="$key" data-action="central--reporteador#seleccionarCampoAgrupacion central--reporteador#seleccionarCampoConfiguracion" checked>
                    </div>
                    <div style=
                    "
                        flex:1;
                        gap:5px; 
                        height:32px;
                        display:flex;
                        padding:7px 12px; 
                        background:white;
                        align-items:center; 
                        border:1px solid #d0d4da; 
                        border-radius:0px 5px 5px 0px; 
                        border-left:none;
                    ">
                        <span class="montserrat" style="font-size:11px;">$titulo</span>
                    </div>
                </div>
                TWIG;
            }
        }

        $altoSeccion = ($cantidadCampos > 10)?380:($cantidadCampos * 35) + 30;
        $seccionConfiguraciones =
        <<<TWIG
        <div class="animate__animated animate__fadeIn" style="display:flex; align-items:center; justify-content:center; height:100%;">
            <div style="filter:drop-shadow(0px 0px 6px gray); border-radius:15px; padding:42px 25px 30px 25px; background:white; width:700px; overflow:hidden; position:relative">
                <i 
                    data-opc="2"
                    data-action="click->central--reporteador#cerrarConfiguraciones"
                    class="fas fa-times-circle cerrarError text-danger animate__animated animate__fadeInRight animate__delay-1s" 
                    style="position:absolute; right:18px; top:15px; font-size:15px; cursor:pointer; z-index:2; transition:all 0.5s ease; border-radius:50%"
                ></i>
                <i class="fas fa-cog fa-spin text-secondary" style="opacity:0.2; position:absolute; top:-105px; left:-131px; font-size:205px; --fa-animation-duration: 15s;"></i>
                <img src="data:image;base64,$fondo" style="width:100%; height:100%; object-fit:cover; position:absolute; opacity:0.1; z-index:0; top:0px; left:0px">
                <div class="animate__animated animate__fadeInDown" style="display:flex; align-items:center; gap:5px; border-radius:16px 0px; padding:10px 20px; background:#17A; color:white; position:relative; z-index:-1">
                    <i class="fas fa-cog" style="color:white; font-size:12px"></i>
                    <span class="montserrat">Configuraciones</span>
                </div>
                <hr>
                <div class="animate__animated animate__fadeIn animate__delay-1s" style="position:relative">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button 
                                role="tab" 
                                type="button" 
                                id="campos-tab" 
                                data-toggle="tab" 
                                data-opc="campos"
                                aria-selected="true"
                                data-target="#campos" 
                                aria-controls="campos" 
                                class="nav-link btnConfiguraciones active"
                                data-action="central--reporteador#seleccionarConfiguracion" 
                                style="transition:all 0.5s ease; display:flex; align-items:center; justify-content:center; gap:5px; background:#e9ecef; color:#17A; border-radius:9px 9px 0px 0px; border:1px solid #d0d4da; height:35px;"
                            >
                                <i class="fas fa-check-circle" style="font-size:12px"></i>
                                <span class="montserrat" style="font-size:11px">Campos</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation" style="$displayConfiguracionAgrupacion">
                            <button 
                                role="tab" 
                                type="button" 
                                data-toggle="tab" 
                                id="agrupacion-tab" 
                                aria-selected="true"
                                data-opc="agrupacion"
                                data-target="#agrupacion" 
                                aria-controls="agrupacion" 
                                class="nav-link btnConfiguraciones"
                                data-action="central--reporteador#seleccionarConfiguracion" 
                                style="transition:all 0.5s ease; display:flex; align-items:center; justify-content:center; gap:5px; border-radius:9px 9px 0px 0px; border:1px solid #d0d4da; height:35px;"
                            >
                                <i class="fas fa-layer-group" style="font-size:12px; color:gray"></i>
                                <span style="color:gray; font-size:11px" class="montserrat">Agrupación</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation" style="$displayConfiguracionCabecera">
                            <button 
                                role="tab" 
                                type="button" 
                                data-toggle="tab" 
                                id="cabecera-tab" 
                                aria-selected="true"
                                data-opc="cabecera"
                                data-target="#cabecera" 
                                aria-controls="cabecera" 
                                class="nav-link btnConfiguraciones"
                                data-action="central--reporteador#seleccionarConfiguracion" 
                                style="transition:all 0.5s ease; display:flex; align-items:center; justify-content:center; gap:5px; border-radius:9px 9px 0px 0px; border:1px solid #d0d4da; height:35px;"
                            >
                                <i class="fas fa-bookmark" style="font-size:12px; color:gray"></i>
                                <span style="color:gray; font-size:11px" class="montserrat">Cabeceras</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button 
                                role="tab" 
                                type="button" 
                                data-toggle="tab" 
                                aria-selected="true"
                                id="busquedaDinamica-tab" 
                                data-opc="busquedaDinamica"
                                data-target="#busquedaDinamica" 
                                aria-controls="busquedaDinamica" 
                                class="nav-link btnConfiguraciones"
                                data-action="central--reporteador#seleccionarConfiguracion" 
                                style="transition:all 0.5s ease; display:flex; align-items:center; justify-content:center; gap:5px; border-radius:9px 9px 0px 0px; border:1px solid #d0d4da; height:35px;"
                            >
                                <i class="fas fa-search" style="font-size:12px; color:gray"></i>
                                <span class="montserrat" style="color:gray; font-size:11px">Búsqueda dinámica</span>
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content" style="background:white">
                        <div class="tab-pane fade show active listadoTablaConfiguraciones" id="campos" role="tabpanel" aria-labelledby="campos-tab" style="padding:1px; overflow-y:auto; transition:all 0.5s ease;">
                            <div style=
                            "
                                gap:3px;
                                display:flex; 
                                padding:15px 15px; 
                                flex-direction:column; 
                                justify-content:center; 
                                border:1px solid #E2E2E2;
                                border-radius:0px 0px 5px 5px; 
                                border-top:none; 
                            ">
                                $camposConfiguradosVista
                            </div>
                        </div>
                        <div class="tab-pane fade" id="agrupacion" role="tabpanel" aria-labelledby="agrupacion-tab">
                            <div style=
                            "
                                gap:3px;
                                display:flex; 
                                padding:15px 15px; 
                                flex-direction:column;
                                height:{$altoSeccion}px;
                                border:1px solid #E2E2E2;
                                border-radius:0px 0px 5px 5px; 
                                border-top:none; 
                            ">
                                $camposAgrupacionConfiguradosVista
                            </div>
                        </div>
                        <div class="tab-pane fade" id="cabecera" role="tabpanel" aria-labelledby="cabecera-tab">
                            <div class="listadoTablaConfiguraciones" style="padding:1px; overflow-y:auto; transition:all 0.5s ease; height:{$altoSeccion}px;">
                                <div style=
                                "
                                    gap:3px;
                                    display:flex; 
                                    padding:15px 15px; 
                                    flex-direction:column;
                                    border:1px solid #E2E2E2;
                                    transition:all 0.5s ease;
                                    border-radius:0px 0px 5px 5px; 
                                    border-top:none;
                                ">
                                    <div id="divCabeceras" style="gap:3px; display:flex; flex-direction:column;">
                                        $cabeceraConfiguradaVista
                                    </div>
                                    <div class="divAgregarCabecera" data-action="click->central--reporteador#agregarCabecera" style=
                                    "   
                                        display:flex; 
                                        padding:10px; 
                                        margin-top:5px; 
                                        cursor:pointer;
                                        border-radius:5px; 
                                        align-items:center; 
                                        justify-content:center; 
                                        border:1px solid #d1d4da; 
                                        transition:all 0.5s ease;
                                        border-style:dashed; gap:5px; 
                                    ">
                                        <i class="fas fa-plus" style="font-size:9px; color:gray; transition:all 0.5s ease;"></i>
                                        <span style="font-size:10px; color:#808080e0; font-weight:bold; transition:all 0.5s ease;">Agregar cabecera</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="busquedaDinamica" role="tabpanel" aria-labelledby="busquedaDinamica-tab" >
                            <div class="listadoTablaConfiguraciones" style="padding:1px; overflow-y:auto; transition:all 0.5s ease; height:{$altoSeccion}px;">
                                <div style=
                                "
                                    gap:3px;
                                    display:flex; 
                                    padding:15px 15px; 
                                    flex-direction:column; 
                                    justify-content:center; 
                                    border:1px solid #E2E2E2;
                                    border-radius:0px 0px 5px 5px; 
                                    border-top:none; 
                                ">
                                    $camposBusquedaDinamicaConfiguradosVista
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="animate__animated animate__flipInX" style="display:flex; align-items:center; justify-content:space-between; margin-top:10px" id="botonesConfiguraciones">
                    <button class="btn btn-success" id="btnGuardarConfiguraciones" data-action="central--reporteador#guardarConfiguraciones"><i class="fas fa-save"></i> Guardar configuraciones</button>
                    <div style="display:flex; align-items:center; justify-content:center; gap:10px" id="divAplicarConfiguraciones">
                        <span class="montserrat" style="font-size:11px; font-weight:bold">Aplicar al descargar informe</span>
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input checkConfiguracion" id="aplicarConfiguracionesDescarga" data-action="central--reporteador#seleccionarCampoConfiguracion">
                            <label class="custom-control-label" for="aplicarConfiguracionesDescarga"></label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        TWIG;

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
                                    <span class="montserrat-text" style="font-size:11px; margin-left:4px; width:max-content">$direccionCompania</span>
                                </div>
                            </div>
                        </div>
                        <div class="animate__animated animate__fadeIn" style="position:relative; width:fit-content; display:flex; align-items:center; justify-content:center; gap:5px; flex:4; margin-left:20px">
                            <div style="display:flex; justify-content:center; flex-direction:column; gap:2px">
                                <div style="display:flex; align-items:center; gap:5px;">
                                    <i class="fas fa-info-circle" style="font-size:11px"></i>
                                    <span class="montserrat" style="font-size:13px;">$nombreInforme</span>
                                </div>
                                $periodo
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; justify-content:end; flex:4; position:relative">
                            <div class="menuReporteador" data-action="click->central--reporteador#showMenuReporteador" data-opc="0" style=
                                "
                                    width:0px; 
                                    height:0px; 
                                    $bloqueoMenu
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
                                    z-index:1;
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
                                <div class="itemMenu" style="cursor:pointer; border-radius:3px; display:flex; align-items:center; gap:10px; padding:5px 15px; width:100%; $bloqueoOpcionesDescarga" data-action="click->central--reporteador#descargarPDF">
                                    <div style="width:16px">
                                        <i class="far fa-file-pdf text-danger" style="font-size:15px"></i>
                                    </div>
                                    <i class="fas fa-angle-double-right flecha text-danger" style="opacity:0; font-size:9px; transition:all 0.5s ease"></i>
                                    <span class="montserrat-text" style="font-size:12px; margin-left:-19px; transition:all 0.5s ease">Descargar PDF</span>
                                </div>
                                <div class="itemMenu" style="cursor:pointer; border-radius:3px; display:flex; align-items:center; gap:10px; padding:5px 15px; width:100%; $bloqueoOpcionesDescarga" data-action="click->central--reporteador#descargarExcel">
                                    <div style="width:16px">
                                        <i class="far fa-file-excel text-success" style="font-size:15px"></i>
                                    </div>
                                    <i class="fas fa-angle-double-right flecha text-success" style="opacity:0; font-size:9px; transition:all 0.5s ease"></i>
                                    <span class="montserrat-text" style="font-size:12px; margin-left:-19px; transition:all 0.5s ease">Descargar EXCEL</span>
                                </div>
                                <hr style="width:95%; margin-top:10px; margin-bottom:9px">
                                <div class="itemMenu" style="cursor:pointer; border-radius:3px; display:flex; align-items:center; gap:10px; padding:5px 15px; width:100%;" data-action="click->central--reporteador#showConfiguraciones" id="opcionConfiguraciones">
                                    <div style="width:16px">
                                        <i class="fas fa-cog text-secondary" style="font-size:15px"></i>
                                    </div>
                                    <i class="fas fa-angle-double-right flecha text-secondary" style="opacity:0; font-size:9px; transition:all 0.5s ease"></i>
                                    <span class="montserrat-text" style="font-size:12px; margin-left:-19px; transition:all 0.5s ease">Configuraciones</span>
                                </div>
                            </div>
                            <div id="loaderDescargaInforme" style=
                            "
                                gap:7px; 
                                opacity:0;
                                width:230px; 
                                right:-260px;
                                display:flex; 
                                background:white; 
                                border-radius:6px;
                                position:absolute; 
                                padding:12px 15px; 
                                align-items:center; 
                                transition:all 1s ease;
                                border:1px solid #dfdfdf; 
                            ">
                                <div style="display:flex; align-items:center; justify-content:center; border-radius:5px; background:#DC354526; padding:7px" id="divIconoDescarga">
                                    <i class="far fa-file-pdf text-danger" style="font-size:15px"></i>
                                </div>
                                <div style="width:100%; display:flex; justify-content:center; gap:1px; flex-direction:column">
                                    <div style="display:flex; align-items:center; justify-content:center; gap:5px">
                                        <div class="progress" style="height:8px; width:100%">
                                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuemin="0" aria-valuemax="100" style="width: 0%" id="barraProgresoDescarga"></div>
                                        </div>
                                        <div class="montserrat" style="font-size:9px; width:30px; text-align:end" id="porcentajeDescarga">0%</div>
                                    </div>
                                    <span class="montserrat" style="font-size:10px;">Descargando informe</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:30px; margin-top:50px;">
                        <div class="animate__animated animate__fadeInLeft" style="position:relative; margin-left:15px; width:fit-content; display:flex; align-items:center; $bloqueoMenu">
                            <button id="btnBusquedaRapida" style="transition:all 0.5s ease; position:absolute; border-radius:50%; right:6px; background:#17A; color:white" class="btn btn-sm" data-action="central--reporteador#busquedaRapida" data-opc="1"><i class="fas fa-search" style="font-size:12px"></i></button>
                            <input id="busquedaRapida" class="form-control buscar montserrat-text" type="text" placeholder="Búsqueda rápida" data-central--reporteador-target="busquedaRapida" style=
                            "
                                width:220px; 
                                height:36px; 
                                font-size:12px; 
                                transition:all 0.5s ease;
                                padding:0px 53px 0px 19px;
                                border-radius:20px 20px 20px 5px; 
                            " data-action="keypress->central--reporteador#busquedaRapida" data-opc="2" value="$busquedaRapida">
                        </div>
                    </div>
                    <hr style="margin-left:15px; margin-right:15px">
                    <div class="animate__animated animate__fadeIn animate__delay-1s listadoTabla" style="margin-top:25px; padding:3px; overflow-y:auto; overflow-x:auto; transition:all 0.5s ease">
                        <div style="display:flex; align-items:center; justify-content:center; $anchoContenidoInforme">
                            $contenidoInforme
                        </div>
                    </div>
                </div>
            </div>
        </div>
        $contenidoPaginacion
        <input type="hidden" id="statusHidden" value="$status" data-central--reporteador-target="statusHidden">
        <input type="hidden" id="paginaHidden" value="$pagina" data-central--reporteador-target="paginaHidden">
        <input type="hidden" id="totalRegistrosHidden" value="$totalRegistros" data-central--reporteador-target="totalRegistrosHidden">
        <input type="hidden" id="busquedaRapidaHidden" value="$busquedaRapida" data-central--reporteador-target="busquedaRapidaHidden">
        TWIG;
        return new Response(json_encode(['status' => $status, 'message' => $message, 'plantilla' => $plantilla, 'seccionConfiguraciones' => $seccionConfiguraciones]));
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
        $cabecerasConfiguracion = !empty($this->configuracionesGuardadas)?$this->configuracionesGuardadas['cabeceras']:[];
        $camposGuardadosConfiguracion = !empty($this->configuracionesGuardadas)?$this->configuracionesGuardadas['campos']:[];
        if(!empty($camposGuardadosConfiguracion))
        {
            foreach($camposTotalizados as $key => $campo)
            {   
                if(!in_array($key, $camposGuardadosConfiguracion))
                {
                    unset($camposTotalizados[$key]);
                    unset($this->camposTotalizados[$key]);
                }
            }
        }

        /** Se obtiene el json que contiene las configuraciones del informe */
        /** --------------------------------------------------------------- */

        if(!empty($configuraciones))
        {
            if(array_key_exists('campos', $configuraciones)){$configuracionCampos = $configuraciones['campos'];}
            if(array_key_exists('cabecera', $configuraciones) && is_array($configuraciones['cabecera']) && !empty($configuraciones['cabecera'])){$cabecera = $configuraciones['cabecera'];}
            if(array_key_exists('totalizacion', $configuraciones['agrupamiento'][0]) && !empty($configuraciones['agrupamiento'][0]['totalizacion']) && is_array($configuraciones['agrupamiento'][0]['totalizacion']))
            {
                if(empty($this->configuracionesGuardadas) || !empty($this->configuracionesGuardadas['agrupacion'])){$camposTotalizados = [];}
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
                    /** Se valida si existen campos guardados en las configuraciones */
                    /** ------------------------------------------------------------ */

                    if(!empty($camposGuardadosConfiguracion))
                    {
                        if(!in_array($ct['campo'], $camposGuardadosConfiguracion))
                        {
                            continue;
                        }
                    }

                    /** Se obtienen los campos de totalización */
                    /** -------------------------------------- */

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
            $rellenoCampo = (($indexRegistro + 1) % 2 == 0) ? '#17A2B814':'';
            foreach($registro as $key => $campo)
            {
                /** Se valida si existen campos guardados en configuraciones */
                /** -------------------------------------------------------- */

                if(!empty($camposGuardadosConfiguracion))
                {
                    $registro = $camposGuardadosConfiguracion;
                    if(!in_array($key, $camposGuardadosConfiguracion)){continue;}
                }

                /** Se crean los títulos del informe con sus respectivos estilos */
                /** ------------------------------------------------------------ */
                
                $tipoDato = 'texto';
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
                        $tipoDato = $configuracionCampo[0]['tipoDato'];
                    }

                    if(array_key_exists('tipoDato', $configuracionCampo[0]) && $configuracionCampo[0]['tipoDato'] == 'numero')
                    {
                        $campo = number_format($campo, 2, '.', '');
                        $tipoDato = $configuracionCampo[0]['tipoDato'];
                    }

                    if(array_key_exists('tipoDato', $configuracionCampo[0]) && $configuracionCampo[0]['tipoDato'] == 'fecha')
                    {
                        $tipoDato = $configuracionCampo[0]['tipoDato'];
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

                        if(array_key_exists('ruta', $configuracionCampo[0]) && is_array($configuracionCampo[0]['ruta']) && !empty($configuracionCampo[0]['ruta']) && array_key_exists('nombre', $configuracionCampo[0]['ruta']))
                        {
                            /** Se valida si existen parámetros configurados */
                            /** -------------------------------------------- */

                            $parametros = [];
                            if(array_key_exists('parametros', $configuracionCampo[0]['ruta']) && is_array($configuracionCampo[0]['ruta']['parametros']) && !empty($configuracionCampo[0]['ruta']['parametros']))
                            {
                                $parametros = str_replace('$campo', $campo, json_encode($configuracionCampo[0]['ruta']['parametros']));
                                $parametros = json_decode($parametros, true);
                            }
                            $rutaCampo = $ruta.$this->generateUrl($configuracionCampo[0]['ruta']['nombre'], $parametros);
                            $html = str_replace('$ruta', $rutaCampo, $html);
                        }
                        $campo = $html;
                    }
                }

                if($index == 1)
                {
                    $estiloBordesCampo = 'border-left:1px solid #d0d4da';
                    $claseTitulo = (empty($cabecera) || (!empty($this->configuracionesGuardadas) && empty($cabecerasConfiguracion)))?'class="tituloInicial"':'';
                    $estiloBordesTitulo = (empty($cabecera) || (!empty($this->configuracionesGuardadas) && empty($cabecerasConfiguracion)))?'border-radius:10px 0px 0px 3px':'';
                }

                if($index == count($registro))
                {
                    $estiloBordesCampo = 'border-right:1px solid #d0d4da';
                    $claseTitulo = (empty($cabecera) || (!empty($this->configuracionesGuardadas) && empty($cabecerasConfiguracion)))?'class="tituloFinal"':'';
                    $estiloBordesTitulo = (empty($cabecera) || (!empty($this->configuracionesGuardadas) && empty($cabecerasConfiguracion)))?'border-radius:0px 10px 3px 0px; border-right:1px solid #d0d4da':'border-right:1px solid #d0d4da';
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

                /** Se guarda la información de cada título para crear los campos de configuraciones */
                /** -------------------------------------------------------------------------------- */

                $this->camposConfigurados[$key] = 
                [
                    'agrupacion' => false,
                    'tipoDato' => $tipoDato, 
                    'titulo' => empty(strip_tags($titulo))?ucfirst(str_replace('_', ' ', $key)):strip_tags($titulo)
                ];
            }
            $filasInforme .=
            <<<TWIG
                <tr class="registroInfome" style="transition:all 0.2s ease; background:$rellenoCampo">
                    $tdCampo
                </tr>
            TWIG;
            $tdCampo = '';
            $index = 1;
        }

        /** Se crea la sección de la cabecera */
        /** --------------------------------- */
        
        if((!empty($cabecera) && empty($this->configuracionesGuardadas)) || !empty($cabecerasConfiguracion))
        {
            $cabeceras = $cabecera;
            $keyCabeceras = array_keys($cabecera);
            if(!empty($cabecerasConfiguracion)){$cabeceras = $cabecerasConfiguracion;}
            foreach($cabeceras as $index => $c)
            {
                if(array_key_exists('index', $c))
                {
                    $tituloCabecera = $c['nombre'];
                    $colSpanCabecera = $c['colspan'];    
                    if(in_array($c['index'], $keyCabeceras))
                    {
                        $cabeceraTexto = strip_tags($cabecera[$c['index']]['nombre']);
                        $tituloCabecera = str_replace($cabeceraTexto, $c['nombre'], $cabecera[$c['index']]['nombre']);
                    }
                }
                else
                {
                    $tituloCabecera = $c['nombre'];
                    $colSpanCabecera = $c['colspan'];
                }

                if($index == 0)
                {
                    $estiloBordesTitulo = 'border-radius:10px 0px 0px 0px';
                }

                if($index == (count($cabeceras) - 1))
                {
                    $estiloBordesTitulo = 'border-radius:0px 10px 0px 0px; border-right:1px solid #d0d4da';
                }

                if(count($cabeceras) == 1)
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
                $estiloBordesTitulo = '';
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

    /**
    * @Route("/Central/Reporteador/descargarInformePDF", name="central_reporteador_descargar_informe_pdf")
    */
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
        $configuracionCampos = [];
        $pdfOptions = new Options();
        $conexion = $bd->getConnection();
        $session = $request->getSession();
        $listRegistrosBusquedaRapida = [];
        $listRegistrosBusquedaDinamica = [];
        $form = $request->request->get('filtros_reporteador');
        $busquedaRapida = $request->request->get('busquedaRapida');
        $compania = $bd->getRepository(compania::class)->findOneBy([]);
        $alineaciones = ['centro' => 'center', 'derecha' => 'right', 'izquierda' => 'left'];
        $informe = $bd->getRepository(reportes::class)->findOneBy(['id' => $form['informe']]);
        $pdfOptions->set('defaultFont', 'Helvetica')->set('sizeFont', '9')->setIsRemoteEnabled(true);
        $fechaActual = (new \DateTime('now', new \DateTimeZone('America/Bogota')))->format('Y-m-d H:i:s');
        $this->configuracionesGuardadas = !empty($request->request->get('configuracionesGuardadas'))?json_decode($request->request->get('configuracionesGuardadas'), true):[];

        try 
        {            
            /** Se obtienen los filtros de búsqueda seleccionados */
            /** ------------------------------------------------- */

            $sqlInforme = $informe->getSql();
            $nitCompania = $compania->getNit();
            $logo = $compania->getLogocompania();
            $nombreInforme = $informe->getNombre();
            $telefonoCompania = $compania->getTelefonos();
            $direccionCompania = $compania->getDireccion();
            $nombreCompania = strtoupper($compania->getNombre());
            preg_match_all('/\[(.*?)\]/', $sqlInforme, $camposSQL);
            foreach($form as $key => $campo){$filtros['['.$key.']'] = !empty($campo)?$campo:-1;}

            /** Se valida si las variables definidas en el sql se encuentran en los filtros de búsqueda */
            /** --------------------------------------------------------------------------------------- */

            foreach($camposSQL[0] as $campoSQL)
            {
                if(!array_key_exists($campoSQL, $filtros))
                {
                    $filtros[$campoSQL] = '-1';
                }
            }
            $sqlInforme = strtr($sqlInforme, $filtros);

            /** Se obtiene el json que contiene las configuraciones del informe */
            /** --------------------------------------------------------------- */

            $tablaTotales['colspan'] = 0;
            $configuraciones = $informe->getJson();
            if(!empty($configuraciones))
            {
                if(array_key_exists('campos', $configuraciones)){$configuracionCampos = $configuraciones['campos'];}
                if(array_key_exists('pdf', $configuraciones) && !empty($configuraciones['pdf']) && is_array($configuraciones['pdf'])){$configuracionesPDF = $configuraciones['pdf'];}
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

            $listRegistros = $conexion->prepare($sqlInforme)->executeQuery()->fetchAll();
            if($busquedaRapida != '')
            {
                /** Se filtran los registros de acuerdo a la búsqueda rápida */
                /** -------------------------------------------------------- */

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
            else
            {
                /** Se filtran los registro de acuerdo a la búsqueda dinámica */
                /** --------------------------------------------------------- */

                $condicionesValidas = 0;
                if(!empty($this->configuracionesGuardadas) && !empty($this->configuracionesGuardadas['busquedaDinamica']))
                {
                    foreach($listRegistros as $registro)
                    {
                        foreach($this->configuracionesGuardadas['busquedaDinamica'] as $busqueda)
                        {
                            $campoBusqueda = $busqueda['campo'];
                            if(!empty($busqueda['input']))
                            {
                                if($busqueda['tipo'] == 'fecha')
                                {
                                    if($busqueda['select'] == 'igual')
                                    {
                                        if(new \DateTime($busqueda['input']) == new \DateTime($registro[$campoBusqueda])){$condicionesValidas ++;}
                                    }
                                    if($busqueda['select'] == 'entre')
                                    {
                                        if((new \DateTime($registro[$campoBusqueda]) >= new \DateTime($busqueda['input'])) && (new \DateTime($registro[$campoBusqueda]) <= new \DateTime($busqueda['hasta'])))
                                        {
                                            $condicionesValidas ++;
                                        }
                                    }
                                    if($busqueda['select'] == 'mayor')
                                    {
                                        if(new \DateTime($registro[$campoBusqueda]) > new \DateTime($busqueda['input'])){$condicionesValidas ++;}
                                    }
                                    if($busqueda['select'] == 'menor')
                                    {
                                        if(new \DateTime($registro[$campoBusqueda]) < new \DateTime($busqueda['input'])){$condicionesValidas ++;}
                                    }
                                }
                                else
                                {
                                    if($busqueda['select'] == 'igual')
                                    {
                                        if($busqueda['input'] == $registro[$campoBusqueda]){$condicionesValidas ++;}
                                    }
                                    if($busqueda['select'] == 'mayor')
                                    {
                                        if($registro[$campoBusqueda] > $busqueda['input']){$condicionesValidas ++;}
                                    }
                                    if($busqueda['select'] == 'menor')
                                    {
                                        if($registro[$campoBusqueda] < $busqueda['input']){$condicionesValidas ++;}
                                    }
                                    if($busqueda['select'] == 'contiene')
                                    {
                                        if(strpos($registro[$campoBusqueda], $busqueda['input']) !== false){$condicionesValidas ++;}
                                    }
                                    if($busqueda['select'] == 'no_contiene')
                                    {
                                        if(strpos($registro[$campoBusqueda], $busqueda['input']) === false){$condicionesValidas ++;}
                                    }
                                }
                            }
                            else
                            {
                                if($registro[$campoBusqueda] == ''){$condicionesValidas ++;}
                            }
                        }
                        if($condicionesValidas == count($this->configuracionesGuardadas['busquedaDinamica']))
                        {
                            $listRegistrosBusquedaDinamica[] = $registro;
                        }
                        $condicionesValidas = 0;
                    }
                    $listRegistros = $listRegistrosBusquedaDinamica;
                }
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

            if(!empty($this->configuracionesGuardadas)){$camposAgrupacion = $this->configuracionesGuardadas['agrupacion'];}
            if(!empty($camposAgrupacion))
            {
                /** Se genera el informe con campos de agrupación */
                /** --------------------------------------------- */
                
                $listAgrupada = [];
                $campoControl = '';
                $campoAnterior = '';
                $camposReferencia = [];
                $divTotalesGenerales = '';
                $camposAgrupacion = array_slice($camposAgrupacion, 0, 3);
                
                /** Se ordena la información de acuerdo a los campos de agrupación configurados en el informe */
                /** ----------------------------------------------------------------------------------------- */

                $keyAgrupacion = [];
                foreach($listRegistros as $registro)
                {
                    foreach($camposAgrupacion as $campo)
                    {
                        $keyAgrupacion[] = $registro[$campo];
                        unset($registro[$campo]);
                    }
                    $keyAgrupacion = implode('_', $keyAgrupacion);
                    $listAgrupada[$keyAgrupacion][] = $registro;
                    $keyAgrupacion = [];
                }

                /** Se crea la sección de agrupamiento con todos los campos seleccionados */
                /** --------------------------------------------------------------------- */

                $tituloAgrupado = [];
                $divAgrupacionGeneral = '';
                $camposAgrupadosCabecera = [];
                foreach($listAgrupada as $key => $registros)
                {
                    $camposAgrupadosCabecera = explode('_', $key);
                    foreach($camposAgrupadosCabecera as $keyCampoAgrupado => $campoAgrupado)
                    {
                        $key = $camposAgrupacion[$keyCampoAgrupado];
                        $campo = array_filter($agrupamiento[0]['campos'], fn($item) => $item['nombre'] == $key);
                        sort($campo);
                        $titulo = strip_tags($campo[0]['titulo']);
                        $campoAgrupado = explode('-', $campoAgrupado);
                        $campoAgrupado = (count($campoAgrupado) > 1)?$campoAgrupado[1]:$campoAgrupado[0];
                        $tituloAgrupado[] = $titulo.' » '.$campoAgrupado;
                    }
                    $tituloAgrupado = implode('  |  ', $tituloAgrupado);

                    /** Se crea la tabla de cada agrupación con sus respectivos registros */
                    /** ----------------------------------------------------------------- */

                    $tablaRegistros = $this->crearTablaRegistrosPDF($request, $configuraciones, $registros, true);                    
                    $divAgrupacionGeneral .=
                    <<<TWIG
                    <div style=
                    "
                        margin-top:5px;
                        font-weight:bold;
                        padding:12px 17px;
                        background:#f2f2f2;
                        border:1px solid gray; 
                        border-right:1px solid gray; 
                        border-radius:5px 5px 0px 0px; 
                    ">
                        $tituloAgrupado
                    </div>
                    <div style="border: 1px solid gray; padding:10px; border-radius: 0px 0px 5px 5px; margin-top:-1px;">
                        $tablaRegistros
                    </div>
                    TWIG;
                    $tituloAgrupado = [];
                }

                /** Se genera la sección de totales obtenidos a partir de los campos de agrupación */
                /** ------------------------------------------------------------------------------ */

                if(!empty($this->camposTotalizados))
                {
                    $indexTotales = 0;
                    foreach($this->camposTotalizados as $ct)
                    {
                        $tituloTotal = $ct[0];
                        $valorTotal = number_format($ct[1], 2, ',', '.');
                        $borderTop = ($indexTotales == 0)?'':'border-top:none;';
                        $divTotalesGenerales .= 
                        <<<TWIG
                            <tr>
                                <td style="text-align:center; padding:5px 7px; border:1px solid gray; $borderTop">
                                    $tituloTotal
                                </td>
                                <td style="text-align:right; padding:5px 7px; border:1px solid gray; border-left:none; $borderTop">
                                    $valorTotal
                                </td>
                            </tr>
                        </div>
                        TWIG;
                        $indexTotales ++;
                    }
                    $divTotalesGenerales =
                    <<<TWIG
                    <div style="margin-top:20px;">
                        <div style="background:#f2f2f2; text-align:center; font-weight:bold; padding:7px; border:1px solid gray; border-bottom:none; border-radius:5px 5px 0px 0px;">
                            TOTALES DEL INFORME
                        </div>
                        <table border="0" cellpadding="0" cellspacing="0" style="width:100%">
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
            $session->set('errorDescargaInforme', 
            [
                'line' => $e->getLine(), 
                'file' => $e->getFile(), 
                'message' => $e->getMessage()
            ]);    
        }

        /** Se asignan las configuraciones del PDF */
        /** -------------------------------------- */

        $tipoHoja = 'letter';
        $orientacion = 'portrait';
        $anchoInformacionEmpresa = '350px';
        if(!empty($configuracionesPDF))
        {
            if(array_key_exists('tipoHoja', $configuracionesPDF) && !empty($configuracionesPDF['tipoHoja'])){$tipoHoja = $configuracionesPDF['tipoHoja'];}
            if(array_key_exists('orientacion', $configuracionesPDF) && !empty($configuracionesPDF['orientacion'])){$orientacion = $configuracionesPDF['orientacion'];}
            if($orientacion == 'landscape'){$anchoInformacionEmpresa = '450px';}
        }

        /** Se genera la plantilla del informe */
        /** ---------------------------------- */

        $cabecera = $this->renderView('Central/Reporteador/cabeceraInformePDF.html.twig', 
        [
            'periodo' => $periodo,
            'compania' => $compania, 
            'fecha' => $fechaActual,
            'nombreInforme' => $nombreInforme,
            'anchoInformacionEmpresa' => $anchoInformacionEmpresa
        ]);
        $html =
        <<<TWIG
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    font-family: Helvetica, Arial, sans-serif;
                    font-size: 10px;
                    margin: 0;
                    padding: 0;
                }
                .contenido {
                    margin-top: 20px;
                }
            </style>
        </head>
        <body>
            <div class="contenido">
                $contenidoPDF
            </div>
        </body>
        </html>
        TWIG;
        $pdf = $this->pdf->getOutputFromHtml($html, 
        [
            'dpi' => 96,
            'margin-top' => 30,
            'margin-left' => 5,
            'margin-right' => 5,
            'margin-bottom' => 10,
            'footer-font-size' => 4,
            'page-size' => $tipoHoja,
            'header-html' => $cabecera,
            'orientation' => $orientacion,
            'footer-font-name' => 'Helvetica',
            'enable-local-file-access' => true,
            'footer-center' => 'Pagina [page] de [toPage]',
        ]);
        return new Response(
            $pdf,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => ResponseHeaderBag::DISPOSITION_ATTACHMENT
            ]
        );
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
        $cabecerasConfiguracion = !empty($this->configuracionesGuardadas)?$this->configuracionesGuardadas['cabeceras']:[];
        $camposGuardadosConfiguracion = !empty($this->configuracionesGuardadas)?$this->configuracionesGuardadas['campos']:[];
        if(!empty($camposGuardadosConfiguracion))
        {
            foreach($camposTotalizados as $key => $campo)
            {   
                if(!in_array($key, $camposGuardadosConfiguracion))
                {
                    unset($camposTotalizados[$key]);
                    unset($this->camposTotalizados[$key]);
                }
            }
        }

        /** Se obtiene el json que contiene las configuraciones del informe */
        /** --------------------------------------------------------------- */

        if(!empty($configuraciones))
        {
            if(array_key_exists('campos', $configuraciones)){$configuracionCampos = $configuraciones['campos'];}
            if(array_key_exists('cabecera', $configuraciones) && is_array($configuraciones['cabecera']) && !empty($configuraciones['cabecera'])){$cabecera = $configuraciones['cabecera'];}
            if(array_key_exists('totalizacion', $configuraciones['agrupamiento'][0]) && !empty($configuraciones['agrupamiento'][0]['totalizacion']) && is_array($configuraciones['agrupamiento'][0]['totalizacion']))
            {
                if(empty($this->configuracionesGuardadas) || !empty($this->configuracionesGuardadas['agrupacion'])){$camposTotalizados = [];}
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
                    /** Se valida si existen campos guardados en las configuraciones */
                    /** ------------------------------------------------------------ */
                    
                    if(!empty($camposGuardadosConfiguracion))
                    {
                        if(!in_array($ct['campo'], $camposGuardadosConfiguracion))
                        {
                            continue;
                        }
                    }

                    /** Se obtienen los campos de totalización */
                    /** -------------------------------------- */

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
            $rellenoCampo = (($indexRegistro + 1) % 2 == 0) ? '#17A2B814':'';
            foreach($registro as $key => $campo)
            {
                /** Se valida si existen campos guardados en configuraciones */
                /** -------------------------------------------------------- */

                if(!empty($camposGuardadosConfiguracion))
                {
                    $registro = $camposGuardadosConfiguracion;
                    if(!in_array($key, $camposGuardadosConfiguracion)){continue;}
                }

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

                    if(array_key_exists('ruta', $configuracionCampo[0]) && is_array($configuracionCampo[0]['ruta']) && !empty($configuracionCampo[0]['ruta']) && array_key_exists('nombre', $configuracionCampo[0]['ruta']))
                    {
                        $parametros = [];
                        $alineacionCampo = 'center';
                        if(array_key_exists('parametros', $configuracionCampo[0]['ruta']) && is_array($configuracionCampo[0]['ruta']['parametros']) && !empty($configuracionCampo[0]['ruta']['parametros']))
                        {
                            $parametros = str_replace('$campo', $campo, json_encode($configuracionCampo[0]['ruta']['parametros']));
                            $parametros = json_decode($parametros, true);
                        }
                        $rutaCampo = $ruta.$this->generateUrl($configuracionCampo[0]['ruta']['nombre'], $parametros);
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
                    $borderLeft = ($index == 1)?'':'border-left:none';
                    $titulosPDF .=
                    <<<TWIG
                    <td style="font-weight:bold; padding:4px; text-align:$alineacionTitulo; background:#f2f2f2; border:1px solid gray; $borderLeft; border-bottom:none">$titulo</td>
                    TWIG;
                    $divRelleno = '';
                    $claseTitulo = '';
                    $estiloBordesTitulo = '';
                }

                /** Se crea cada registro del informe */
                /** --------------------------------- */

                $borderLeft = ($index == 1)?'':'border-left:none';
                $borderTop = ($indexRegistro == 0)?'':'border-top:none';
                $tdCampo .= 
                <<<TWIG
                <td style="padding:3px 7px; border:1px solid gray; text-align:$alineacionCampo; $borderTop; $borderLeft">$campo</td>
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
        
        if((!empty($cabecera) && empty($this->configuracionesGuardadas)) || !empty($cabecerasConfiguracion))
        {
            $cabeceras = $cabecera;
            if(!empty($cabecerasConfiguracion)){$cabeceras = $cabecerasConfiguracion;}
            foreach($cabeceras as $index => $c)
            {
                $colSpanCabecera = $c['colspan'];
                $tituloCabecera = strip_tags($c['nombre']);

                if($index == 0)
                {
                    $estiloBordesTitulo = 'border-radius:5px 0px 0px 0px';
                }

                if($index == (count($cabeceras) - 1))
                {
                    $estiloBordesTitulo = 'border-radius:0px 5px 0px 0px; border-right:1px solid gray';
                }

                if(count($cabeceras) == 1)
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
                $estiloBordesTitulo = 'border-radius:1px 1px 0px 0px';
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
                        <div style="background:#f2f2f2; text-align:right; padding:7px; height:12px; border:1px solid gray; border-right:none; border-top:none; border-radius:0px 0px 0px 5px">
                            Total &raquo;
                        </div>
                    </th>
                    TWIG;
                }
                else
                {
                    $campo = !empty($campoTotal)?$campoTotal[0]:'';
                    $alineacionCampo = !empty($campoTotal)?$campoTotal[1]:'';
                    $estiloBordes = ($index == (count($tablaTotales) - 1))?'border-radius:0px 0px 5px 0px; border-right:1px solid gray; border-bottom:1px solid gray; border-left:none;':'border-radius:1px; border-right:none; border-bottom:1px solid gray;';
                    $tdTotal .= 
                    <<<TWIG
                    <th>
                        <div style="background:#f2f2f2; text-align:$alineacionCampo; padding:7px; height:12px; border:none; border-top:none; $estiloBordes">
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

    /**
    * @Route("/Central/Reporteador/descargarInformeExcel", name="central_reporteador_descargar_informe_excel")
    */
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
        $fsObject = new Filesystem();
        $fechaCabecera = new RichText();
        $spreadsheet = new Spreadsheet();
        $conexion = $bd->getConnection();
        $periodoCabecera = new RichText();
        $listRegistrosBusquedaRapida = [];
        $session = $request->getSession();
        $listRegistrosBusquedaDinamica = [];
        $sheet = $spreadsheet->getActiveSheet();
        $logoTmp = tempnam(sys_get_temp_dir(), 'logoTmp');
        $rutaLogo = $this->getParameter('imgs_directory');
        $form = $request->request->get('filtros_reporteador');
        $busquedaRapida = $request->request->get('busquedaRapida');
        $compania = $bd->getRepository(compania::class)->findOneBy([]);
        $alineaciones = ['centro' => 'center', 'derecha' => 'right', 'izquierda' => 'left'];
        $informe = $bd->getRepository(reportes::class)->findOneBy(['id' => $form['informe']]);
        $fechaActual = (new \DateTime('now', new \DateTimeZone('America/Bogota')))->format('Y-m-d H:i:s');
        $this->configuracionesGuardadas = !empty($request->request->get('configuracionesGuardadas'))?json_decode($request->request->get('configuracionesGuardadas'), true):[];

        try 
        {
            /** Se obtienen los filtros de búsqueda seleccionados */
            /** ------------------------------------------------- */
            
            $sqlInforme = $informe->getSql();
            $nitCompania = $compania->getNit();
            $logo = $compania->getLogocompania();
            $nombreInforme = $informe->getNombre();
            $telefonoCompania = $compania->getTelefonos();
            $direccionCompania = $compania->getDireccion();
            $nombreCompania = strtoupper($compania->getNombre());
            preg_match_all('/\[(.*?)\]/', $sqlInforme, $camposSQL);
            $logoCompania = base64_decode($compania->getLogocompania());
            foreach($form as $key => $campo){$filtros['['.$key.']'] = !empty($campo)?$campo:-1;}
            file_put_contents($logoTmp, $logoCompania);

            /** Se valida si las variables definidas en el sql se encuentran en los filtros de búsqueda */
            /** --------------------------------------------------------------------------------------- */

            foreach($camposSQL[0] as $campoSQL)
            {
                if(!array_key_exists($campoSQL, $filtros))
                {
                    $filtros[$campoSQL] = '-1';
                }
            }
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

            $listRegistros = $conexion->prepare($sqlInforme)->executeQuery()->fetchAll();
            if($busquedaRapida != '')
            {
                /** Se filtran los registros de acuerdo a la búsqueda rápida */
                /** -------------------------------------------------------- */

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
            else
            {
                /** Se filtran los registro de acuerdo a la búsqueda dinámica */
                /** --------------------------------------------------------- */

                $condicionesValidas = 0;
                if(!empty($this->configuracionesGuardadas) && !empty($this->configuracionesGuardadas['busquedaDinamica']))
                {
                    foreach($listRegistros as $registro)
                    {
                        foreach($this->configuracionesGuardadas['busquedaDinamica'] as $busqueda)
                        {
                            $campoBusqueda = $busqueda['campo'];
                            if(!empty($busqueda['input']))
                            {
                                if($busqueda['tipo'] == 'fecha')
                                {
                                    if($busqueda['select'] == 'igual')
                                    {
                                        if(new \DateTime($busqueda['input']) == new \DateTime($registro[$campoBusqueda])){$condicionesValidas ++;}
                                    }
                                    if($busqueda['select'] == 'entre')
                                    {
                                        if((new \DateTime($registro[$campoBusqueda]) >= new \DateTime($busqueda['input'])) && (new \DateTime($registro[$campoBusqueda]) <= new \DateTime($busqueda['hasta'])))
                                        {
                                            $condicionesValidas ++;
                                        }
                                    }
                                    if($busqueda['select'] == 'mayor')
                                    {
                                        if(new \DateTime($registro[$campoBusqueda]) > new \DateTime($busqueda['input'])){$condicionesValidas ++;}
                                    }
                                    if($busqueda['select'] == 'menor')
                                    {
                                        if(new \DateTime($registro[$campoBusqueda]) < new \DateTime($busqueda['input'])){$condicionesValidas ++;}
                                    }
                                }
                                else
                                {
                                    if($busqueda['select'] == 'igual')
                                    {
                                        if($busqueda['input'] == $registro[$campoBusqueda]){$condicionesValidas ++;}
                                    }
                                    if($busqueda['select'] == 'mayor')
                                    {
                                        if($registro[$campoBusqueda] > $busqueda['input']){$condicionesValidas ++;}
                                    }
                                    if($busqueda['select'] == 'menor')
                                    {
                                        if($registro[$campoBusqueda] < $busqueda['input']){$condicionesValidas ++;}
                                    }
                                    if($busqueda['select'] == 'contiene')
                                    {
                                        if(strpos($registro[$campoBusqueda], $busqueda['input']) !== false){$condicionesValidas ++;}
                                    }
                                    if($busqueda['select'] == 'no_contiene')
                                    {
                                        if(strpos($registro[$campoBusqueda], $busqueda['input']) === false){$condicionesValidas ++;}
                                    }
                                }
                            }
                            else
                            {
                                if($registro[$campoBusqueda] == ''){$condicionesValidas ++;}
                            }
                        }
                        if($condicionesValidas == count($this->configuracionesGuardadas['busquedaDinamica']))
                        {
                            $listRegistrosBusquedaDinamica[] = $registro;
                        }
                        $condicionesValidas = 0;
                    }
                    $listRegistros = $listRegistrosBusquedaDinamica;
                }
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

            if(!empty($this->configuracionesGuardadas)){$camposAgrupacion = $this->configuracionesGuardadas['agrupacion'];}
            if(!empty($camposAgrupacion))
            {
                /** Se genera el informe con campos de agrupación */
                /** --------------------------------------------- */
                
                $listAgrupada = [];
                $keyAgrupacion = [];
                $divTotalesGenerales = '';
                $camposAgrupacion = array_slice($camposAgrupacion, 0, 3);
                
                /** Se ordena la información de acuerdo a los campos de agrupación configurados en el informe */
                /** ----------------------------------------------------------------------------------------- */
        
                foreach($listRegistros as $registro)
                {
                    foreach($camposAgrupacion as $campo)
                    {
                        $keyAgrupacion[] = $registro[$campo];
                        unset($registro[$campo]);
                    }
                    $keyAgrupacion = implode('_', $keyAgrupacion);
                    $listAgrupada[$keyAgrupacion][] = $registro;
                    $keyAgrupacion = [];
                }

                /** Se crea la sección de agrupamiento con todos los campos seleccionados */
                /** --------------------------------------------------------------------- */

                $this->ultimaColumna = Coordinate::stringFromColumnIndex(count($listAgrupada[array_key_first($listAgrupada)][0]));
                foreach($listAgrupada as $key => $items)
                {
                    $tituloAgrupado = [];
                    $camposAgrupadosCabecera = [];
                    $camposAgrupadosCabecera = explode('_', $key);
                    foreach($camposAgrupadosCabecera as $keyCampoAgrupado => $campoAgrupado)
                    {
                        $key = $camposAgrupacion[$keyCampoAgrupado];
                        $campo = array_filter($agrupamiento[0]['campos'], fn($item) => $item['nombre'] == $key);
                        sort($campo);
                        $titulo = strip_tags($campo[0]['titulo']);
                        $campoAgrupado = explode('-', $campoAgrupado);
                        $campoAgrupado = (count($campoAgrupado) > 1)?$campoAgrupado[1]:$campoAgrupado[0];
                        $tituloAgrupado[] = $titulo.' » '.$campoAgrupado;
                    }
                    $tituloAgrupado = implode('  |  ', $tituloAgrupado);

                    /** Se crea la tabla de registros para cada agrupación */
                    /** -------------------------------------------------- */

                    $this->crearTablaAgrupadaExcel($request, $tituloAgrupado, $sheet, $configuraciones, $items);
                }

                /** Se genera la sección de totales obtenidos a partir de los campos de agrupación */
                /** ------------------------------------------------------------------------------ */

                if(!empty($this->camposTotalizados))
                {
                    $sheet->getRowDimension($this->filaGeneral)->setRowHeight(25);
                    $totalColumnas = Coordinate::columnIndexFromString($this->ultimaColumna);
                    $columnaInicial = Coordinate::stringFromColumnIndex($totalColumnas - 1);
                    $sheet->setCellValue($columnaInicial.$this->filaGeneral, 'TOTALES DEL INFORME');
                    $sheet->mergeCells($columnaInicial.$this->filaGeneral.':'.$this->ultimaColumna.$this->filaGeneral);
                    $sheet->getStyle($columnaInicial.$this->filaGeneral.':'.$this->ultimaColumna.$this->filaGeneral)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                    $sheet->getStyle($columnaInicial.$this->filaGeneral.':'.$this->ultimaColumna.$this->filaGeneral)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                    /** Se aplican estilos a los títulos del informe */
                    /** -------------------------------------------- */

                    $sheet->getStyle($columnaInicial.$this->filaGeneral.':'.$this->ultimaColumna.$this->filaGeneral)->getFill()
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
                                'color' => ['argb' => 'FFB0B0B0'],
                            ],
                        ],
                    ];
                    $sheet->getStyle($columnaInicial.$this->filaGeneral.':'.$this->ultimaColumna.$this->filaGeneral)->getFont()->setBold(true)->setSize(11);
                    $sheet->getStyle($columnaInicial.$this->filaGeneral.':'.$this->ultimaColumna.$this->filaGeneral)->applyFromArray($styles);
                    $this->filaGeneral ++;

                    foreach($this->camposTotalizados as $ct)
                    {
                        $sheet->getRowDimension($this->filaGeneral)->setRowHeight(20);
                        $sheet->getStyle($columnaInicial.$this->filaGeneral.':'.$this->ultimaColumna.$this->filaGeneral)->applyFromArray($styles, false);
                        $sheet->getStyle($columnaInicial.$this->filaGeneral.':'.$this->ultimaColumna.$this->filaGeneral)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                        $sheet->getStyle($columnaInicial.$this->filaGeneral.':'.$this->ultimaColumna.$this->filaGeneral)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);    

                        /** Se asignan estilos a cada campo */
                        /** ------------------------------- */
        
                        $styles = 
                        [
                            'borders' => 
                            [
                                'right' => 
                                [
                                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                    'color' => ['argb' => 'FFB0B0B0'],
                                ],
                                'bottom' => 
                                [
                                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                    'color' => ['argb' => 'FFB0B0B0'],
                                ]
                            ],
                        ];

                        /** Se asigna el título y el valor de cada total */
                        /** -------------------------------------------- */

                        $tituloTotal = $ct[0];
                        $valorTotal = number_format($ct[1], 2, ',', '.');
                        $sheet->setCellValue($columnaInicial.$this->filaGeneral, $tituloTotal);
                        $sheet->setCellValue($this->ultimaColumna.$this->filaGeneral, $valorTotal);
                        $this->filaGeneral ++;
                    }
                }
            }
            else
            {
                /** Se genera el informe sin campos de agrupacion */
                /** --------------------------------------------- */
                
                $this->crearTablaRegistrosExcel($request, $configuraciones, $listRegistros, false, $sheet);
            }
        } 
        catch(\Exception $e) 
        {
            $status = 'error';
            $session->set('errorDescargaInforme', 
            [
                'line' => $e->getLine(), 
                'file' => $e->getFile(), 
                'message' => $e->getMessage()
            ]);
        }

        $ultimaColumna = $this->ultimaColumna; 
        $sheet->getRowDimension('1')->setRowHeight(35);
        $sheet->getRowDimension('2')->setRowHeight(20);
        $sheet->getRowDimension('3')->setRowHeight(20);
        $sheet->getRowDimension('4')->setRowHeight(20);
        $sheet->getRowDimension('5')->setRowHeight(20);
        if(empty($camposAgrupacion)){$sheet->getRowDimension('6')->setRowHeight(25);}

        $sheet->mergeCells('A1:A4');
        $sheet->mergeCells('A5:'.$ultimaColumna.'5');
        $sheet->mergeCells('B1:'.$ultimaColumna.'1');
        $sheet->mergeCells('B2:'.$ultimaColumna.'2');
        $sheet->mergeCells('B3:'.$ultimaColumna.'3');
        $sheet->mergeCells('B4:'.$ultimaColumna.'4');
        $sheet->mergeCells('B5:'.$ultimaColumna.'5');
        $sheet->getStyle('B2:B4')->getFont()->setSize(15);
        $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('B2:B4')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

        /** Información cabecera */
        /** -------------------- */

        $periodoText = $periodoCabecera->createTextRun('  » Periodo: ');
        $periodoText->getFont()->setBold(true);
        $periodoCabecera->createText($periodo);

        $cabeceraText = $fechaCabecera->createTextRun('  » Fecha imprime: ');
        $cabeceraText->getFont()->setBold(true);
        $fechaCabecera->createText($fechaActual);

        $sheet->setCellValue('B1', '  '.strtoupper($nombreInforme));
        $sheet->setCellValue('B2', $periodoCabecera);
        $sheet->setCellValue('B3', $fechaCabecera);

        /* Color de fondo Title */
        /* -------------------- */

        $sheet->getStyle('A6:'.$ultimaColumna.'6')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('f2f2f2')
        ;

        /* Estilo de Bordes */
        /* ---------------- */

        $styles = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FFB0B0B0'],
                ],
            ],
        ];

        $stylesCabecera = [
            'borders' => [
                'outline' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FFB0B0B0'],
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

        $sheet->getStyle('A1:'.$ultimaColumna.'4')->applyFromArray($stylesCabecera);
        $sheet->getStyle('A1:'.$ultimaColumna.'4')->applyFromArray($stylesCabeceraInterior);
        $sheet->getSheetView()->setZoomScale(80);
        $sheet->setTitle($nombreInforme);

        /* Configuración del logo */
        /* ---------------------- */
        
        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawing->setName('Informe');
        $drawing->setDescription('Informe');
        $drawing->setPath($logoTmp);
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
        $nombreInforme = strtolower(str_replace(' ', '_', $nombreInforme));
        return $this->file($temp_file, $nombreInforme.'.xls', ResponseHeaderBag::DISPOSITION_ATTACHMENT);
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
        $cabecera = [];
        $rutaCampo = '';
        $indexCabecera = 0;
        $camposTotalizacion = [];
        $tablaTotales['colspan'] = 0;
        $camposTotalizacionAgrupamiento = [];
        $camposTotalizados = $this->camposTotalizados;
        $ruta = $request->getScheme().'://'.$request->server->get('HTTP_HOST');
        $alineaciones = ['centro' => 'center', 'derecha' => 'right', 'izquierda' => 'left'];
        $cabecerasConfiguracion = !empty($this->configuracionesGuardadas)?$this->configuracionesGuardadas['cabeceras']:[];
        $camposGuardadosConfiguracion = !empty($this->configuracionesGuardadas)?$this->configuracionesGuardadas['campos']:[];
        if(!empty($camposGuardadosConfiguracion))
        {
            foreach($camposTotalizados as $key => $campo)
            {   
                if(!in_array($key, $camposGuardadosConfiguracion))
                {
                    unset($camposTotalizados[$key]);
                    unset($this->camposTotalizados[$key]);
                }
            }
        }

        /** Se definen los campos iniciales del excel */
        /** ----------------------------------------- */

        if($agrupacion)
        {
            $filaTitulo = $this->filaGeneral;
            $inicioRegistros = $this->filaGeneral + 1;
            $filaInicioRegistros = $this->filaGeneral; 
        }
        else
        {
            $filaTitulo = 6;
            $inicioRegistros = 7;
            $filaInicioRegistros = 6;    
        }

        /** Se obtiene el json que contiene las configuraciones del informe */
        /** --------------------------------------------------------------- */

        if(!empty($configuraciones))
        {
            if(array_key_exists('campos', $configuraciones)){$configuracionCampos = $configuraciones['campos'];}
            if(array_key_exists('cabecera', $configuraciones) && is_array($configuraciones['cabecera']) && !empty($configuraciones['cabecera'])){$cabecera = $configuraciones['cabecera'];}
            if(array_key_exists('totalizacion', $configuraciones['agrupamiento'][0]) && !empty($configuraciones['agrupamiento'][0]['totalizacion']) && is_array($configuraciones['agrupamiento'][0]['totalizacion']))
            {
                if(empty($this->configuracionesGuardadas) || !empty($this->configuracionesGuardadas['agrupacion'])){$camposTotalizados = [];}
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
                    /** Se valida si existen campos guardados en las configuraciones */
                    /** ------------------------------------------------------------ */
                    
                    if(!empty($camposGuardadosConfiguracion))
                    {
                        if(!in_array($ct['campo'], $camposGuardadosConfiguracion))
                        {
                            continue;
                        }
                    }

                    /** Se obtienen los campos de totalización */
                    /** -------------------------------------- */

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
            $rellenoCampo = (($indexRegistro + 1) % 2 == 0)?'#17A2B814':'';
            foreach($registro as $key => $campo)
            {
                /** Se valida si existen campos guardados en configuraciones */
                /** -------------------------------------------------------- */

                if(!empty($camposGuardadosConfiguracion))
                {
                    $registro = $camposGuardadosConfiguracion;
                    if(!in_array($key, $camposGuardadosConfiguracion)){continue;}
                }
                
                /** Se crean los títulos del informe con sus respectivos estilos */
                /** ------------------------------------------------------------ */
                
                $alineacionCampo = 'left';
                $alineacionTitulo = 'center';
                $titulo = ucfirst(str_replace('_', ' ', $key));
                $columna = Coordinate::stringFromColumnIndex($index);
                $this->ultimaColumna = Coordinate::stringFromColumnIndex(count($registro));
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

                    if(array_key_exists('ruta', $configuracionCampo[0]) && is_array($configuracionCampo[0]['ruta']) && !empty($configuracionCampo[0]['ruta']) && array_key_exists('nombre', $configuracionCampo[0]['ruta']))
                    {
                        $parametros = [];
                        $alineacionCampo = 'center';
                        if(array_key_exists('parametros', $configuracionCampo[0]['ruta']) && is_array($configuracionCampo[0]['ruta']['parametros']) && !empty($configuracionCampo[0]['ruta']['parametros']))
                        {
                            $parametros = str_replace('$campo', $campo, json_encode($configuracionCampo[0]['ruta']['parametros']));
                            $parametros = json_decode($parametros, true);
                        }
                        $rutaCampo = $ruta.$this->generateUrl($configuracionCampo[0]['ruta']['nombre'], $parametros);
                    }
                }

                /** Se crean los títulos del informe */
                /** -------------------------------- */

                if($indexRegistro == 0)
                {   
                    /** Se crea la sección de la cabecera */
                    /** --------------------------------- */
                    
                    if((!empty($cabecera) && empty($this->configuracionesGuardadas)) || !empty($cabecerasConfiguracion))
                    {
                        $cabeceras = $cabecera;
                        if(!empty($cabecerasConfiguracion)){$cabeceras = $cabecerasConfiguracion;}
                        if($indexCabecera == 0)
                        {
                            if(!$agrupacion)
                            {
                                $filaTitulo = 7;
                                $columnaInicio = 1;
                                $inicioRegistros = 8;
                                $filaInicioRegistros = 7;
                                $sheet->getRowDimension('7')->setRowHeight(25);
                                $sheet->getStyle('A6:'.$this->ultimaColumna.'6')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                                $sheet->getStyle('A6:'.$this->ultimaColumna.'6')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
                                foreach($cabeceras as $c)
                                {
                                    $colSpanCabecera = $c['colspan'];
                                    $tituloCabecera = strip_tags($c['nombre']);
                                    $columnaFinal = Coordinate::stringFromColumnIndex(($columnaInicio + $colSpanCabecera) - 1);
                                    $columnaInicial = Coordinate::stringFromColumnIndex($columnaInicio);
                                    $sheet->setCellValue($columnaInicial.'6', $tituloCabecera);
                                    $sheet->mergeCells($columnaInicial.'6:'.$columnaFinal.'6');
                                    $columnaInicio += $colSpanCabecera;
                                }
        
                                /** Se aplican estilos a la cabecera del informe */
                                /** -------------------------------------------- */
        
                                $sheet->getStyle('A6:'.$this->ultimaColumna.'7')->getFill()
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
                                            'color' => ['argb' => 'FFB0B0B0'],
                                        ],
                                    ],
                                ];
                                $sheet->getStyle('A6:'.$this->ultimaColumna.'6')->getFont()->setBold(true)->setSize(11);
                                $sheet->getStyle('A6:'.$this->ultimaColumna.'6')->applyFromArray($styles);
                                $indexCabecera ++;
                            }
                            else
                            {
                                $columnaInicio = 1;
                                $filaTitulo = $this->filaGeneral + 1;
                                $inicioRegistros = $this->filaGeneral + 2;
                                $filaInicioRegistros = $this->filaGeneral + 1;
                                $sheet->getRowDimension($filaTitulo)->setRowHeight(25);
                                $sheet->getRowDimension($filaTitulo - 1)->setRowHeight(25);
                                $sheet->getStyle('A'.$this->filaGeneral.':'.$this->ultimaColumna.$this->filaGeneral)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                                $sheet->getStyle('A'.$this->filaGeneral.':'.$this->ultimaColumna.$this->filaGeneral)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
                                foreach($cabeceras as $c)
                                {
                                    $colSpanCabecera = $c['colspan'];
                                    $tituloCabecera = strip_tags($c['nombre']);
                                    $columnaInicial = Coordinate::stringFromColumnIndex($columnaInicio);
                                    $sheet->setCellValue($columnaInicial.$this->filaGeneral, $tituloCabecera);
                                    $columnaFinal = Coordinate::stringFromColumnIndex(($columnaInicio + $colSpanCabecera) - 1);
                                    $sheet->mergeCells($columnaInicial.$this->filaGeneral.':'.$columnaFinal.$this->filaGeneral);
                                    $columnaInicio += $colSpanCabecera;
                                }
        
                                /** Se aplican estilos a la cabecera del informe */
                                /** -------------------------------------------- */
        
                                $sheet->getStyle('A'.$this->filaGeneral.':'.$this->ultimaColumna.$filaTitulo)->getFill()
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
                                            'color' => ['argb' => 'FFB0B0B0'],
                                        ],
                                    ],
                                ];
                                $sheet->getStyle('A'.$this->filaGeneral.':'.$this->ultimaColumna.$this->filaGeneral)->getFont()->setBold(true)->setSize(11);
                                $sheet->getStyle('A'.$this->filaGeneral.':'.$this->ultimaColumna.$this->filaGeneral)->applyFromArray($styles);
                                $indexCabecera ++;
                            }
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
                                'color' => ['argb' => 'FFB0B0B0'],
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

                /** Se valida si el campo corresponde a una ruta */
                /** -------------------------------------------- */

                if(!empty($rutaCampo))
                {
                    $sheet->getCell($columna.$inicioRegistros)->getHyperlink()->setUrl($rutaCampo);
                    $sheet->getStyle($columna.$inicioRegistros)->getFont()->getColor()->setARGB('FF007BFF');
                }

                /** Se asignan estilos a cada campo */
                /** ------------------------------- */

                $styles = 
                [
                    'borders' => 
                    [
                        'right' => 
                        [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['argb' => 'FFB0B0B0'],
                        ],
                        'bottom' => 
                        [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['argb' => (($inicioRegistros - $filaInicioRegistros) == count($listRegistros))?'FFB0B0B0':'d1d4da'],
                        ],
                    ],
                ];
                $sheet->getStyle('A'.$inicioRegistros.':'.$this->ultimaColumna.$inicioRegistros)->applyFromArray($styles, false);
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
                $rutaCampo = '';
            }
            $index = 1;
            $inicioRegistros ++;
        }

        /** Se crea la sección de totales */
        /** ----------------------------- */

        $columnaInicioTotal = 1;
        $columnaInicialTotal = '';
        if(!empty($camposTotalizados))
        {
            $sheet->getRowDimension($inicioRegistros)->setRowHeight(25);
            $sheet->getStyle('A'.$inicioRegistros.':'.$this->ultimaColumna.$inicioRegistros)->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle('A'.$inicioRegistros.':'.$this->ultimaColumna.$inicioRegistros)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            $sheet->getStyle('A'.$inicioRegistros.':'.$this->ultimaColumna.$inicioRegistros)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            foreach($tablaTotales as $key => $campoTotal)
            {
                $columnaInicialTotal = Coordinate::stringFromColumnIndex($columnaInicioTotal);
                if($key == 'colspan' && $campoTotal > 0)
                {
                    $columnaInicioTotal = $campoTotal + 1;
                    $sheet->setCellValue('A'.$inicioRegistros, 'Total »');
                    $columnaFinTotal = Coordinate::stringFromColumnIndex($campoTotal);
                    $sheet->mergeCells('A'.$inicioRegistros.':'.$columnaFinTotal.$inicioRegistros);
                }
                else
                {
                    if(is_array($campoTotal)){$campoTotal = $campoTotal[0];}
                    $sheet->setCellValue($columnaInicialTotal.$inicioRegistros, $campoTotal);
                    $columnaInicioTotal ++;
                }
            }

            /** Se aplican estilos a la cabecera del informe */
            /** -------------------------------------------- */

            $sheet->getStyle('A'.$inicioRegistros.':'.$this->ultimaColumna.$inicioRegistros)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('f2f2f2')
            ;

            $styles = 
            [
                'borders' => 
                [
                    'outline' => 
                    [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['argb' => 'FFB0B0B0'],
                    ]
                ],
            ];
            $sheet->getStyle('A'.$inicioRegistros.':'.$this->ultimaColumna.$inicioRegistros)->applyFromArray($styles);
            $this->filaGeneral = $inicioRegistros;
            $this->filaGeneral ++;
        }
    }

    public function crearTablaAgrupadaExcel(Request $request, $titulo, $sheet, $configuraciones, $registros)
    {
        /** 
            * En esta función se crean las tablas del archivo excel, de acuerdo a los campos de agrupación configurados en el informe
            * -----------------------------------------------------------------------------------------------------------------------
            * @access public
        */

        $sheet->getRowDimension($this->filaGeneral)->setRowHeight(30);
        $sheet->setCellValue('A'.$this->filaGeneral, '      '.$titulo);
        $sheet->mergeCells('A'.$this->filaGeneral.':'.$this->ultimaColumna.$this->filaGeneral);
        $sheet->getStyle('A'.$this->filaGeneral.':'.$this->ultimaColumna.$this->filaGeneral)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

        /** Se aplican estilos a la cabecera del informe */
        /** -------------------------------------------- */

        $sheet->getStyle('A'.$this->filaGeneral.':'.$this->ultimaColumna.$this->filaGeneral)->getFill()
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
                    'color' => ['argb' => 'FFB0B0B0'],
                ],
            ],
        ];
        $sheet->getStyle('A'.$this->filaGeneral.':'.$this->ultimaColumna.$this->filaGeneral)->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A'.$this->filaGeneral.':'.$this->ultimaColumna.$this->filaGeneral)->applyFromArray($styles);
        $this->filaGeneral ++;

        /** Se crea la tabla de detalles correspondiente a cada agrupación */
        /** -------------------------------------------------------------- */

        $this->crearTablaRegistrosExcel($request, $configuraciones, $registros, true, $sheet);

        /** Se crea un separador */
        /** -------------------- */

        $sheet->getRowDimension($this->filaGeneral)->setRowHeight(30);
        $sheet->mergeCells('A'.$this->filaGeneral.':'.$this->ultimaColumna.$this->filaGeneral);
        $this->filaGeneral ++;
    }

    /**
     * @Route("/Central/Reporteador/frameErrorInforme", name="central_reporteador_frame_error_informe")
    */
    public function frameErrorInforme(Request $request)
    {
        /** 
            * En esta función se genera la vista para visualizar los detalles de cualquier error ocurrido 
            * en la descarga de un informe (excel, pdf).
            * -------------------------------------------------------------------------------------------
            * @access public
        */

        $line = '';
        $file = '';
        $message = '';
        $session = $request->getSession();
        if($session->has('errorDescargaInforme'))
        {
            $errorDescargaInforme = $session->get('errorDescargaInforme');
            $message = $errorDescargaInforme['message'];
            $line = $errorDescargaInforme['line'];
            $file = $errorDescargaInforme['file'];
            $session->remove('errorDescargaInforme');
        }
        return $this->render('Central/Reporteador/frameErrorInforme.html.twig',
        [
            'file' => $file,
            'line' => $line,
            'message' => $message
        ]);
    }
}