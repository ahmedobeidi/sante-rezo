<?php

namespace App\Controller\Admin;

use App\Entity\Doctor;
use App\Entity\Patient;
use App\Entity\Specialty;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class AdminController extends AbstractDashboardController
{
    #[Route('/admin', name: 'app_admin')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_home');
        }

        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('admin/dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('SantéRézo Admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        
        // Users section
        yield MenuItem::section('Gestion des utilisateurs');
        yield MenuItem::linkToCrud('Users', 'fa fa-users', User::class);
        
        // Patients section
        yield MenuItem::section('Gestion des patients');
        yield MenuItem::linkToCrud('Patients', 'fa fa-heart', Patient::class);
        
        // Doctors section 
        yield MenuItem::section('Gestion des médecins');
        yield MenuItem::linkToCrud('Comptes', 'fa fa-user-md', User::class)
            ->setController(DoctorCrudController::class);
        yield MenuItem::linkToCrud('Médecins', 'fa fa-stethoscope', Doctor::class)
            ->setController(DoctorEntityCrudController::class);
        
        // Add Specialties
        yield MenuItem::linkToCrud('Spécialités', 'fa fa-tags', Specialty::class);

        // Navigation section
        // yield MenuItem::section('Navigation');
        // yield MenuItem::linkToRoute('Retour au site', 'fa fa-arrow-left', 'app_home');
    }
}