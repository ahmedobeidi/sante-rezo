<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW) // Prevent creating users from admin (they should register)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_EDIT, Action::DETAIL);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Utilisateur')
            ->setEntityLabelInPlural('Utilisateurs')
            ->setSearchFields(['id', 'email'])
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        $roles = ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_PATIENT', 'ROLE_DOCTOR'];

        yield IdField::new('id')
            ->onlyOnDetail();
            
        yield EmailField::new('email')
            ->setHelp('This is the user\'s login identifier');

        yield ChoiceField::new('roles')
            ->setChoices(array_combine($roles, $roles))
            ->allowMultipleChoices()
            ->renderExpanded()
            ->setHelp('Select the roles assigned to this user');

        yield BooleanField::new('isVerified')
            ->renderAsSwitch(true)
            ->setHelp('Whether the user has verified their email');
            
        yield TextField::new('resetToken')
            ->onlyOnDetail()
            ->setHelp('Password reset token (if any)');
            
        yield BooleanField::new('isDeleted')
            ->renderAsSwitch(false)
            ->setHelp('Soft-deleted accounts are inactive but data is preserved');
            
        yield DateTimeField::new('deletedDate')
            ->hideOnForm()
            ->setHelp('When this account was deleted (if applicable)');
            
        yield AssociationField::new('patient')
            ->setHelp('Associated patient profile')
            ->onlyOnDetail();
    }
}