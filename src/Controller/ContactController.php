<?php
namespace App\Controller;

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
    
        // Initialize contact data
        $contactData = [
            'firstName' => '',
            'lastName' => '',
            'email' => '',
            'message' => '',
        ];
    
        if ($request->isMethod('POST')) {
            // Handle form submission
            $contactData['firstName'] = $request->request->get('firstName', $contactData['firstName']);
            $contactData['lastName'] = $request->request->get('lastName', $contactData['lastName']);
            $contactData['email'] = $request->request->get('email', $contactData['email']);
            $contactData['message'] = $request->request->get('message', '');
            
            // Validate the form data
            if (
                empty($contactData['firstName']) ||
                empty($contactData['lastName']) ||
                empty($contactData['email']) ||
                empty($contactData['message'])
            ) {
                $this->addFlash('error', 'Tous les champs sont obligatoires.');
                return $this->redirectToRoute('app_home', ['_fragment' => 'contact']);
            }
    
            // Send the email
            $email = (new TemplatedEmail())
                ->from(new Address($contactData['email'], $contactData['firstName'] . ' ' . $contactData['lastName']))
                ->to('contact@sante-rezo.com')
                ->subject('Nouveau message de contact')
                ->htmlTemplate('contact/email.html.twig')
                ->context([
                    'contactData' => $contactData,
                ]);
    
            $mailer->send($email);
    
            // Add success flash message
            $this->addFlash('success', 'Votre message a été envoyé avec succès.');
    
            // Redirect to the homepage
            return $this->redirectToRoute('app_home', ['_fragment' => 'contact']);
        }

        
    
        return $this->render('home/index.html.twig', [
            'contactData' => $contactData,
            'doctorCount' => $doctorCount,
            'patientCount' => $patientCount,
        ]);
    }
}