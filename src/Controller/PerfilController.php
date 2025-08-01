<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class PerfilController extends AbstractController
{
    public function index()
    {
        return new Response(json_encode(['status' => 'success', 'email' => $this->getUser()->getEmail()]));
    }
}
