<?php

namespace App\Controller\Facturas;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Entity\Central\meses;
use App\Entity\Usuarios\Usuario;
use App\Entity\Facturas\Factura;
use App\Entity\Central\compania;
use App\Entity\Facturas\Reporte;
use App\Entity\Productos\Producto;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Form\Facturas\NuevoFacturaType;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use App\Form\Facturas\FiltrosBusquedaFacturaType;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Doctrine\DBAL\Exception\ConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class FacturasController extends AbstractController
{
    private $em;
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function listarFacturas()
    {
        /**
         * En esta función se carga la vista que contiene los filtros de búsqueda y el listado de registros de la tabla respectiva
         * -----------------------------------------------------------------------------------------------------------------------
         * @access public
        */

        $bd = $this->em;
        $formFiltros = $this->createForm(FiltrosBusquedaFacturaType::class, null);
        return $this->render('Facturas\listaFacturas.html.twig',
        [
            'formFiltros' => $formFiltros->createView()
        ]);
    }

    public function frameListaFacturas(Request $request)
    {
        /**
         * En esta función se listan todos los registros de la tabla respectiva
         * --------------------------------------------------------------------
         * @access public
        */

        $bd = $this->em;
        $filtrosBusqueda = $request->request->get('filtros_busqueda_factura');
        $listRegistros = $bd->getRepository(Factura::class)->findFacturas($filtrosBusqueda);
        return $this->render('Facturas\frameListaFacturas.html.twig',
        [
            'listRegistros' => $listRegistros
        ]);
    }

    public function frameNuevoFacturas(Request $request)
    {
        /** 
         * En esta función se genera el formulario para Crear/Editar registros de la entidad correspondiente 
         * -------------------------------------------------------------------------------------------------
         * @access public
        */

        $bd = $this->em;
        $id = ($request->request->has('id'))?$request->request->get('id'):0;
        $registro = ($id > 0)?$bd->getRepository(Factura::class)->find($id):new Factura(); 
        $formularioPrincipal = $this->createForm(NuevoFacturaType::class, $registro);
        return $this->render('Facturas\frameNuevoFacturas.html.twig', 
        [
            'formularioPrincipal' => $formularioPrincipal->createView()
        ]);
    }

    public function guardarFacturas(Request $request)
    {
        /** 
         * En esta función se guardar/edita un registro
         * --------------------------------------------
         * @access public
        */

        /** Definición de variables */
        /** ----------------------- */

        $message = '';
        $bd = $this->em;
        $status = 'success';
        $form = $request->request->get('nuevo_factura');
        $registro = !empty($form['idRegistro'])?$bd->getRepository(Factura::class):new Factura();
        $formularioPrincipal = $this->createForm(NuevoFacturaType::class, $registro);

        /** Se guarda/edita el registro */
        /** --------------------------- */

        $idRegistro = $form['idRegistro'];
        $formularioPrincipal->handleRequest($request);
        try
        {
            $idRegistro = $registro->getId();
            $bd->persist($registro);
            $bd->flush();
        }
        catch(\Exception $e)
        {
            $status = 'error';
            $message = '¡Ocurrión un error al guardar el registro. '.$e->getMessage().'!';
        }
        return new Response(json_encode(
        [
            'status' => $status, 
            'message' => $message,
            'idRegistro' => $idRegistro
        ]));
    }

    public function eliminarFacturas(Request $request)
    {
        /** 
         * En esta función se efectúa la eliminación de un registro
         * --------------------------------------------------------
         * @access public
        */

        /** Definición de variables */
        /** ----------------------- */

        $message = '';
        $bd = $this->em;
        $status = 'success';
        $id = $request->request->get('id');
        $registro = $bd->getRepository(Factura::class)->find($id);

        /** Se guarda/edita el registro */
        /** --------------------------- */

        if(!empty($registro))
        {
            try
            {
                $bd->remove($registro);
                $bd->flush();
            }
            catch(\ConstraintViolationException $e)
            {
                $status = 'error';
                $message = '¡El registro no se puede eliminar porque se encuentra asociado!';
            }
        }
        return new Response(json_encode(
        [
            'status' => $status, 
            'message' => $message
        ]));
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

    public function descargarExcelInformeConciliacion(Request $request, $opc)
    {
        /** 
         * En esta función se hace la descarga del informe de conciliación en formato excel
         * --------------------------------------------------------------------------------
         * @access public
        */

        /** Definición de variables */
        /** ----------------------- */

        $bd = $this->em;
        set_time_limit(0);
        $ultimaColumna = 'G';
        $fechaCabecera = new RichText();
        $spreadsheet = new Spreadsheet();
        $conexion = $bd->getConnection();
        $periodoCabecera = new RichText();
        $session = $request->getSession();
        $sheet = $spreadsheet->getActiveSheet();
        $logoTmp = tempnam(sys_get_temp_dir(), 'logoTmp');
        $form = $request->request->get('filtros_informes');
        $compania = $bd->getRepository(compania::class)->findOneBy([]);
        $informe = $bd->getRepository(Reporte::class)->findOneBy(['id' => $form['informe']]);
        $fechaActual = (new \DateTime('now', new \DateTimeZone('America/Bogota')))->format('Y-m-d H:i:s');
        
        /** Se obtienen los filtros de búsqueda seleccionados */
        /** ------------------------------------------------- */
        
        $nitCompania = $compania->getNit();
        $nombreInforme = $informe->getNombre();
        $telefonoCompania = $compania->getTelefonos();
        $direccionCompania = $compania->getDireccion();
        $nombreCompania = strtoupper($compania->getNombre());
        $logoCompania = base64_decode($compania->getLogocompania());
        file_put_contents($logoTmp, $logoCompania);

        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(20);
        $sheet->getColumnDimension('G')->setWidth(20);
        $sheet->getRowDimension('1')->setRowHeight(35);
        $sheet->getRowDimension('2')->setRowHeight(20);
        $sheet->getRowDimension('3')->setRowHeight(20);
        $sheet->getRowDimension('4')->setRowHeight(20);
        $sheet->getRowDimension('5')->setRowHeight(20);
        $sheet->getRowDimension('6')->setRowHeight(40);

        $sheet->mergeCells('A1:A4');
        $sheet->mergeCells('A6:G6');
        $sheet->mergeCells('A5:'.$ultimaColumna.'5');
        $sheet->mergeCells('B1:'.$ultimaColumna.'1');
        $sheet->mergeCells('B2:'.$ultimaColumna.'2');
        $sheet->mergeCells('B3:'.$ultimaColumna.'3');
        $sheet->mergeCells('B4:'.$ultimaColumna.'4');
        $sheet->mergeCells('B5:'.$ultimaColumna.'5');
        $sheet->getStyle('B2:B4')->getFont()->setSize(15);
        $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A6')->getFont()->setBold(true)->getColor()->setARGB('FFDC3545');
        $sheet->getStyle('A6')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('B2:B4')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A6')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        /** Información cabecera */
        /** -------------------- */

        $periodoText = $periodoCabecera->createTextRun('  » Periodo: ');
        $periodoText->getFont()->setBold(true);
        $periodoCabecera->createText($fechaActual);

        $cabeceraText = $fechaCabecera->createTextRun('  » Fecha imprime: ');
        $cabeceraText->getFont()->setBold(true);
        $fechaCabecera->createText($fechaActual);

        $sheet->setCellValue('B1', '  '.strtoupper($nombreInforme));
        $sheet->setCellValue('B2', $periodoCabecera);
        $sheet->setCellValue('B3', $fechaCabecera);
        $sheet->setCellValue('A6', '¡No se encontraron registros para listar!');

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

        $sheet->getStyle('A6:'.$ultimaColumna.'6')->applyFromArray($styles);
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

    public function generarInformeConciliacion(Request $request, $opc)
    {
        /** 
         * En esta función se genera el informe de conciliación mensual
         * ------------------------------------------------------------
         * @access public
        */
        
        $plantilla = $this->renderView('Facturas/informeConciliacion.html.twig');
        return new Response(json_encode(['plantilla' => $plantilla]));
    }

    public function descargarPdfInformeConciliacion(Request $request, $opc)
    {
        /** 
         * En esta función se descarga el informe de conciliación en formato PDF
         * ---------------------------------------------------------------------
         * @access public
        */
        
        /** Definición de variables */
        /** ----------------------- */

        $periodo = '';
        $bd = $this->em;
        set_time_limit(0);
        $contenidoPDF = '';
        $camposPeriodoValido = [];
        $configuracionCampos = [];
        $pdfOptions = new Options();
        $conexion = $bd->getConnection();
        $form = $request->request->get('filtros_informes');
        $compania = $bd->getRepository(compania::class)->findOneBy([]);
        $informe = $bd->getRepository(Reporte::class)->findOneBy(['id' => $form['informe']]);
        $pdfOptions->set('defaultFont', 'Helvetica')->set('sizeFont', '9')->setIsRemoteEnabled(true);
        $fechaActual = (new \DateTime('now', new \DateTimeZone('America/Bogota')))->format('Y-m-d H:i:s');

        /** Se obtienen los filtros de búsqueda seleccionados */
        /** ------------------------------------------------- */

        $nitCompania = $compania->getNit();
        $logo = $compania->getLogocompania();
        $nombreInforme = $informe->getNombre();
        $telefonoCompania = $compania->getTelefonos();
        $direccionCompania = $compania->getDireccion();
        $nombreCompania = strtoupper($compania->getNombre());
        foreach($form as $key => $campo){$filtros['['.$key.']'] = !empty($campo)?$campo:-1;}

        /** Se obtiene el json que contiene las configuraciones del informe */
        /** --------------------------------------------------------------- */

        $configuraciones = $informe->getJson();
        if(!empty($configuraciones))
        {
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
                        <td style="padding-left:15px; width:280px">
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
            <div style="text-align:center; font-weight:bold; border:1px solid #E2E2E2; padding:17px; border-radius:5px">
                No se encontraron registros para listar
            </div>
        </body>
        TWIG;

        /** Se genera el PDF del informe */
        /** ---------------------------- */

        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        $nombreInforme = strtolower(str_replace(' ', '_', $nombreInforme));
        $dompdf->get_canvas()->page_text(282, 766, "Pagina: {PAGE_NUM} de {PAGE_COUNT}", 'Helvetica', 6, array(0, 0, 0));
        $pdf = $dompdf->output();
        return new Response(
            $pdf,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => ResponseHeaderBag::DISPOSITION_ATTACHMENT
            ]
        );
    }
}