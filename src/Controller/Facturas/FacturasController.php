<?php

namespace App\Controller\Facturas;

use App\Entity\Usuarios\Usuario;
use App\Entity\Productos\Producto;

use App\Entity\Facturas\Factura;
use App\Form\Facturas\FiltrosBusquedaFacturaType;
use App\Form\Facturas\NuevoFacturaType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
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
}