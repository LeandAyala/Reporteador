<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReclamosController extends AbstractController
{
    public function reclamos()
    {
        return $this->render('reclamos\reclamos.html.twig');
    }
}
