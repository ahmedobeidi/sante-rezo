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

        // Initialize contact data
        $contactData = [
            'firstName' => '',
            'lastName' => '',
            'email' => '',
            'message' => '',
        ];

        // Pre-fill contact data if the user is logged in
        /** @var User $user */
        $user = $this->getUser();

        if ($user) {
            $contactData['email'] = $user->getEmail();
            // Check roles and fetch additional data
            if (in_array('ROLE_DOCTOR', $user->getRoles())) {
                $doctor = $doctorRepository->findOneBy(['user' => $user]);
                if ($doctor) {
                    $contactData['firstName'] = $doctor->getFirstName();
                    $contactData['lastName'] = $doctor->getLastName();
                }
            } elseif (in_array('ROLE_PATIENT', $user->getRoles())) {
                $patient = $patientRepository->findOneBy(['user' => $user]);
                if ($patient) {
                    $contactData['firstName'] = $patient->getFirstName();
                    $contactData['lastName'] = $patient->getLastName();
                }
            }
        }
        
        return $this->render('home/index.html.twig', [
            'contactData' => $contactData,
            'doctorCount' => $doctorCount,
            'patientCount' => $patientCount,
        ]);
    }
}
