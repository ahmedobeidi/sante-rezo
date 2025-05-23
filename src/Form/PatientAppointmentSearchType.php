<?php

namespace App\Form;

use App\Entity\Specialty;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PatientAppointmentSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Define fixed list of major cities in France
        $cities = [
            'Paris' => 'Paris',
            'Marseille' => 'Marseille',
            'Lyon' => 'Lyon',
            'Toulouse' => 'Toulouse',
            'Nice' => 'Nice',
            'Nantes' => 'Nantes',
            'Strasbourg' => 'Strasbourg',
            'Montpellier' => 'Montpellier',
            'Bordeaux' => 'Bordeaux',
            'Lille' => 'Lille',
            'Rennes' => 'Rennes',
            'Reims' => 'Reims',
            'Le Havre' => 'Le Havre',
            'Saint-Étienne' => 'Saint-Étienne',
            'Toulon' => 'Toulon',
            'Angers' => 'Angers',
            'Grenoble' => 'Grenoble',
            'Dijon' => 'Dijon',
            'Nîmes' => 'Nîmes',
            'Aix-en-Provence' => 'Aix-en-Provence',
            'Casablanca' => 'Casablanca',
            'Agadir' => 'Agadir',
        ];

        $builder
            ->add('search', SearchType::class, [
                'label' => 'Nom du médecin',
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-gray-700 mb-1'
                ],
                'attr' => [
                    'class' => 'w-full p-3 border border-solid border-gray-400 rounded-md',
                    'placeholder' => 'Nom ou prénom...'
                ],
                'required' => false,
            ])
            ->add('specialty', EntityType::class, [
                'class' => Specialty::class,
                'choice_label' => 'name',
                'label' => 'Spécialité',
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-gray-700 mb-1'
                ],
                'attr' => [
                    'class' => 'w-full p-3 border border-solid border-gray-400 rounded-md'
                ],
                'placeholder' => 'Toutes les spécialités',
                'required' => false,
            ])
            ->add('city', ChoiceType::class, [
                'label' => 'Ville',
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-gray-700 mb-1'
                ],
                'attr' => [
                    'class' => 'w-full p-3 border border-solid border-gray-400 rounded-md'
                ],
                'placeholder' => 'Toutes les villes',
                'choices' => $cities,
                'required' => false,
            ])
            ->add('date', DateType::class, [
                'label' => 'Date souhaitée',
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-gray-700 mb-1'
                ],
                'attr' => [
                    'class' => 'w-full p-3 border border-solid border-gray-400 rounded-md cursor-pointer'
                ],
                'widget' => 'single_text',
                'required' => false,
                'html5' => true,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Rechercher',
                'attr' => [
                    'class' => 'bg-dark-blue text-white py-2 px-4 rounded hover:bg-off-blue'
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false, // Disable CSRF for search form since it's GET
            'allow_extra_fields' => true,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return ''; // This will prevent Symfony from prefixing form fields
    }
}