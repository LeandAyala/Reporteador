<?php

namespace App\Controller\Consumo\Reporteador;

use App\Form\Consumo\Reporteador\FiltrosReporteadorType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ReporteadorController extends AbstractController
{
    private $em;
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @Route("/Consumo/Reporteador/informes", name="consumo_reporteador_informes")
    */
    public function informes(Request $request)
    {
        /** 
         * En esta función se crea la vista para generar informes
         * ------------------------------------------------------
         * @access public
        */

        $formFiltros = $this->createForm(FiltrosReporteadorType::class, null, ['tipo' => 1, 'modulo' => 'puntoventa']);
        return $this->render('Consumo\Reporteador\reporteador.html.twig', ['formFiltros' => $formFiltros->createView()]);
    }
}