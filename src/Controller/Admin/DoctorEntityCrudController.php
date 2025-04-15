<?php

namespace App\Controller\Admin;

use App\Entity\Doctor;
use App\Entity\Specialty;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

class DoctorEntityCrudController extends AbstractCrudController
{
    private AdminUrlGenerator $adminUrlGenerator;
    private EntityManagerInterface $entityManager;

    public function __construct(AdminUrlGenerator $adminUrlGenerator, EntityManagerInterface $entityManager)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->entityManager = $entityManager;
    }

    public static function getEntityFqcn(): string
    {
        return Doctor::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $deletePhoto = Action::new('deletePhoto', 'Delete Photo', 'fa fa-trash')
            ->linkToCrudAction('deleteDoctorPhoto')
            ->displayIf(static function (Doctor $doctor) {
                return $doctor->getProfileImage() !== null;
            })
            ->setHtmlAttributes([
                'onclick' => 'return confirm("Voulez-vous supprimer cette photo ? Cette action est irréversible.")',
            ]);

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $deletePhoto)
            ->disable(Action::NEW, Action::EDIT); // Disable both creating and editing doctor profiles
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Profil médecin')
            ->setEntityLabelInPlural('Profils médecins')
            ->setSearchFields(['firstName', 'lastName', 'city', 'address', 'user.email', 'specialty.name'])
            ->setDefaultSort(['id' => 'DESC'])
            ->setPaginatorPageSize(20);
    }

    public function configureFilters(Filters $filters): Filters
    {
        // Return empty filters object - no filters will be displayed
        return $filters;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->onlyOnDetail();
            
        yield TextField::new('firstName')
            ->setHelp('Prénom du médecin');
            
        yield TextField::new('lastName')
            ->setHelp('Nom du médecin');
            
        yield AssociationField::new('user')
        ->setFormTypeOption('query_builder', function ($repository) {
            return $repository->createQueryBuilder('u')
                ->where('u.roles LIKE :role')
                ->setParameter('role', '%ROLE_DOCTOR%')
                ->orderBy('u.id', 'ASC');
        })
        ->setFormTypeOption('choice_label', function(User $user) {
            return sprintf('ID: %d - %s', $user->getId(), $user->getEmail());
        })
        ->setHelp('Sélectionnez un compte médecin (utilisateurs avec ROLE_DOCTOR)');
            
        yield AssociationField::new('specialty')
            ->setHelp('Spécialité médicale');
            
        yield TextField::new('city')
            ->setHelp('Ville d\'exercice');
            
        yield TextField::new('address')
            ->hideOnIndex()
            ->setHelp('Adresse du cabinet');
            
        yield ImageField::new('profileImage')
            ->setBasePath('/uploads/profiles')
            ->setUploadDir('public/uploads/profiles')
            ->setUploadedFileNamePattern('[randomhash].[extension]')
            ->setHelp('Photo de profil du médecin');
    }

    public function deleteDoctorPhoto(AdminContext $context): Response
    {
        // Get the entity ID from the request
        $entityId = $context->getRequest()->query->get('entityId');
        
        if (!$entityId) {
            $this->addFlash('error', 'ID du médecin manquant');
            return $this->redirect($this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->generateUrl());
        }
        
        // Fetch the Doctor entity
        $doctor = $this->entityManager->getRepository(Doctor::class)->find($entityId);
        
        if (!$doctor) {
            $this->addFlash('error', 'Médecin non trouvé');
            return $this->redirect($this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->generateUrl());
        }
        
        if ($doctor->getProfileImage()) {
            // Delete the file from filesystem
            $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles/' . $doctor->getProfileImage();
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Remove the database reference
            $doctor->setProfileImage(null);
            $this->entityManager->flush();

            // Add a flash message
            $this->addFlash('success', 'Photo de profil du médecin supprimée');
        } else {
            $this->addFlash('info', 'Aucune photo de profil à supprimer');
        }

        // Redirect back to the doctor detail page
        return $this->redirect($this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($doctor->getId())
            ->generateUrl());
    }
}