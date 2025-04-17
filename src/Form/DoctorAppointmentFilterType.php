<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DoctorAppointmentFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date_filter', DateType::class, [
                'label' => 'Filtrer par date',
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-gray-700 mb-1'
                ],
                'attr' => [
                    'class' => 'w-full p-3 border border-solid border-gray-400 rounded-md cursor-pointer',
                    'id' => 'date_filter'
                ],
                'widget' => 'single_text',
                'required' => false,
                'html5' => true,
                'data' => $options['date_filter'] ? new \DateTime($options['date_filter']) : null,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Filtrer',
                'attr' => [
                    'class' => 'bg-dark-blue text-white py-2 px-4 rounded hover:bg-off-blue w-full md:w-auto',
                ],
                'label_html' => true,
                'label_format' => '<i class="fas fa-search mr-2"></i> %name%',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false, // Disable CSRF for GET form
            'method' => 'GET',
            'date_filter' => null,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return ''; // This prevents the parameters from being namespaced in the URL
    }
}