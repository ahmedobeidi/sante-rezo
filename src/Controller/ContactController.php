<?php
namespace App\Controller;

use App\DTO\ContactFormDTO;
use App\Form\ContactFormType;
use App\Repository\DoctorRepository;
use App\Repository\PatientRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact', methods: ['POST'])]
    public function handleContactForm(
        Request $request,
        DoctorRepository $doctorRepository,
        PatientRepository $patientRepository,
        MailerInterface $mailer
    ): Response {
        // Fetch doctor and patient counts
        $doctorCount = $doctorRepository->count([]);
        $patientCount = $patientRepository->count([]);
        
        // Create the contact form DTO with user data if available
        $contactFormDTO = new ContactFormDTO();
        
        // Pre-fill with user data if logged in
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
        
        // Create form
        $form = $this->createForm(ContactFormType::class, $contactFormDTO, [
            'action' => $this->generateUrl('app_contact'),
            'method' => 'POST',
        ]);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // Send the email
            $email = (new TemplatedEmail())
                ->from(new Address($contactFormDTO->email, $contactFormDTO->firstName . ' ' . $contactFormDTO->lastName))
                ->to('contact@sante-rezo.com')
                ->subject('Nouveau message de contact')
                ->htmlTemplate('contact/email.html.twig')
                ->context([
                    'contactData' => [
                        'firstName' => $contactFormDTO->firstName,
                        'lastName' => $contactFormDTO->lastName,
                        'email' => $contactFormDTO->email,
                        'message' => $contactFormDTO->message
                    ],
                ]);
            
            $mailer->send($email);
            
            // Add success flash message
            $this->addFlash('success', 'Votre message a été envoyé avec succès.');
            
            // Redirect to the homepage
            return $this->redirectToRoute('app_home', ['_fragment' => 'contact']);
        } elseif ($form->isSubmitted()) {
            // Form has errors
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
            return $this->redirectToRoute('app_home', ['_fragment' => 'contact']);
        }
        
        // Create the form view for rendering
        $formView = $form->createView();
        
        return $this->render('home/index.html.twig', [
            'contactForm' => $formView,
            'contactData' => [
                'firstName' => $contactFormDTO->firstName,
                'lastName' => $contactFormDTO->lastName,
                'email' => $contactFormDTO->email,
                'message' => $contactFormDTO->message
            ],
            'doctorCount' => $doctorCount,
            'patientCount' => $patientCount,
        ]);
    }
}