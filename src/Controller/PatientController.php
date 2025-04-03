<?php

namespace App\Controller;

use App\Entity\Patient;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;


final class PatientController extends AbstractController
{
    #[Route('/patient', name: 'app_patient_profile')]
    #[IsGranted('ROLE_USER')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $patient = $entityManager->getRepository(Patient::class)->findOneBy(['user' => $user]);

        if (!$patient) {
            $patient = new Patient();
            $patient->setUser($user);
            $entityManager->persist($patient);
            $entityManager->flush();
        }

        return $this->render('patient/index.html.twig', [
            'patient' => $patient,
        ]);
    }

    #[Route('/patient/update', name: 'app_patient_update', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function update(
        Request $request, 
        EntityManagerInterface $entityManager,
        TokenStorageInterface $tokenStorage
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $patient = $entityManager->getRepository(Patient::class)->findOneBy(['user' => $user]);

        if (!$patient) {
            throw $this->createNotFoundException('Patient not found');
        }

        // Get form data
        $firstName = $request->request->get('firstName');
        $lastName = $request->request->get('lastName');
        $city = $request->request->get('city');
        $address = $request->request->get('address');

        // Update patient data
        $patient->setFirstName($firstName);
        $patient->setLastName($lastName);
        $patient->setCity($city);
        $patient->setAddress($address);

        // Check if all fields are filled to grant ROLE_PATIENT
        if ($firstName && $lastName && $city && $address) {
            if (!in_array('ROLE_PATIENT', $user->getRoles())) {
                $roles = $user->getRoles();
                $roles[] = 'ROLE_PATIENT';
                $user->setRoles(array_unique($roles));
                $entityManager->persist($user);
                
                // Refresh the user's token with new roles
                $token = new UsernamePasswordToken(
                    $user,
                    'main',
                    $user->getRoles()
                );
                $tokenStorage->setToken($token);
            }
        }

        $entityManager->persist($patient);
        $entityManager->flush();

        $this->addFlash('success', 'Profil mis à jour avec succès');
        return $this->redirectToRoute('app_patient_profile');
    }
}