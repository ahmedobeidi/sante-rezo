<?php

namespace App\Controller\Admin;

use App\Entity\Specialty;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class SpecialtyCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Specialty::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Spécialité')
            ->setEntityLabelInPlural('Spécialités')
            ->setSearchFields(['name'])
            ->setDefaultSort(['name' => 'ASC']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->onlyOnDetail();
            
        yield TextField::new('name')
            ->setLabel('Nom')
            ->setHelp('Nom de la spécialité médicale');
            
        yield AssociationField::new('doctors')
            ->onlyOnDetail()
            ->setLabel('Médecins')
            ->setHelp('Médecins associés à cette spécialité');
    }
}