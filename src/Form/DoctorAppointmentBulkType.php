<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class DoctorAppointmentBulkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $today = new \DateTime();
        
        $builder
            ->add('start_date', DateType::class, [
                'label' => 'Date de début',
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-gray-700 mb-1'
                ],
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'p-3 border border-gray-300 rounded-md w-full cursor-pointer',
                    'id' => 'start_date'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez sélectionner une date de début'
                    ]),
                    new GreaterThanOrEqual([
                        'value' => $today,
                        'message' => 'La date de début doit être aujourd\'hui ou dans le futur'
                    ])
                ],
            ])
            ->add('end_date', DateType::class, [
                'label' => 'Date de fin',
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-gray-700 mb-1'
                ],
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'p-3 border border-gray-300 rounded-md w-full cursor-pointer',
                    'id' => 'end_date'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez sélectionner une date de fin'
                    ]),
                    new Callback(function($endDate, ExecutionContextInterface $context) {
                        $form = $context->getRoot();
                        $startDate = $form->get('start_date')->getData();
                        if ($startDate && $endDate && $endDate < $startDate) {
                            $context->buildViolation('La date de fin doit être après la date de début')
                                ->addViolation();
                        }
                    })
                ],
            ])
            ->add('start_time', TimeType::class, [
                'label' => 'Heure de début',
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-gray-700 mb-1'
                ],
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'p-3 border border-gray-300 rounded-md w-full cursor-pointer',
                    'id' => 'start_time'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez sélectionner une heure de début'
                    ])
                ],
            ])
            ->add('end_time', TimeType::class, [
                'label' => 'Heure de fin',
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-gray-700 mb-1'
                ],
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'p-3 border border-gray-300 rounded-md w-full cursor-pointer',
                    'id' => 'end_time'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez sélectionner une heure de fin'
                    ]),
                    new Callback(function($endTime, ExecutionContextInterface $context) {
                        $form = $context->getRoot();
                        $startTime = $form->get('start_time')->getData();
                        if ($startTime && $endTime && $endTime <= $startTime) {
                            $context->buildViolation('L\'heure de fin doit être après l\'heure de début')
                                ->addViolation();
                        }
                    })
                ],
            ])
            ->add('duration', ChoiceType::class, [
                'label' => 'Durée de rendez-vous (minutes)',
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-gray-700 mb-1'
                ],
                'choices' => [
                    '15 minutes' => 15,
                    '30 minutes' => 30,
                    '45 minutes' => 45,
                    '60 minutes' => 60
                ],
                'preferred_choices' => [30],
                'attr' => [
                    'class' => 'p-3 border border-gray-300 rounded-md w-full',
                    'id' => 'duration'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez sélectionner une durée'
                    ])
                ],
            ])
            ->add('weekdays', ChoiceType::class, [
                'label' => 'Jours de la semaine',
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-gray-700 mb-2'
                ],
                'choices' => [
                    'Lundi' => 'monday',
                    'Mardi' => 'tuesday',
                    'Mercredi' => 'wednesday',
                    'Jeudi' => 'thursday',
                    'Vendredi' => 'friday',
                    'Samedi' => 'saturday',
                    'Dimanche' => 'sunday'
                ],
                'expanded' => true,
                'multiple' => true,
                'choice_attr' => function() {
                    return [
                        'class' => 'h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded'
                    ];
                },
                // Remove duplicate label_attr - this is what's causing issues
                // 'label_attr' => [
                //     'class' => 'ml-2 text-sm text-gray-700'
                // ],
                'row_attr' => [
                    'class' => 'flex flex-wrap gap-4'
                ],
                'data' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez sélectionner au moins un jour de la semaine'
                    ])
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Générer les rendez-vous',
                'attr' => [
                    'class' => 'bg-dark-blue text-white py-2 px-4 rounded hover:bg-off-blue mt-2'
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'doctor_appointment_bulk',
        ]);
    }
}