<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\Doctor;
use App\Entity\Specialty;
use App\Entity\User;
use App\Form\DoctorAppointmentBulkType;
use App\Form\DoctorAppointmentFilterType;
use App\Form\DoctorAvailableAppointmentFilterType;
use App\Form\DoctorCancelAppointmentType;
use App\Form\DoctorDeleteAccountType;
use App\Form\DoctorDeleteImageType;
use App\Form\DoctorPasswordResetType;
use App\Form\DoctorProfileImageType;
use App\Form\DoctorUpdateType;
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

        // Create the profile image upload form
        $profileImageForm = $this->createForm(DoctorProfileImageType::class, null, [
            'action' => $this->generateUrl('app_doctor_upload_image'),
            'method' => 'POST',
        ]);

        // Create the delete image form
        $deleteImageForm = $this->createForm(DoctorDeleteImageType::class, null, [
            'action' => $this->generateUrl('app_doctor_delete_image'),
            'method' => 'POST',
        ]);

        // Create the update form
        $doctorUpdateForm = $this->createForm(DoctorUpdateType::class, $doctor, [
            'action' => $this->generateUrl('app_doctor_update'),
            'method' => 'POST',
        ]);

        // Create the password reset form
        $passwordResetForm = $this->createForm(DoctorPasswordResetType::class, null, [
            'action' => $this->generateUrl('app_doctor_handle_reset_password_form'),
            'method' => 'POST',
        ]);

        // Create the delete account form
        $deleteAccountForm = $this->createForm(DoctorDeleteAccountType::class, null, [
            'action' => $this->generateUrl('app_doctor_delete_account'),
            'method' => 'POST',
        ]);

        return $this->render('doctor/index.html.twig', [
            'doctor' => $doctor,
            'specialties' => $specialties,
            'profileImageForm' => $profileImageForm->createView(),
            'deleteImageForm' => $deleteImageForm->createView(),
            'doctorUpdateForm' => $doctorUpdateForm->createView(),
            'passwordResetForm' => $passwordResetForm->createView(),
            'deleteAccountForm' => $deleteAccountForm->createView(),
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

        $originalData = [
            'firstName' => $doctor->getFirstName(),
            'lastName' => $doctor->getLastName(),
            'city' => $doctor->getCity(),
            'address' => $doctor->getAddress(),
            'specialty' => $doctor->getSpecialty(),
        ];

        // Create and handle form submission
        $form = $this->createForm(DoctorUpdateType::class, $doctor);

        try {
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // Check if trying to empty existing data
                if (($originalData['firstName'] && empty($doctor->getFirstName())) ||
                    ($originalData['lastName'] && empty($doctor->getLastName())) ||
                    ($originalData['city'] && empty($doctor->getCity())) ||
                    ($originalData['address'] && empty($doctor->getAddress())) ||
                    ($originalData['specialty'] && empty($doctor->getSpecialty()))
                ) {
                    $this->addFlash('error', 'Les champs peuvent être modifiés mais ne peuvent pas être vidés une fois remplis');
                    return $this->redirectToRoute('app_doctor_profile');
                }

                // Check if any data has changed
                $hasChanges =
                    $originalData['firstName'] !== $doctor->getFirstName() ||
                    $originalData['lastName'] !== $doctor->getLastName() ||
                    $originalData['city'] !== $doctor->getCity() ||
                    $originalData['address'] !== $doctor->getAddress() ||
                    $originalData['specialty'] !== $doctor->getSpecialty();

                // Only proceed if there are changes
                if ($hasChanges) {
                    // Check if profile is complete to set isCompleted flag
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

                    $entityManager->persist($doctor);
                    $entityManager->flush();
                    $this->addFlash('success', 'Profil mis à jour avec succès');
                } else {
                    $this->addFlash('info', 'Aucune modification détectée.');
                }
            } else if ($form->isSubmitted()) {
                // Form has validation errors
                foreach ($form->getErrors(true) as $error) {
                    $this->addFlash('error', $error->getMessage());
                }

                // Check for specific field errors
                foreach (['lastName', 'firstName', 'city', 'address', 'specialty'] as $field) {
                    if ($form->get($field)->getErrors()->count() > 0) {
                        foreach ($form->get($field)->getErrors() as $error) {
                            $this->addFlash('error', 'Erreur dans le champ ' . $field . ': ' . $error->getMessage());
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de la mise à jour du profil: ' . $e->getMessage());
        }

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

        // Create the form
        $form = $this->createForm(DoctorProfileImageType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $profileImage */
            $profileImage = $form->get('profileImage')->getData();

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
                    $this->addFlash('error', 'Une erreur est survenue lors du téléchargement de l\'image: ' . $e->getMessage());
                }
            }
        } elseif ($form->isSubmitted()) {
            // Form has validation errors - add them as flash messages
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->redirectToRoute('app_doctor_profile');
    }

    #[Route('/doctor/delete-image', name: 'app_doctor_delete_image', methods: ['POST'])]
    public function deleteImage(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $doctor = $entityManager->getRepository(Doctor::class)->findOneBy(['user' => $user]);

        if (!$doctor || !$doctor->getProfileImage()) {
            $this->addFlash('error', 'Aucune photo de profil à supprimer');
            return $this->redirectToRoute('app_doctor_profile');
        }

        // Create and handle form submission
        $form = $this->createForm(DoctorDeleteImageType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Delete the file
            $oldFile = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles/' . $doctor->getProfileImage();
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }

            // Remove reference from database
            $doctor->setProfileImage(null);
            $entityManager->flush();

            $this->addFlash('success', 'Photo de profil supprimée avec succès');
        } else {
            $this->addFlash('error', 'Une erreur est survenue lors de la suppression de la photo');
        }

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

        // Create the form
        $form = $this->createForm(DoctorPasswordResetType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get form data
            $currentPassword = $form->get('currentPassword')->getData();
            $newPassword = $form->get('newPassword')->getData();

            // Validate current password
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
                return $this->redirectToRoute('app_doctor_profile');
            }

            // Update the user's password
            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a été mis à jour avec succès.');
        } else if ($form->isSubmitted()) {
            // Form has validation errors
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }

            // Add specific field errors
            if ($form->get('currentPassword')->getErrors()->count() > 0) {
                foreach ($form->get('currentPassword')->getErrors() as $error) {
                    $this->addFlash('error', 'Mot de passe actuel: ' . $error->getMessage());
                }
            }

            if ($form->get('newPassword')->getErrors()->count() > 0) {
                foreach ($form->get('newPassword')->getErrors() as $error) {
                    $this->addFlash('error', 'Nouveau mot de passe: ' . $error->getMessage());
                }
            }
        }

        return $this->redirectToRoute('app_doctor_profile');
    }

    #[Route('/doctor/delete-account', name: 'app_doctor_delete_account', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function deleteAccount(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Create and handle the form
        $form = $this->createForm(DoctorDeleteAccountType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Mark the account as deleted (soft delete)
            $user->setIsDeleted(true);
            $user->setDeletedDate(new \DateTimeImmutable());

            $entityManager->persist($user);
            $entityManager->flush();

            // Log the user out
            $this->addFlash('success', 'Votre compte a été supprimé avec succès.');
            return $this->redirectToRoute('app_logout');
        }

        // If form validation fails, redirect back to profile
        $this->addFlash('error', 'Une erreur est survenue lors de la suppression du compte.');
        return $this->redirectToRoute('app_doctor_profile');
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

        // Get date filter if any
        $dateFilter = $request->query->get('date_filter');

        // Create and handle the filter form
        $filterForm = $this->createForm(DoctorAppointmentFilterType::class, null, [
            'date_filter' => $dateFilter,
            'method' => 'GET',
            'action' => $this->generateUrl('app_doctor_appointments_upcoming'),
        ]);

        $filterForm->handleRequest($request);

        // Set timezone to ensure correct filtering
        $timezone = new \DateTimeZone('Europe/Paris');
        $now = new \DateTime('now', $timezone);

        // Fetch only upcoming appointments for the doctor that have been reserved by patients
        $queryBuilder = $entityManager->getRepository(Appointment::class)
            ->createQueryBuilder('a')
            ->where('a.doctor = :doctor')
            ->andWhere('a.date >= :now') // Filter out past appointments
            ->andWhere('a.patient IS NOT NULL') // Only show appointments with patients
            ->setParameter('doctor', $doctor)
            ->setParameter('now', $now->format('Y-m-d H:i:s')) // Format the date for comparison
            ->orderBy('a.date', 'ASC');

        // Apply date filter if provided
        if (!empty($dateFilter)) {
            $filterDate = new \DateTime($dateFilter, $timezone);
            $nextDay = (clone $filterDate)->modify('+1 day');
            $queryBuilder->andWhere('a.date >= :filterDate AND a.date < :nextDay')
                ->setParameter('filterDate', $filterDate->format('Y-m-d'))
                ->setParameter('nextDay', $nextDay->format('Y-m-d'));
        }

        // Get all upcoming appointments
        $allAppointments = $queryBuilder->getQuery()->getResult();

        // Group appointments by day
        $appointmentsByDay = [];
        foreach ($allAppointments as $appointment) {
            $dateKey = $appointment->getDate()->format('Y-m-d');
            if (!isset($appointmentsByDay[$dateKey])) {
                $appointmentsByDay[$dateKey] = [];
            }
            $appointmentsByDay[$dateKey][] = $appointment;
        }

        // Get the unique dates and paginate them
        $dateKeys = array_keys($appointmentsByDay);
        $paginatedDates = $paginator->paginate(
            $dateKeys,
            $request->query->getInt('page', 1),
            1 // Show 1 day per page
        );

        // Create the filtered appointments by day array
        $filteredAppointmentsByDay = [];
        foreach ($paginatedDates as $dateKey) {
            if (isset($appointmentsByDay[$dateKey])) {
                $filteredAppointmentsByDay[$dateKey] = $appointmentsByDay[$dateKey];
            }
        }

        return $this->render('doctor/appointments_upcoming.html.twig', [
            'appointmentsByDay' => $filteredAppointmentsByDay,
            'appointments' => $paginatedDates, // This is now a proper paginator object
            'date_filter' => $dateFilter,
            'filterForm' => $filterForm->createView(),
        ]);
    }

    #[Route('/doctor/appointments/add', name: 'app_doctor_appointments_add', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_DOCTOR')]
    public function addAppointment(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $doctor = $entityManager->getRepository(Doctor::class)->findOneBy(['user' => $user]);

        if ((!$doctor && in_array('ROLE_DOCTOR', $user->getRoles())) || ($doctor && !$doctor->isCompleted())) {
            $this->addFlash('error', 'Vous devez compléter votre profil pour ajouter un rendez-vous.');
            return $this->redirectToRoute('app_doctor_profile');
        }

        // For individual appointment form (commented out in your template)
        if ($request->isMethod('POST')) {
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

        // Create the bulk appointment form
        $bulkForm = $this->createForm(DoctorAppointmentBulkType::class, null, [
            'action' => $this->generateUrl('app_doctor_appointments_add_bulk'),
            'method' => 'POST',
        ]);

        return $this->render('doctor/appointments_add.html.twig', [
            'bulkForm' => $bulkForm->createView(),
        ]);
    }

    #[Route('/doctor/appointments/cancel/{id}', name: 'app_doctor_cancel_appointment', methods: ['POST'])]
    #[IsGranted('ROLE_DOCTOR')]
    public function cancelAppointment(
        Request $request,
        Appointment $appointment,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $doctor = $entityManager->getRepository(Doctor::class)->findOneBy(['user' => $user]);

        // Security check - ensure the appointment belongs to this doctor
        if ($appointment->getDoctor() !== $doctor) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à annuler ce rendez-vous.');
        }

        // Create and handle the form
        $form = $this->createForm(DoctorCancelAppointmentType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check if the appointment has a patient
            if ($appointment->getPatient()) {
                $this->addFlash('error', 'Impossible d\'annuler un rendez-vous déjà réservé par un patient.');
                return $this->redirectToRoute('app_doctor_appointments_available');
            }

            // Delete the available appointment
            $entityManager->remove($appointment);
            $entityManager->flush();

            $this->addFlash('success', 'Le rendez-vous a été annulé avec succès.');
        } else {
            $this->addFlash('error', 'Une erreur est survenue lors de l\'annulation du rendez-vous.');
        }

        // Redirect back to available appointments page
        return $this->redirectToRoute('app_doctor_appointments_available');
    }

    #[Route('/doctor/appointments/add-bulk', name: 'app_doctor_appointments_add_bulk', methods: ['POST'])]
    #[IsGranted('ROLE_DOCTOR')]
    public function addBulkAppointments(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $doctor = $entityManager->getRepository(Doctor::class)->findOneBy(['user' => $user]);

        if ((!$doctor && in_array('ROLE_DOCTOR', $user->getRoles())) || ($doctor && !$doctor->isCompleted())) {
            $this->addFlash('error', 'Vous devez compléter votre profil pour ajouter des rendez-vous.');
            return $this->redirectToRoute('app_doctor_profile');
        }

        // Create and handle the form
        $form = $this->createForm(DoctorAppointmentBulkType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get form data
            $startDate = $form->get('start_date')->getData();
            $endDate = $form->get('end_date')->getData();
            $startTime = $form->get('start_time')->getData();
            $endTime = $form->get('end_time')->getData();
            $duration = $form->get('duration')->getData();
            $weekdays = $form->get('weekdays')->getData();

            // Map day names to day of week numbers
            $dayMap = [
                'monday' => 1,
                'tuesday' => 2,
                'wednesday' => 3,
                'thursday' => 4,
                'friday' => 5,
                'saturday' => 6,
                'sunday' => 7
            ];

            // Convert weekdays to numbers
            $selectedDays = [];
            foreach ($weekdays as $day) {
                if (isset($dayMap[$day])) {
                    $selectedDays[] = $dayMap[$day];
                }
            }

            // Set timezone 
            $timezone = new \DateTimeZone('Europe/Paris');

            // Create DateTime objects for the date range
            $currentDate = clone $startDate;
            $lastDate = clone $endDate;
            $lastDate->modify('+1 day'); // Include the end date

            // Get current time for validation
            $now = new \DateTime('now', $timezone);
            $oneHourLater = (clone $now)->modify('+1 hour');

            // Create interval for appointment duration
            $durationInterval = new \DateInterval('PT' . $duration . 'M');

            // Track created appointments
            $createdCount = 0;
            $skippedCount = 0;

            // Loop through each day in the range
            while ($currentDate < $lastDate) {
                // Check if current day is a selected weekday
                $dayOfWeek = (int) $currentDate->format('N'); // 1 (Monday) to 7 (Sunday)

                if (in_array($dayOfWeek, $selectedDays)) {
                    // Create appointment times for this day
                    $appointmentStart = clone $currentDate;
                    $appointmentStart->setTime(
                        (int) $startTime->format('H'),
                        (int) $startTime->format('i')
                    );

                    $dayEndTime = clone $currentDate;
                    $dayEndTime->setTime(
                        (int) $endTime->format('H'),
                        (int) $endTime->format('i')
                    );

                    // Loop through each time slot on this day
                    while ($appointmentStart < $dayEndTime) {
                        $appointmentEnd = clone $appointmentStart;
                        $appointmentEnd->add($durationInterval);

                        // Check if this appointment end time exceeds the day's end time
                        if ($appointmentEnd > $dayEndTime) {
                            break;
                        }

                        // Skip if appointment is in the past or within next hour
                        if ($appointmentStart > $oneHourLater) {
                            // Check if an appointment already exists at this time
                            $existingAppointment = $entityManager->getRepository(Appointment::class)->findOneBy([
                                'doctor' => $doctor,
                                'date' => $appointmentStart
                            ]);

                            if (!$existingAppointment) {
                                // Create new appointment
                                $appointment = new Appointment();
                                $appointment->setDoctor($doctor);
                                $appointment->setDate(clone $appointmentStart);
                                $appointment->setStatus('disponible');

                                $entityManager->persist($appointment);
                                $createdCount++;
                            } else {
                                $skippedCount++;
                            }
                        } else {
                            $skippedCount++;
                        }

                        // Move to next time slot
                        $appointmentStart->add($durationInterval);
                    }
                }

                // Move to next day
                $currentDate->modify('+1 day');
            }

            // Flush all created appointments
            $entityManager->flush();

            if ($createdCount > 0) {
                $this->addFlash('success', $createdCount . ' rendez-vous ont été ajoutés avec succès. '
                    . ($skippedCount > 0 ? $skippedCount . ' ont été ignorés (déjà existants ou trop proches).' : ''));
            } else {
                $this->addFlash('error', 'Aucun rendez-vous n\'a été créé. Veuillez vérifier vos paramètres.');
            }

            return $this->redirectToRoute('app_doctor_appointments_available');
        } else if ($form->isSubmitted()) {
            // Add flash messages for validation errors
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->redirectToRoute('app_doctor_appointments_add');
    }

    #[Route('/doctor/appointments/available', name: 'app_doctor_appointments_available')]
    #[IsGranted('ROLE_DOCTOR')]
    public function availableAppointments(
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

        // Get date filter if any
        $dateFilter = $request->query->get('date_filter');

        // Create and handle the filter form
        $filterForm = $this->createForm(DoctorAvailableAppointmentFilterType::class, null, [
            'date_filter' => $dateFilter,
            'method' => 'GET',
            'action' => $this->generateUrl('app_doctor_appointments_available'),
        ]);

        $filterForm->handleRequest($request);

        // Set timezone to ensure correct filtering
        $timezone = new \DateTimeZone('Europe/Paris');
        $now = new \DateTime('now', $timezone);

        // Fetch only available appointments for the doctor
        $queryBuilder = $entityManager->getRepository(Appointment::class)
            ->createQueryBuilder('a')
            ->where('a.doctor = :doctor')
            ->andWhere('a.date >= :now') // Filter out past appointments
            ->andWhere('a.status = :status') // Only show available appointments
            ->andWhere('a.patient IS NULL') // Only show appointments without patients
            ->setParameter('doctor', $doctor)
            ->setParameter('now', $now->format('Y-m-d H:i:s'))
            ->setParameter('status', 'disponible')
            ->orderBy('a.date', 'ASC');

        // Apply date filter if provided
        if (!empty($dateFilter)) {
            $filterDate = new \DateTime($dateFilter, $timezone);
            $nextDay = (clone $filterDate)->modify('+1 day');
            $queryBuilder->andWhere('a.date >= :filterDate AND a.date < :nextDay')
                ->setParameter('filterDate', $filterDate->format('Y-m-d'))
                ->setParameter('nextDay', $nextDay->format('Y-m-d'));
        }

        // Get all available appointments
        $allAppointments = $queryBuilder->getQuery()->getResult();

        // Group appointments by day
        $appointmentsByDay = [];
        foreach ($allAppointments as $appointment) {
            $dateKey = $appointment->getDate()->format('Y-m-d');
            if (!isset($appointmentsByDay[$dateKey])) {
                $appointmentsByDay[$dateKey] = [];
            }
            $appointmentsByDay[$dateKey][] = $appointment;
        }

        // Get the unique dates and paginate them
        $dateKeys = array_keys($appointmentsByDay);
        $paginatedDates = $paginator->paginate(
            $dateKeys,
            $request->query->getInt('page', 1),
            1 // Show 1 day per page
        );

        // Create the filtered appointments by day array
        $filteredAppointmentsByDay = [];
        foreach ($paginatedDates as $dateKey) {
            if (isset($appointmentsByDay[$dateKey])) {
                $filteredAppointmentsByDay[$dateKey] = $appointmentsByDay[$dateKey];
            }
        }

        // Create cancel forms for each appointment
        $cancelForms = [];
        foreach ($filteredAppointmentsByDay as $dateKey => $appointments) {
            foreach ($appointments as $appointment) {
                $cancelForms[$appointment->getId()] = $this->createForm(
                    DoctorCancelAppointmentType::class,
                    null,
                    [
                        'action' => $this->generateUrl('app_doctor_cancel_appointment', ['id' => $appointment->getId()]),
                        'method' => 'POST',
                    ]
                )->createView();
            }
        }

        return $this->render('doctor/appointments_available.html.twig', [
            'appointmentsByDay' => $filteredAppointmentsByDay,
            'appointments' => $paginatedDates,
            'date_filter' => $dateFilter,
            'filterForm' => $filterForm->createView(),
            'cancelForms' => $cancelForms,
        ]);
    }
}
