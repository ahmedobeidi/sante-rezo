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
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;


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

        // Check if trying to empty existing data
        if (($patient->getFirstName() && empty($firstName)) ||
            ($patient->getLastName() && empty($lastName)) ||
            ($patient->getCity() && empty($city)) ||
            ($patient->getAddress() && empty($address))
        ) {
            $this->addFlash('error', 'Les champs peuvent être modifiés mais ne peuvent pas être vidés une fois remplis');
            return $this->redirectToRoute('app_patient_profile');
        }

        // Check if any data has changed
        $hasChanges = false;

        if (!empty($firstName) && $firstName !== $patient->getFirstName()) {
            $patient->setFirstName($firstName);
            $hasChanges = true;
        }
        if (!empty($lastName) && $lastName !== $patient->getLastName()) {
            $patient->setLastName($lastName);
            $hasChanges = true;
        }
        if (!empty($city) && $city !== $patient->getCity()) {
            $patient->setCity($city);
            $hasChanges = true;
        }
        if (!empty($address) && $address !== $patient->getAddress()) {
            $patient->setAddress($address);
            $hasChanges = true;
        }

        // Only proceed if there are changes
        if ($hasChanges) {
            // Check if all fields are filled to grant ROLE_PATIENT
            if (
                $patient->getFirstName() &&
                $patient->getLastName() &&
                $patient->getCity() &&
                $patient->getAddress()
            ) {
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
        }

        return $this->redirectToRoute('app_patient_profile');
    }

    #[Route('/patient/upload-image', name: 'app_patient_upload_image', methods: ['POST'])]
    public function uploadImage(
        Request $request, 
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $patient = $entityManager->getRepository(Patient::class)->findOneBy(['user' => $user]);

        if (!$patient) {
            throw $this->createNotFoundException('Patient not found');
        }

        $profileImage = $request->files->get('profileImage');

        if ($profileImage) {
            $originalFilename = pathinfo($profileImage->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename.'-'.uniqid().'.'.$profileImage->guessExtension();

            try {
                $profileImage->move(
                    $this->getParameter('kernel.project_dir').'/public/uploads/profiles',
                    $newFilename
                );

                // Delete old image if exists
                if ($patient->getProfileImage()) {
                    $oldFile = $this->getParameter('kernel.project_dir').'/public/uploads/profiles/'.$patient->getProfileImage();
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }

                $patient->setProfileImage($newFilename);
                $entityManager->flush();

                $this->addFlash('success', 'Photo de profil mise à jour avec succès');
            } catch (FileException $e) {
                $this->addFlash('error', 'Une erreur est survenue lors du téléchargement de l\'image');
            }
        }

        return $this->redirectToRoute('app_patient_profile');
    }
}
