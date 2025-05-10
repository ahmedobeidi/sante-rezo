<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DoctorCancelAppointmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cancel', SubmitType::class, [
                'label' => 'Annuler',
                'attr' => [
                    'class' => 'bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded text-sm',
                    'onclick' => "return confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?');"
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
        ]);
    }
}