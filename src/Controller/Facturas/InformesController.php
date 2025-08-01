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
        $bd = $this->em;
        $plantilla = '';
        $divRelleno = '';
        $indexPagina = 1;
        $estiloBordes = '';
        $colorRelleno = '';
        $rellenoCampo = '';
        $filasInforme = '';
        $status = 'success';
        $rellenoTitulo = '';
        $totalRegistros = 0;
        $titulosInforme = '';
        $indexPaginacion = 1;
        $indexTotalPaginas = 1;
        $botonesPaginator = '';
        $estiloBordesRelleno = '';
        $paginasSeleccionadas = [];
        $iconoBloqueo = 'color:gray;';
        $rellenoOpcionPaginator = '';
        $listRegistrosPaginacion = [];
        $conexion = $bd->getConnection();
        $accionBloqueo = 'pointer-events:none;';
        $form = $request->request->get('filtros_informes');
        $informe = $bd->getRepository(Reporte::class)->findOneBy(['id' => $form['informe']]);
        $logo = base64_encode(file_get_contents($this->getParameter('imgs_directory').'logo.png'));

        /** Se obtienen los filtros de búsqueda seleccionados */
        /** ------------------------------------------------- */

        $sqlInforme = $informe->getSql();
        $nombreInforme = $informe->getNombre();
        $pagina = $request->request->get('pagina');
        foreach($form as $key => $campo){$filtros['$'.$key] = !empty($campo)?$campo:-1;}
        $sqlInforme = strtr($sqlInforme, $filtros);

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
                if($indexPaginacion == 100 || ($indexRegistro == count($listRegistros) - 1))
                {
                    $listRegistrosPaginacion[] = $dataRegistro;
                    $paginas[] = $indexTotalPaginas; 
                    $indexTotalPaginas ++;
                    $indexPaginacion = 0;
                    $dataRegistro = [];
                    $indexPagina ++;
                }
                $indexPaginacion ++;
            }

            /** Se crean las opciones del paginator */
            /** ----------------------------------- */

            if($pagina > 5)
            {
                $paginas = array_slice($paginas, $paginaSeleccionada - 1, $paginaSeleccionada + 5);
                if(count($paginas) < 5)
                {
                    $paginasCompletar = 5 - count($paginas);
                    for($i = $paginasCompletar; $i >= 1; $i--)
                    {
                        $paginasControl[] = $paginaSeleccionada - $i; 
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

            $listRegistros = $listRegistrosPaginacion[$pagina - 1];
            foreach($listRegistros as $indexRegistro => $registro)
            {   
                $rellenoCampo = (($indexRegistro + 1) % 2 == 0) ? '#17A2B814' : '';
                foreach($registro as $key => $campo)
                {
                    /** Se crean los títulos del informe con sus respectivos estilos */
                    /** ------------------------------------------------------------ */
                    
                    $tdCampo .= 
                    <<<TWIG
                    <td style="padding-left:7px; font-size:12px; padding-top:7px; padding-bottom:7px; border-bottom:1px solid #E2E2E2">$campo</td>
                    TWIG;

                    if($indexRegistro == 0)
                    {
                        $titulo = ucfirst(str_replace('_', ' ', $key));
                        $colorRelleno = (($index % 2) != 0)?'#17A3B8':'#0a5561';
                        $rellenoTitulo = (($index % 2) != 0)?'#0a5561':'#17A3B8';
                        if($index == 1)
                        {
                            $estiloBordes = 'border-radius:15px 5px 5px 15px';
                            $divRelleno = 
                            <<<TWIG
                            <div style="position:absolute; height:100%; width:100%; background:$colorRelleno; top:0; left:0; border-radius: 16px 0px 0px 16px; z-index:-1;"></div>
                            TWIG;
                        }
                        if($index == count($registro))
                        {
                            $estiloBordes = 'border-radius:5px 15px 15px 5px';
                            $divRelleno = 
                            <<<TWIG
                            <div style="position:absolute; height:100%; width:100%; background:$colorRelleno; top:0; left:0; border-radius: 0px 16px 16px 0px; z-index:-1;"></div>
                            TWIG;
                        }
                        $titulosInforme .=
                        <<<TWIG
                        <td>
                            <div style=
                            "
                                color:white;
                                font-size:10px;
                                font-weight:bold; 
                                position:relative;
                                text-align:center;
                                background:$rellenoTitulo;
                                padding:6px 10px 6px 12px;
                                $estiloBordes
                            ">
                                $titulo
                                $divRelleno
                            </div>
                        </td>
                        TWIG;
                        $index ++;
                        $divRelleno = '';
                        $estiloBordes = '';
                    }
                }
                $filasInforme .=
                <<<TWIG
                    <tr style="background:$rellenoCampo">
                        $tdCampo
                    </tr>
                TWIG;
                $tdCampo = '';
            }

            $plantilla =
            <<<TWIG
            <div class="list-group-item p-0" style="width:100%; border:1px solid #d1d4da; border-radius:6px 6px 0px 0px; display:flex; align-items:center; justify-content:center">
                <div class="row animate__animated animate__fadeIn" style=
                "
                    width:100%; 
                    overflow:hidden; 
                    background:white; 
                    position:relative; 
                    border-radius:15px;
                    padding:30px 15px 20px 15px; 
                ">
                    <div class="col-12">
                        <div style=
                        "
                            top: -15px;
                            z-index: -1;
                            left: -33px;
                            height: 85px;
                            width: 523px;
                            position:absolute;     
                            background: #ECECEC;
                            transform: skewX(38deg);
                            border-radius: 0px 31px 0px 11px;
                        "></div>
                        <div style="display:flex; align-items:center; gap:10px">
                            <img src="data:image;base64,$logo" style="transform:scale(0.7)">
                            <div style="display:flex; justify-content:center; flex-direction:column; gap:3px">
                                <span class="montserrat">COMPUCONTA S.A.S</span>
                                <div style="display:flex; align-items:center; gap:5px">
                                    <i class="fas fa-map-marker-alt" style="font-size:11px"></i>
                                    <span class="montserrat-text" style="font-size:10px">Calle 20 No. 28-61 Edificio El Doral Oficina 201.</span>
                                </div>
                                <div style="display:flex; align-items:center; gap:5px">
                                    <i class="fas fa-phone" style="font-size:11px"></i>
                                    <span class="montserrat-text" style="font-size:10px">601 915 8777</span>
                                </div>
                            </div>
                        </div>
                        <div class="animate__animated animate__flipInX" style="position:relative; margin-top:35px; margin-left:20px; width:fit-content; display:flex; align-items:center; justify-content:center; gap:5px">
                            <i class="fas fa-info-circle" style="font-size:13px"></i>
                            <span class="montserrat" style="font-size:12px;">$nombreInforme</span>
                        </div>
                        <hr style="margin-left:15px; margin-right:15px">
                        <div class="listado animate__animated animate__fadeIn animate__delay-1s" style="margin-top:25px; padding:3px; overflow-y:auto; overflow-x:hidden; transition:all 0.5s ease">
                            <div style="display:flex; align-items:center; justify-content:center;">
                                <table border="0" cellspacing="0" cellpadding="0" style="width:100%">
                                    <tr class="montserrat">
                                        $titulosInforme
                                    </tr>
                                    $filasInforme
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
            <input type="hidden" id="paginaHidden" value="$pagina" data-facturas--informes-target="paginaHidden">
            TWIG;
        } 
        catch(\Exception $e) 
        {
            $status = 'error';
            $message = $e->getMessage();    
        }
        return new Response(json_encode(['status' => $status, 'message' => $message, 'plantilla' => $plantilla]));
    }
}