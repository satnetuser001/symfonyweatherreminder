<?php

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/', name: 'home_')]
final class HomeController extends AbstractController
{
    /**
     * Render application landing page with link to API docs.
     */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        // Render a simple landing page with link to Swagger UI
        return $this->render('home/index.html.twig');
    }
}
