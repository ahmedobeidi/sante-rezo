<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\Patient;
use App\Entity\Specialty;
use App\Entity\User;
use App\Form\PatientProfileImageType;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
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

        // Create the profile image upload form
        $profileImageForm = $this->createForm(PatientProfileImageType::class, null, [
            'action' => $this->generateUrl('app_patient_upload_image'),
            'method' => 'POST',
        ]);

        return $this->render('patient/index.html.twig', [
            'patient' => $patient,
            'profileImageForm' => $profileImageForm->createView(),
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
            throw $this->createNotFoundException('Profil patient introuvable.');
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

        // Create the form
        $form = $this->createForm(PatientProfileImageType::class);
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
                    $this->addFlash('error', 'Une erreur est survenue lors du téléchargement de l\'image: ' . $e->getMessage());
                }
            }
        } elseif ($form->isSubmitted()) {
            // Form has validation errors - add them as flash messages
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
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
    #[IsGranted('ROLE_USER')]
    public function viewAppointments(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $patient = $entityManager->getRepository(Patient::class)->findOneBy(['user' => $user]);

        // Redirect if the user does not have ROLE_PATIENT
        if (!in_array('ROLE_PATIENT', $user->getRoles())) {
            $this->addFlash('error', 'Vous devez compléter votre profil pour accéder à cette page.');
            return $this->redirectToRoute('app_patient_profile');
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
                ->setParameter('status', 'disponible')
                ->setParameter('search', '%' . strtolower($searchQuery) . '%')
                ->getQuery()
                ->getResult();
        }

        // return $this->render('patient/appointments.html.twig', [
        //     'bookedAppointments' => $bookedAppointments,
        //     'availableAppointments' => $availableAppointments,
        //     'searchQuery' => $searchQuery,
        // ]);

        return $this->redirectToRoute('app_patient_appointments_upcoming');
    }

    #[Route('/patient/appointments/book/{id}', name: 'app_patient_book_appointment')]
    #[IsGranted('ROLE_PATIENT')]
    public function bookAppointment(Appointment $appointment, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $patient = $entityManager->getRepository(Patient::class)->findOneBy(['user' => $user]);

        if (!$patient) {
            throw $this->createNotFoundException('Profil patient introuvable');
        }

        if ($appointment->getStatus() !== 'disponible') {
            $this->addFlash('error', 'Ce rendez-vous n\'est plus disponible.');
            return $this->redirectToRoute('app_patient_appointments');
        }

        // Check if patient already has 2 or more future appointments with this doctor
        $now = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        $doctorId = $appointment->getDoctor()->getId();
        
        $existingAppointments = $entityManager->getRepository(Appointment::class)
            ->createQueryBuilder('a')
            ->where('a.patient = :patient')
            ->andWhere('a.doctor = :doctor')
            ->andWhere('a.date >= :now')
            ->andWhere('a.status = :status')
            ->setParameter('patient', $patient)
            ->setParameter('doctor', $doctorId)
            ->setParameter('now', $now)
            ->setParameter('status', 'réservé')
            ->getQuery()
            ->getResult();
        
        if (count($existingAppointments) >= 2) {
            $this->addFlash('error', 'Vous avez déjà deux rendez-vous programmés avec ce médecin. Vous ne pouvez pas en réserver plus pour le moment.');
            return $this->redirectToRoute('app_patient_appointments_available');
        }

        $appointment->setPatient($patient);
        $appointment->setStatus('réservé');

        $entityManager->flush();

        $this->addFlash('success', 'Rendez-vous réservé avec succès.');

        return $this->redirectToRoute('app_patient_appointments');
    }

    #[Route('/patient/appointments/cancel/{id}', name: 'app_patient_cancel_appointment', methods: ['POST'])]
    #[IsGranted('ROLE_PATIENT')]
    public function cancelAppointment(Appointment $appointment, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $patient = $entityManager->getRepository(Patient::class)->findOneBy(['user' => $user]);

        if (!$patient || $appointment->getPatient() !== $patient) {
            $this->addFlash('error', 'Vous ne pouvez pas annuler ce rendez-vous.');
            return $this->redirectToRoute('app_patient_appointments');
        }

        // Cancel the appointment
        $appointment->setPatient(null);
        $appointment->setStatus('disponible');
        $entityManager->flush();

        $this->addFlash('success', 'Le rendez-vous a été annulé avec succès.');
        return $this->redirectToRoute('app_patient_appointments');
    }

    #[Route('/patient/appointments/upcoming', name: 'app_patient_appointments_upcoming')]
    #[IsGranted('ROLE_PATIENT')]
    public function upcomingAppointments(
        Request $request,
        EntityManagerInterface $entityManager,
        PaginatorInterface $paginator
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $patient = $entityManager->getRepository(Patient::class)->findOneBy(['user' => $user]);

        // Redirect if the user does not have ROLE_PATIENT
        if (!in_array('ROLE_PATIENT', $user->getRoles())) {
            $this->addFlash('error', 'Vous devez compléter votre profil pour accéder à cette page.');
            return $this->redirectToRoute('app_patient_profile');
        }
        // Set timezone to ensure correct filtering
        $timezone = new \DateTimeZone('Europe/Paris');
        $now = new \DateTime('now', $timezone);

        // Fetch only upcoming appointments for the patient
        $queryBuilder = $entityManager->getRepository(Appointment::class)
            ->createQueryBuilder('a')
            ->where('a.patient = :patient')
            ->andWhere('a.date >= :now') // Filter out past appointments
            ->setParameter('patient', $patient)
            ->setParameter('now', $now->format('Y-m-d H:i:s'))
            ->orderBy('a.date', 'ASC');

        // Paginate the results
        $bookedAppointments = $paginator->paginate(
            $queryBuilder, // QueryBuilder object
            $request->query->getInt('page', 1), // Current page number, default is 1
            3 // Number of results per page
        );

        return $this->render('patient/appointments_upcoming.html.twig', [
            'bookedAppointments' => $bookedAppointments,
        ]);
    }

    #[Route('/patient/appointments/available', name: 'app_patient_appointments_available')]
    #[IsGranted('ROLE_PATIENT')]
    public function availableAppointments(
        Request $request,
        EntityManagerInterface $entityManager,
        PaginatorInterface $paginator
    ): Response {
        // Get filter parameters
        $searchQuery = $request->query->get('search', '');
        $specialtyId = $request->query->get('specialty', '');
        $cityFilter = $request->query->get('city', '');
        $dateFilter = $request->query->get('date', '');

        // Set timezone to ensure correct filtering
        $timezone = new \DateTimeZone('Europe/Paris');
        $now = new \DateTime('now', $timezone);

        // First, get the base query for doctors with available appointments
        $doctorQueryBuilder = $entityManager->createQueryBuilder()
            ->select('DISTINCT d')
            ->from('App\Entity\Doctor', 'd')
            ->join('d.appointments', 'a')
            ->where('a.status = :status')
            ->andWhere('a.date >= :now')
            ->setParameter('status', 'disponible')
            ->setParameter('now', $now);

        // Add name search filter if provided
        if (!empty($searchQuery)) {
            $doctorQueryBuilder->andWhere('LOWER(d.firstName) LIKE :search OR LOWER(d.lastName) LIKE :search')
                ->setParameter('search', '%' . strtolower($searchQuery) . '%');
        }

        // Add city filter if provided
        if (!empty($cityFilter)) {
            $doctorQueryBuilder->andWhere('LOWER(d.city) LIKE :city')
                ->setParameter('city', '%' . strtolower($cityFilter) . '%');
        }

        // Add specialty filter if provided
        if (!empty($specialtyId)) {
            $doctorQueryBuilder->andWhere('d.specialty = :specialty')
                ->setParameter('specialty', $specialtyId);
        }

        // Add date filter if provided
        if (!empty($dateFilter)) {
            $filterDate = new \DateTime($dateFilter, $timezone);
            $nextDay = (clone $filterDate)->modify('+1 day');
            $doctorQueryBuilder->andWhere('a.date >= :filterDate AND a.date < :nextDay')
                ->setParameter('filterDate', $filterDate->format('Y-m-d'))
                ->setParameter('nextDay', $nextDay->format('Y-m-d'));
        }

        // Order doctors
        $doctorQueryBuilder->orderBy('d.lastName', 'ASC')
            ->addOrderBy('d.firstName', 'ASC');

        // Get all specialties for the filter dropdown
        $specialties = $entityManager->getRepository(Specialty::class)
            ->findBy([], ['name' => 'ASC']);

        // Paginate doctors (not appointments)
        $paginatedDoctors = $paginator->paginate(
            $doctorQueryBuilder,
            $request->query->getInt('page', 1),
            3 // 3 doctors per page
        );

        // Get the doctor IDs from the current page
        $doctorIds = [];
        foreach ($paginatedDoctors as $doctor) {
            $doctorIds[] = $doctor->getId();
        }

        // Now load all appointments for these doctors
        $appointmentsQuery = $entityManager->getRepository(Appointment::class)->createQueryBuilder('a')
            ->join('a.doctor', 'd')
            ->where('a.status = :status')
            ->andWhere('a.date >= :now')
            ->andWhere('d.id IN (:doctorIds)')
            ->setParameter('status', 'disponible')
            ->setParameter('now', $now)
            ->setParameter('doctorIds', $doctorIds)
            ->orderBy('d.lastName', 'ASC')
            ->addOrderBy('d.firstName', 'ASC')
            ->addOrderBy('a.date', 'ASC');

        // Apply date filter to appointments if provided
        if (!empty($dateFilter)) {
            $filterDate = new \DateTime($dateFilter, $timezone);
            $nextDay = (clone $filterDate)->modify('+1 day');
            $appointmentsQuery->andWhere('a.date >= :filterDate AND a.date < :nextDay')
                ->setParameter('filterDate', $filterDate->format('Y-m-d'))
                ->setParameter('nextDay', $nextDay->format('Y-m-d'));
        }

        $availableAppointments = $appointmentsQuery->getQuery()->getResult();

        // Get sorted doctors for the template
        $doctors = [];
        foreach ($paginatedDoctors as $doctor) {
            $doctors[] = $doctor;
        }

        return $this->render('patient/appointments_available.html.twig', [
            'availableAppointments' => $availableAppointments,
            'doctors' => $doctors, // Passing the doctor entities directly
            'paginatedDoctors' => $paginatedDoctors, // For pagination controls
            'searchQuery' => $searchQuery,
            'specialtyId' => $specialtyId,
            'cityFilter' => $cityFilter,
            'dateFilter' => $dateFilter,
            'specialties' => $specialties
        ]);
    }
}
