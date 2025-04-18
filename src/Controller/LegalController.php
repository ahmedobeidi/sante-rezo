<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LegalController extends AbstractController
{
    #[Route('/conditions-utilisation', name: 'app_terms_of_use')]
    public function termsOfUse(): Response
    {
        return $this->render('legal/terms_of_use.html.twig');
    }
}