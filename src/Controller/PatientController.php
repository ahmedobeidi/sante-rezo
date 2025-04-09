<?php

namespace App\Controller;

use App\Entity\Appointment;
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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;


final class PatientController extends AbstractController
{
    #[Route('/patient', name: 'app_patient_profile')]
    #[IsGranted('ROLE_USER')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Ensure the user does not have ROLE_ADMIN or ROLE_DOCTOR
        if (!in_array('ROLE_PATIENT', $user->getRoles())) {
            return $this->redirectToRoute('app_home');
        }

        if ($user->isDeleted()) {
            throw $this->createAccessDeniedException('Votre compte a été supprimé.');
        }

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
    public function update(Request $request, EntityManagerInterface $entityManager, TokenStorageInterface $tokenStorage): Response
    {
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
    public function uploadImage(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
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
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $profileImage->guessExtension();

            try {
                $profileImage->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/profiles',
                    $newFilename
                );

                // Delete old image if exists
                if ($patient->getProfileImage()) {
                    $oldFile = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles/' . $patient->getProfileImage();
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

    #[Route('/patient/delete-image', name: 'app_patient_delete_image', methods: ['POST'])]
    public function deleteImage(EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $patient = $entityManager->getRepository(Patient::class)->findOneBy(['user' => $user]);

        if (!$patient || !$patient->getProfileImage()) {
            $this->addFlash('error', 'Aucune photo de profil à supprimer');
            return $this->redirectToRoute('app_patient_profile');
        }

        // Delete the file
        $oldFile = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles/' . $patient->getProfileImage();
        if (file_exists($oldFile)) {
            unlink($oldFile);
        }

        // Remove reference from database
        $patient->setProfileImage(null);
        $entityManager->flush();

        $this->addFlash('success', 'Photo de profil supprimée avec succès');
        return $this->redirectToRoute('app_patient_profile');
    }

    #[Route('/patient/handle-reset-password-form', name: 'app_patient_handle_reset_password_form', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function handleResetPasswordForm(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
    
        // Get form data
        $currentPassword = $request->request->get('current_password');
        $newPassword = $request->request->get('new_password');
        $confirmPassword = $request->request->get('confirm_password');

        // Check if any field is empty
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $this->addFlash('error', 'Tous les champs sont obligatoires.');
            return $this->redirectToRoute('app_patient_profile');
        }
    
        // Validate current password
        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
            return $this->redirectToRoute('app_patient_profile');
        }
    
        // Check if new passwords match
        if ($newPassword !== $confirmPassword) {
            $this->addFlash('error', 'Les nouveaux mots de passe ne correspondent pas.');
            return $this->redirectToRoute('app_patient_profile');
        }

         // Validate new password with regex
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/', $newPassword)) {
            $this->addFlash('error', 'Le nouveau mot de passe doit comporter au moins 6 caractères, dont au moins un chiffre, une majuscule et une minuscule.');
            return $this->redirectToRoute('app_patient_profile');
        }
    
        // Update the user's password
        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
        $entityManager->persist($user);
        $entityManager->flush();
    
        $this->addFlash('success', 'Votre mot de passe a été mis à jour avec succès.');
        return $this->redirectToRoute('app_patient_profile');
    }

    #[Route('/patient/delete-account', name: 'app_patient_delete_account', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function deleteAccount(EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Mark the account as deleted
        $user->setIsDeleted(true);
        $user->setDeletedDate(new \DateTimeImmutable());

        $entityManager->persist($user);
        $entityManager->flush();

        // Log the user out
        $this->addFlash('success', 'Votre compte a été supprimé avec succès.');
        return $this->redirectToRoute('app_logout');
    }

    #[Route('/patient/appointments', name: 'app_patient_appointments')]
    #[IsGranted('ROLE_PATIENT')]
    public function viewAppointments(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $patient = $entityManager->getRepository(Patient::class)->findOneBy(['user' => $user]);

        if (!$patient) {
            throw $this->createNotFoundException('Patient profile not found.');
        }

        // Fetch booked appointments
        $bookedAppointments = $entityManager->getRepository(Appointment::class)->findBy(['patient' => $patient]);

        // Handle search query
        $searchQuery = $request->query->get('search', '');
        $availableAppointments = [];
        if (!empty($searchQuery)) {
            $availableAppointments = $entityManager->getRepository(Appointment::class)->createQueryBuilder('a')
                ->join('a.doctor', 'd')
                ->where('a.status = :status')
                ->andWhere('LOWER(d.firstName) LIKE :search OR LOWER(d.lastName) LIKE :search')
                ->setParameter('status', 'available')
                ->setParameter('search', '%' . strtolower($searchQuery) . '%')
                ->getQuery()
                ->getResult();
        }

        return $this->render('patient/appointments.html.twig', [
            'bookedAppointments' => $bookedAppointments,
            'availableAppointments' => $availableAppointments,
            'searchQuery' => $searchQuery,
        ]);
    }

    #[Route('/patient/appointments/book/{id}', name: 'app_patient_book_appointment')]
    #[IsGranted('ROLE_PATIENT')]
    public function bookAppointment(Appointment $appointment, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $patient = $entityManager->getRepository(Patient::class)->findOneBy(['user' => $user]);

        if (!$patient) {
            throw $this->createNotFoundException('Patient profile not found.');
        }

        if ($appointment->getStatus() !== 'available') {
            $this->addFlash('error', 'This appointment is no longer available.');
            return $this->redirectToRoute('app_patient_appointments');
        }

        $appointment->setPatient($patient);
        $appointment->setStatus('booked');

        $entityManager->flush();

        $this->addFlash('success', 'Appointment booked successfully.');

        return $this->redirectToRoute('app_patient_appointments');
    }
}
