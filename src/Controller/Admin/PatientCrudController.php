<?php

namespace App\Controller\Admin;

use App\Entity\Patient;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
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
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;

class PatientCrudController extends AbstractCrudController
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
        return Patient::class;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        
        // Join with User entity and filter by ROLE_PATIENT - database agnostic approach
        $queryBuilder
            ->join('entity.user', 'u')
            ->andWhere("u.roles LIKE :role")
            ->setParameter('role', '%ROLE_PATIENT%');
            
        return $queryBuilder;
    }

    public function configureActions(Actions $actions): Actions
    {
        $deletePhoto = Action::new('deletePhoto', 'Delete Photo', 'fa fa-trash')
            ->linkToCrudAction('deletePatientPhoto')
            ->displayIf(static function (Patient $patient) {
                return $patient->getProfileImage() !== null;
            })
            ->setHtmlAttributes([
                'onclick' => 'return confirm("Voulez-vous supprimer cette photo ? Cette action est irréversible.")',
            ]);

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $deletePhoto)
            ->disable(Action::NEW, Action::EDIT); // Disable both creating and editing patients
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Patient')
            ->setEntityLabelInPlural('Patients')
            ->setSearchFields(['firstName', 'lastName', 'city', 'address', 'user.email'])
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
            ->setHelp('Patient\'s first name');
            
        yield TextField::new('lastName')
            ->setHelp('Patient\'s last name');
            
        yield AssociationField::new('user')
            ->setFormTypeOption('choice_label', 'email')
            ->setHelp('Associated user account');
            
        yield TextField::new('city')
            ->setHelp('City of residence');
            
        yield TextField::new('address')
            ->hideOnIndex()
            ->setHelp('Patient\'s address');
            
        yield ImageField::new('profileImage')
            ->setBasePath('/uploads/profiles')
            ->setUploadDir('public/uploads/profiles')
            ->setUploadedFileNamePattern('[randomhash].[extension]')
            ->setHelp('Patient\'s profile picture');
    }

    public function deletePatientPhoto(AdminContext $context): Response
    {
        // Get the entity ID from the request
        $entityId = $context->getRequest()->query->get('entityId');
        
        if (!$entityId) {
            $this->addFlash('error', 'Patient ID is missing');
            return $this->redirect($this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->generateUrl());
        }
        
        // Fetch the Patient entity directly from the entity manager
        $patient = $this->entityManager->getRepository(Patient::class)->find($entityId);
        
        if (!$patient) {
            $this->addFlash('error', 'Patient not found');
            return $this->redirect($this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->generateUrl());
        }
        
        if ($patient->getProfileImage()) {
            // Delete the file from filesystem
            $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles/' . $patient->getProfileImage();
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Remove the database reference
            $patient->setProfileImage(null);
            $this->entityManager->flush();

            // Add a flash message
            $this->addFlash('success', 'Patient profile photo has been deleted');
        } else {
            $this->addFlash('info', 'No profile photo to delete');
        }

        // Redirect back to the patient detail page
        return $this->redirect($this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($patient->getId())
            ->generateUrl());
    }
}