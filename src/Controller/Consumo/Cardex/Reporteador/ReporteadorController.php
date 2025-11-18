<?php

namespace App\Controller\Consumo\Cardex\Reporteador;

use App\Form\Consumo\Cardex\Reporteador\FiltrosReporteadorType;
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
     * @Route("/Consumo/Cardex/Reporteador/informes", name="consumo_cardex_reporteador_informes")
    */
    public function informes(Request $request)
    {
        /** 
         * En esta función se crea la vista para generar informes
         * ------------------------------------------------------
         * @access public
        */

        $formFiltros = $this->createForm(FiltrosReporteadorType::class, null, ['tipo' => 1, 'modulo' => 'Consumo']);
        return $this->render('Consumo/Cardex\Reporteador\reporteador.html.twig', ['formFiltros' => $formFiltros->createView()]);
    }
}