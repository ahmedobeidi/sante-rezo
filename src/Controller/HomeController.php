<?php

namespace App\Controller;

use App\Repository\DoctorRepository;
use App\Repository\PatientRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(DoctorRepository $doctorRepository, PatientRepository $patientRepository): Response
    {
        // Check if the user has the ROLE_ADMIN role
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin');
        }

        // Fetch the counts of doctors and patients
        $doctorCount = $doctorRepository->count([]);
        $patientCount = $patientRepository->count([]);
        
        return $this->render('home/index.html.twig', [
            'doctorCount' => $doctorCount,
            'patientCount' => $patientCount,
        ]);
    }
}
