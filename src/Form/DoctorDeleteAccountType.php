<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DoctorDeleteAccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('submit', SubmitType::class, [
                'label' => 'Supprimer mon compte',
                'attr' => [
                    'class' => 'bg-red-500 text-white py-2 px-4 rounded hover:bg-red-600 text-[16px] w-full sm:w-auto sm:self-end',
                    'onclick' => "return confirm('Êtes-vous sûr de vouloir supprimer votre compte ? Cette action est irréversible.')"
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_csrf_token',
            'csrf_token_id' => 'delete-account',
        ]);
    }
}