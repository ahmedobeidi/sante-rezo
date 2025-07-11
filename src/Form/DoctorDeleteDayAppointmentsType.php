<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class DoctorDeleteDayAppointmentsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', HiddenType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => 'La date est requise'
                    ]),
                ],
                'data' => $options['date'],
            ])
            ->add('delete', SubmitType::class, [
                'label' => 'Supprimer les créneaux du jour',
                'attr' => [
                    'class' => 'bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded text-sm w-full',
                    'onclick' => "return confirm('Êtes-vous sûr de vouloir supprimer toutes les disponibilités pour cette journée ? Seuls les créneaux disponibles seront supprimés, les rendez-vous déjà réservés par les patients seront conservés.');"
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'delete_day_appointments',
            'date' => null,
        ]);

        $resolver->setRequired(['date']);
    }
}