<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\Doctor;
use App\Entity\Specialty;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Knp\Component\Pager\PaginatorInterface;

final class DoctorController extends AbstractController
{
    #[Route('/doctor', name: 'app_doctor_profile')]
    #[IsGranted('ROLE_USER')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Ensure the user has ROLE_DOCTOR
        if (!in_array('ROLE_DOCTOR', $user->getRoles())) {
            return $this->redirectToRoute('app_home');
        }

        if ($user->isDeleted()) {
            throw $this->createAccessDeniedException('Votre compte a été supprimé.');
        }

        $doctor = $entityManager->getRepository(Doctor::class)->findOneBy(['user' => $user]);

        if (!$doctor) {
            // Create a new doctor profile if it doesn't exist
            $doctor = new Doctor();
            $doctor->setUser($user);
            $entityManager->persist($doctor);
            $entityManager->flush();
        }

        // Get all specialties for the dropdown
        $specialties = $entityManager->getRepository(Specialty::class)->findAll();

        return $this->render('doctor/index.html.twig', [
            'doctor' => $doctor,
            'specialties' => $specialties,
        ]);
    }

    #[Route('/doctor/update', name: 'app_doctor_update', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function update(Request $request, EntityManagerInterface $entityManager, TokenStorageInterface $tokenStorage): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $doctor = $entityManager->getRepository(Doctor::class)->findOneBy(['user' => $user]);

        if (!$doctor) {
            throw $this->createNotFoundException('Profil médecin non trouvé');
        }

        // Get form data
        $firstName = $request->request->get('firstName');
        $lastName = $request->request->get('lastName');
        $city = $request->request->get('city');
        $address = $request->request->get('address');
        $specialtyId = $request->request->get('specialty');

        // Check if trying to empty existing data
        if (($doctor->getFirstName() && empty($firstName)) ||
            ($doctor->getLastName() && empty($lastName)) ||
            ($doctor->getCity() && empty($city)) ||
            ($doctor->getAddress() && empty($address))
        ) {
            $this->addFlash('error', 'Les champs peuvent être modifiés mais ne peuvent pas être vidés une fois remplis');
            return $this->redirectToRoute('app_doctor_profile');
        }

        // Check if any data has changed
        $hasChanges = false;

        if (!empty($firstName) && $firstName !== $doctor->getFirstName()) {
            $doctor->setFirstName($firstName);
            $hasChanges = true;
        }
        if (!empty($lastName) && $lastName !== $doctor->getLastName()) {
            $doctor->setLastName($lastName);
            $hasChanges = true;
        }
        if (!empty($city) && $city !== $doctor->getCity()) {
            $doctor->setCity($city);
            $hasChanges = true;
        }
        if (!empty($address) && $address !== $doctor->getAddress()) {
            $doctor->setAddress($address);
            $hasChanges = true;
        }

        // Handle specialty
        if (!empty($specialtyId)) {
            $specialty = $entityManager->getRepository(Specialty::class)->find($specialtyId);
            if ($specialty && $doctor->getSpecialty() !== $specialty) {
                $doctor->setSpecialty($specialty);
                $hasChanges = true;
            }
        }

        // Only proceed if there are changes
        if ($hasChanges) {
            if (
                $doctor->getFirstName() &&
                $doctor->getLastName() &&
                $doctor->getCity() &&
                $doctor->getAddress() &&
                $doctor->getSpecialty()
            ) {
                $doctor->setIsCompleted(true);
            } else {
                $doctor->setIsCompleted(false);
            }
        }

        $entityManager->persist($doctor);
        $entityManager->flush();
        $this->addFlash('success', 'Profil mis à jour avec succès');
        return $this->redirectToRoute('app_doctor_profile');
    }

    #[Route('/doctor/upload-image', name: 'app_doctor_upload_image', methods: ['POST'])]
    public function uploadImage(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $doctor = $entityManager->getRepository(Doctor::class)->findOneBy(['user' => $user]);

        if (!$doctor) {
            throw $this->createNotFoundException('Profil médecin non trouvé');
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
                if ($doctor->getProfileImage()) {
                    $oldFile = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles/' . $doctor->getProfileImage();
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }

                $doctor->setProfileImage($newFilename);
                $entityManager->flush();

                $this->addFlash('success', 'Photo de profil mise à jour avec succès');
            } catch (FileException $e) {
                $this->addFlash('error', 'Une erreur est survenue lors du téléchargement de l\'image');
            }
        }

        return $this->redirectToRoute('app_doctor_profile');
    }

    #[Route('/doctor/delete-image', name: 'app_doctor_delete_image', methods: ['POST'])]
    public function deleteImage(EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $doctor = $entityManager->getRepository(Doctor::class)->findOneBy(['user' => $user]);

        if (!$doctor || !$doctor->getProfileImage()) {
            $this->addFlash('error', 'Aucune photo de profil à supprimer');
            return $this->redirectToRoute('app_doctor_profile');
        }

        // Delete the file
        $oldFile = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles/' . $doctor->getProfileImage();
        if (file_exists($oldFile)) {
            unlink($oldFile);
        }

        // Remove reference from database
        $doctor->setProfileImage(null);
        $entityManager->flush();

        $this->addFlash('success', 'Photo de profil supprimée avec succès');
        return $this->redirectToRoute('app_doctor_profile');
    }

    #[Route('/doctor/handle-reset-password-form', name: 'app_doctor_handle_reset_password_form', methods: ['POST'])]
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
            return $this->redirectToRoute('app_doctor_profile');
        }
    
        // Validate current password
        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
            return $this->redirectToRoute('app_doctor_profile');
        }
    
        // Check if new passwords match
        if ($newPassword !== $confirmPassword) {
            $this->addFlash('error', 'Les nouveaux mots de passe ne correspondent pas.');
            return $this->redirectToRoute('app_doctor_profile');
        }

        // Validate new password with regex
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/', $newPassword)) {
            $this->addFlash('error', 'Le nouveau mot de passe doit comporter au moins 6 caractères, dont au moins un chiffre, une majuscule et une minuscule.');
            return $this->redirectToRoute('app_doctor_profile');
        }
    
        // Update the user's password
        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
        $entityManager->persist($user);
        $entityManager->flush();
    
        $this->addFlash('success', 'Votre mot de passe a été mis à jour avec succès.');
        return $this->redirectToRoute('app_doctor_profile');
    }

    #[Route('/doctor/delete-account', name: 'app_doctor_delete_account', methods: ['POST'])]
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

    #[Route('/doctor/appointments', name: 'app_doctor_appointments')]
    #[IsGranted('ROLE_DOCTOR')]
    public function manageAppointments(EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $doctor = $entityManager->getRepository(Doctor::class)->findOneBy(['user' => $user]);

        if ((!$doctor && in_array('ROLE_DOCTOR', $user->getRoles())) || ($doctor && !$doctor->isCompleted())) {
            $this->addFlash('error', 'Vous devez compléter votre profil pour gérer vos rendez-vous.');
            return $this->redirectToRoute('app_doctor_profile');
        }

        $appointments = $entityManager->getRepository(Appointment::class)->findBy(['doctor' => $doctor]);

        return $this->redirectToRoute('app_doctor_appointments_upcoming');
    }

    #[Route('/doctor/appointments/upcoming', name: 'app_doctor_appointments_upcoming')]
    #[IsGranted('ROLE_DOCTOR')]
    public function upcomingAppointments(
        Request $request,
        EntityManagerInterface $entityManager,
        PaginatorInterface $paginator
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $doctor = $entityManager->getRepository(Doctor::class)->findOneBy(['user' => $user]);

        if ((!$doctor && in_array('ROLE_DOCTOR', $user->getRoles())) || ($doctor && !$doctor->isCompleted())) {
            $this->addFlash('error', 'Vous devez compléter votre profil pour accéder à cette page.');
            return $this->redirectToRoute('app_doctor_profile');
        }

        // Fetch only upcoming appointments for the doctor
        $now = new \DateTime('now'); // Ensure the current date and time is used
        $queryBuilder = $entityManager->getRepository(Appointment::class)
            ->createQueryBuilder('a')
            ->where('a.doctor = :doctor')
            ->andWhere('a.date >= :now') // Filter out past appointments
            ->setParameter('doctor', $doctor)
            ->setParameter('now', $now->format('Y-m-d H:i:s')) // Format the date for comparison
            ->orderBy('a.date', 'ASC');

        // Paginate the results
        $appointments = $paginator->paginate(
            $queryBuilder, // QueryBuilder object
            $request->query->getInt('page', 1), // Current page number, default is 1
            3 // Number of results per page
        );

        return $this->render('doctor/appointments_upcoming.html.twig', [
            'appointments' => $appointments,
        ]);
    }

    #[Route('/doctor/appointments/add', name: 'app_doctor_appointments_add', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_DOCTOR')]
    public function addAppointment(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            /** @var User $user */
            $user = $this->getUser();
            $doctor = $entityManager->getRepository(Doctor::class)->findOneBy(['user' => $user]);

            if ((!$doctor && in_array('ROLE_DOCTOR', $user->getRoles())) || ($doctor && !$doctor->isCompleted())) {
                $this->addFlash('error', 'Vous devez compléter votre profil pour ajouter un rendez-vous.');
                return $this->redirectToRoute('app_doctor_profile');
            }

             // Get the date from the client
            $clientDate = $request->request->get('date');

            // Set the timezone for the client date
            $timezone = new \DateTimeZone('Europe/Paris'); // Adjust to your desired timezone
            $date = \DateTime::createFromFormat('Y-m-d\TH:i', $clientDate, $timezone);

            if (!$date) {
                $this->addFlash('error', 'La date fournie est invalide.');
                return $this->redirectToRoute('app_doctor_appointments_add');
            }

            // Get the current server date and set the same timezone
            $now = new \DateTime('now', $timezone);

            // Check if the date is in the past
            if ($date < $now) {
                $this->addFlash('error', 'Vous ne pouvez pas ajouter un rendez-vous avec une date passée.');
                return $this->redirectToRoute('app_doctor_appointments_add');
            }

            // Add one hour to the current time
            $oneHourLater = (clone $now)->modify('+1 hour');

            // Check if the date is within the next hour
            if ($date < $oneHourLater) {
                $this->addFlash('error', 'Vous ne pouvez pas ajouter un rendez-vous dans l\'heure qui suit.');
                return $this->redirectToRoute('app_doctor_appointments_add');
            }

            $appointment = new Appointment();
            $appointment->setDoctor($doctor);
            $appointment->setDate($date);
            $appointment->setStatus('disponible');

            $entityManager->persist($appointment);
            $entityManager->flush();

            $this->addFlash('success', 'Rendez-vous ajouté avec succès.');

            return $this->redirectToRoute('app_doctor_appointments_upcoming');
        }

        return $this->render('doctor/appointments_add.html.twig');
    }

    #[Route('/doctor/appointments/cancel/{id}', name: 'app_doctor_cancel_appointment', methods: ['POST'])]
    #[IsGranted('ROLE_DOCTOR')]
    public function cancelAppointment(Appointment $appointment, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $doctor = $entityManager->getRepository(Doctor::class)->findOneBy(['user' => $user]);

        if (!$doctor) {
            throw $this->createNotFoundException('Profil médecin introuvable.');
        }

        // Check if the appointment belongs to the doctor
        if ($appointment->getDoctor() !== $doctor) {
            $this->addFlash('error', 'Vous ne pouvez pas annuler ce rendez-vous.');
            return $this->redirectToRoute('app_doctor_appointments_upcoming');
        }

        // Check if the appointment is still available
        if ($appointment->getPatient() !== null) {
            $this->addFlash('error', 'Ce rendez-vous ne peut pas être annulé car il a déjà été réservé par un patient.');
            return $this->redirectToRoute('app_doctor_appointments_upcoming');
        }

        // Cancel the appointment
        $entityManager->remove($appointment);
        $entityManager->flush();

        $this->addFlash('success', 'Le rendez-vous a été annulé avec succès.');
        return $this->redirectToRoute('app_doctor_appointments_upcoming');
    }
}