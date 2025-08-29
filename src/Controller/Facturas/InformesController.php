<?php

namespace App\Controller\Facturas;

use App\Entity\Facturas\Reporte;
use App\Entity\Facturas\Factura;
use App\Entity\Productos\Producto;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\Facturas\FiltrosInformesType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\DBAL\Exception\ConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class InformesController extends AbstractController
{
    private $em;
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function informes()
    {
        $listAgrupada = [];
        $campoControl = '';
        $campoAnterior = '';
        $camposReferencia = [];
        $camposAgrupacion = ['almacen'];
        $listRegistros =
        [
            ['almacen' => '1-Bellatriz', 'bodega' => '1-Bodega principal', 'grupo' => '1-Consignados', 'producto' => 'Producto test 1'],
            ['almacen' => '1-Bellatriz', 'bodega' => '1-Bodega principal', 'grupo' => '1-Consignados', 'producto' => 'Producto test 2'],
            ['almacen' => '1-Bellatriz', 'bodega' => '1-Bodega principal', 'grupo' => '5-Otros', 'producto' => 'Producto test 4'],
            ['almacen' => '1-Bellatriz', 'bodega' => '2-Bodega Panamericana', 'grupo' => '1-Consignados', 'producto' => 'Producto test 2'],
            ['almacen' => '1-Bellatriz', 'bodega' => '3-Bodega Sebastian', 'grupo' => '2-Cosmetología', 'producto' => 'Producto test 3'],
            ['almacen' => '2-Principal', 'bodega' => '4-Bodega principal', 'grupo' => '3-Papelería', 'producto' => 'Producto test 1'],
            ['almacen' => '2-Principal', 'bodega' => '5-Bodega Panamericana', 'grupo' => '3-Papelería', 'producto' => 'Producto test 2'],
            ['almacen' => '2-Principal', 'bodega' => '6-Bodega Sebastian', 'grupo' => '4-Cosmetología', 'producto' => 'Producto test 3']
        ];

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
        $tdCampo = '';
        $paginas = [];
        $cabecera = [];
        $trTotales = '';
        $bd = $this->em;
        $plantilla = '';
        $keyCampos = [];
        $thCabecera = '';
        $trCabecera = '';
        $divRelleno = '';
        $indexPagina = 1;
        $paginacion = 100;
        $tablaTotales = [];
        $agrupamiento = [];
        $estiloBordes = '';
        $colorRelleno = '';
        $rellenoCampo = '';
        $filasInforme = '';
        $status = 'success';
        $rellenoTitulo = '';
        $totalRegistros = 0;
        $titulosInforme = '';
        $indexPaginacion = 1;
        $contenidoInforme = '';
        $indexTotalPaginas = 1;
        $camposAgrupacion = [];
        $botonesPaginator = '';
        $camposTotalizados = [];
        $camposTotalizacion = [];
        $contenidoPaginacion = '';
        $estiloBordesRelleno = '';
        $configuracionCampos = [];
        $paginasSeleccionadas = [];
        $rellenoOpcionPaginator = '';
        $iconoBloqueo = 'color:gray;';
        $listRegistrosPaginacion = [];
        $conexion = $bd->getConnection();
        $accionBloqueo = 'pointer-events:none;';
        $form = $request->request->get('filtros_informes');
        $alineaciones = ['centro' => 'center', 'derecha' => 'right', 'izquierda' => 'left'];
        $informe = $bd->getRepository(Reporte::class)->findOneBy(['id' => $form['informe']]);
        $logo = base64_encode(file_get_contents($this->getParameter('imgs_directory').'logo.png'));
        $fondo = base64_encode(file_get_contents($this->getParameter('imgs_directory').'fondo.jpg'));

        /** Se obtienen los filtros de búsqueda seleccionados */
        /** ------------------------------------------------- */

        $sqlInforme = $informe->getSql();
        $nombreInforme = $informe->getNombre();
        $pagina = $request->request->get('pagina');
        foreach($form as $key => $campo){$filtros['['.$key.']'] = !empty($campo)?$campo:-1;}
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
        }

        /** Se realiza la consulta de los registros */
        /** --------------------------------------- */

        try 
        {
            $listRegistros = $conexion->prepare($sqlInforme)->executeQuery()->fetchAll();
            $totalRegistros = count($listRegistros);

            /** Se genera la paginación de los registros */
            /** ---------------------------------------- */

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
                $rellenoOpcionPaginator = ($pagina == $p)?'background:#0a5561; color:white;':'background:white';
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
                            if($index == 0)
                            {
                                $divAgrupacionGeneral .=
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
                                    <div class="titulo" style="transition:all 0.5s ease; font-size:11px; cursor:pointer" onclick="$('#$keyFila').toggle('400')">$titulo</div>
                                    <i class="fas fa-angle-double-right text-info" style="font-size:10px"></i>
                                    <span class="montserrat-text" style="font-size:11px">$items</span>
                                </div>
                                <div id="$keyFila" style="display:none; width:100%; border: 1px solid #dee2e6; padding: 10px; border-radius: 0px 0px 5px 5px; margin-top:-1px;">
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
                                        <span class="montserrat-text" style="font-size:11px">$item</span>
                                    </div>
                                    <div id="$keyItem" style="display:none; width:100%; border: 1px solid #dee2e6; padding: 10px; border-radius: 0px 0px 5px 5px; margin-top:-1px;">
                                        <div style="display:flex; align-items:center; justify-content:center; width:100%; flex-direction:column">
                                            replace_$keyItem
                                        </div>
                                    </div>
                                    TWIG;
                                }
                                $divAgrupacionGeneral = str_replace('replace_'.$keyFila, $divAgrupacion, $divAgrupacionGeneral);
                                $divAgrupacion = '';

                                /** Se agrega a los items del último campo de agrupación los registros correspondientes */
                                /** ----------------------------------------------------------------------------------- */

                                if($index == (count($listAgrupada) - 2))
                                {
                                    foreach($registrosAgrupados as $registros)
                                    {
                                        foreach($listAgrupada['registros'][str_replace('_', ' ', $registros)] as $registro)
                                        {
                                            $registro = $registro[array_key_first($registro)];
                                            $divAgrupacion .=
                                            <<<TWIG
                                            <div>$registro</div>
                                            TWIG;
                                        }
                                        $divAgrupacionGeneral = str_replace('replace_'.$registros, $divAgrupacion, $divAgrupacionGeneral);
                                        $divAgrupacion = '';
                                    }
                                }
                            }
                        }
                        $index ++;
                    }
                    $contenidoInforme = 
                    <<<TWIG
                    <div style="display:flex; align-items:center; justify-content:center; flex-direction:column; width:100%">
                        $divAgrupacionGeneral
                    </div>
                    TWIG;
                }
                else
                {
                    /** Se genera el informe sin campos de agrupacion */
                    /** --------------------------------------------- */
                    
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
                                    <div $claseTitulo style="transition:all 0.5s ease; background:#f8f9fa; display:flex; align-items:center; justify-content:$alineacionTitulo; padding:9px 10px 9px 12px; font-size:12px; border:1px solid #d0d4da; border-right:none; $estiloBordesTitulo">
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
                                $claseTitulo = 'class="tituloInicial"';
                                $estiloBordesTitulo = 'border-radius:10px 0px 0px 3px';
                            }

                            if($index == (count($cabecera) - 1))
                            {
                                $claseTitulo = 'class="tituloFinal"';
                                $estiloBordesTitulo = 'border-radius:0px 10px 3px 0px; border-right:1px solid #d0d4da';
                            }

                            $thCabecera .=
                            <<<TWIG
                            <th colspan="$colSpanCabecera">
                                <div $claseTitulo style="transition:all 0.5s ease; background:#f8f9fa; display:flex; align-items:center; justify-content:center; padding:9px 10px 9px 12px; font-size:12px; border:1px solid #d0d4da; border-bottom:none; border-right:none; $estiloBordesTitulo">
                                    $tituloCabecera
                                </div>
                            </th>
                            TWIG;
                            $claseTitulo = '';
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
                                                    style="position: absolute; top:1px; left:0px; font-size:12px" 
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
                padding:20px 15px 20px 15px; 
            ">
                <div class="col-12">
                    <div></div>
                    <div style=
                    "   
                        top: -28px;
                        z-index: -1;
                        left: -15px;
                        height: 120px;
                        overflow: hidden;
                        background: #17A;
                        position: absolute;
                        width: calc(100% + 30px);
                        border-radius: 0px 0px 12px 12px;
                        filter: drop-shadow(2px 3px 6px gray);
                    ">
                        <img src="data:image;base64,$fondo" style=
                        "
                            left: 4px;
                            opacity: 0.1;
                            width: 520px;
                            height: 330px;
                            position: absolute;
                        ">
                        <img src="data:image;base64,$fondo" style=
                        "
                            left: 524px;
                            opacity: 0.1;
                            width: 520px;
                            height: 330px;
                            position: absolute;
                        ">
                        <img src="data:image;base64,$fondo" style=
                        "
                            left: 1044px;
                            opacity: 0.1;
                            width: 520px;
                            height: 330px;
                            position: absolute;
                        ">
                        <img src="data:image;base64,$fondo" style=
                        "
                            left: 1564px;
                            opacity: 0.1;
                            width: 520px;
                            height: 330px;
                            position: absolute;
                        ">
                    </div>
                    <div style="display:flex; align-items:center; gap:10px">
                        <div style=
                        "
                            width: 50px;
                            display: flex;
                            padding: 33px;
                            height: 50px;
                            background: white;
                            border-radius: 50%;
                            align-items: center;
                            justify-content: center;
                            border: 3px solid #315460;
                        ">
                            <img src="data:image;base64,$logo" style="transform:scale(0.7)">
                        </div>
                        <div style="display:flex; justify-content:center; flex-direction:column; gap:3px">
                            <span class="montserrat" style="color:white">COMPUCONTA S.A.S</span>
                            <div style="display:flex; align-items:center; gap:5px">
                                <i class="fas fa-map-marker-alt" style="font-size:11px"></i>
                                <span class="montserrat-text" style="font-size:10px; color:white">Calle 20 No. 28-61 Edificio El Doral Oficina 201.</span>
                            </div>
                            <div style="display:flex; align-items:center; gap:5px">
                                <i class="fas fa-phone" style="font-size:11px"></i>
                                <span class="montserrat-text" style="font-size:10px; color:white">601 915 8777</span>
                            </div>
                        </div>
                    </div>
                    <div class="animate__animated animate__flipInX" style="position:relative; margin-top:50px; margin-left:20px; width:fit-content; display:flex; align-items:center; justify-content:center; gap:5px">
                        <i class="fas fa-info-circle" style="font-size:13px"></i>
                        <span class="montserrat" style="font-size:12px;">$nombreInforme</span>
                    </div>
                    <hr style="margin-left:15px; margin-right:15px">
                    <div class="listado animate__animated animate__fadeIn animate__delay-1s" style="margin-top:25px; padding:3px; overflow-y:auto; overflow-x:hidden; transition:all 0.5s ease">
                        <div style="display:flex; align-items:center; justify-content:center;">
                            $contenidoInforme
                        </div>
                    </div>
                </div>
            </div>
        </div>
        $contenidoPaginacion
        <input type="hidden" id="paginaHidden" value="$pagina" data-facturas--informes-target="paginaHidden">
        TWIG;
        return new Response(json_encode(['status' => $status, 'message' => $message, 'plantilla' => $plantilla]));
    }

    public function crearTablaRegistros($configuracionCampos, $listRegistros)
    {   
        /** 
         * En esta función se crea la tabla principal del informe, la cual contiene todos los registros de la página seleccionada
         * ----------------------------------------------------------------------------------------------------------------------
         * @access public
        */

        
    }
}