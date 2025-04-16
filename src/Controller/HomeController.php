<?php

namespace App\Controller;

use App\DTO\ContactFormDTO;
use App\Form\ContactFormType;
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

        // Create the contact form DTO
        $contactFormDTO = new ContactFormDTO();
        
        // Pre-fill contact data if the user is logged in
        /** @var User $user */
        $user = $this->getUser();

        if ($user) {
            $contactFormDTO->email = $user->getEmail();
            
            // Check roles and fetch additional data
            if (in_array('ROLE_DOCTOR', $user->getRoles())) {
                $doctor = $doctorRepository->findOneBy(['user' => $user]);
                if ($doctor) {
                    $contactFormDTO->firstName = $doctor->getFirstName();
                    $contactFormDTO->lastName = $doctor->getLastName();
                }
            } elseif (in_array('ROLE_PATIENT', $user->getRoles())) {
                $patient = $patientRepository->findOneBy(['user' => $user]);
                if ($patient) {
                    $contactFormDTO->firstName = $patient->getFirstName();
                    $contactFormDTO->lastName = $patient->getLastName();
                }
            }
        }
        
        // Create the form
        $form = $this->createForm(ContactFormType::class, $contactFormDTO, [
            'action' => $this->generateUrl('app_contact'),
            'method' => 'POST',
        ]);
        
        // Create the contact data array for backward compatibility
        $contactData = [
            'firstName' => $contactFormDTO->firstName,
            'lastName' => $contactFormDTO->lastName,
            'email' => $contactFormDTO->email,
            'message' => $contactFormDTO->message
        ];
        
        return $this->render('home/index.html.twig', [
            'contactForm' => $form->createView(),
            'contactData' => $contactData,
            'doctorCount' => $doctorCount,
            'patientCount' => $patientCount,
        ]);
    }
}