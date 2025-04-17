<?php

namespace App\Form;

use App\Entity\Doctor;
use App\Entity\Specialty;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class DoctorUpdateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Get French cities for dropdown
        $cities = [
            "Paris", "Marseille", "Lyon", "Toulouse", "Nice", "Nantes", "Strasbourg", "Montpellier", 
            "Bordeaux", "Lille", "Rennes", "Reims", "Le Havre", "Saint-Étienne", "Toulon", 
            "Grenoble", "Dijon", "Angers", "Villeurbanne", "Le Mans", "Aix-en-Provence", 
            "Clermont-Ferrand", "Brest", "Tours", "Limoges", "Amiens", "Annecy", "Perpignan", 
            "Boulogne-Billancourt", "Metz", "Besançon", "Orléans", "Argenteuil", "Rouen", 
            "Mulhouse", "Caen", "Nancy", "Saint-Denis", "Saint-Paul", "Montreuil", "Avignon"
        ];
        
        $builder
            ->add('lastName', TextType::class, [
                'attr' => [
                    'class' => 'p-3 border border-dotted border-gray-500 rounded-md w-full',
                    'placeholder' => 'Nom'
                ],
                'label' => 'Nom',
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-gray-700 mb-1'
                ],
                'required' => false,
                'empty_data' => '',
            ])
            ->add('firstName', TextType::class, [
                'attr' => [
                    'class' => 'p-3 border border-dotted border-gray-500 rounded-md w-full',
                    'placeholder' => 'Prénom'
                ],
                'label' => 'Prénom',
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-gray-700 mb-1'
                ],
                'required' => false,
                'empty_data' => '',
            ])
            ->add('city', ChoiceType::class, [
                'choices' => array_combine($cities, $cities), // Convert array to format ['Paris' => 'Paris', 'Lyon' => 'Lyon', etc.]
                'attr' => [
                    'class' => 'p-3 border border-dotted border-gray-500 rounded-md w-full'
                ],
                'label' => 'Ville',
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-gray-700 mb-1'
                ],
                'placeholder' => 'Sélectionnez une ville',
                'required' => false,
                'empty_data' => '',
            ])
            ->add('address', TextType::class, [
                'attr' => [
                    'class' => 'p-3 border border-dotted border-gray-500 rounded-md w-full',
                    'placeholder' => 'Adresse du cabinet'
                ],
                'label' => 'Adresse du cabinet',
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-gray-700 mb-1'
                ],
                'required' => false,
                'empty_data' => '',
            ])
            ->add('specialty', EntityType::class, [
                'class' => Specialty::class,
                'choice_label' => 'name',
                'attr' => [
                    'class' => 'p-3 border border-dotted border-gray-500 rounded-md w-full'
                ],
                'label' => 'Spécialité',
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-gray-700 mb-1'
                ],
                'placeholder' => 'Sélectionnez une spécialité',
                'required' => false,
            ]);
        
        // Add data transformers to ensure empty strings are properly handled
        $emptyStringToNullTransformer = new CallbackTransformer(
            function ($value) {
                // Transform value from the database to the form
                return $value;
            },
            function ($value) {
                // Transform value from the form to the database
                return $value === '' ? '' : $value;
            }
        );
        
        $builder->get('lastName')->addModelTransformer($emptyStringToNullTransformer);
        $builder->get('firstName')->addModelTransformer($emptyStringToNullTransformer);
        $builder->get('city')->addModelTransformer($emptyStringToNullTransformer);
        $builder->get('address')->addModelTransformer($emptyStringToNullTransformer);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Doctor::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_csrf_token',
            'csrf_token_id' => 'update-doctor',
            'validation_groups' => ['Default'],
            'allow_extra_fields' => true,
        ]);
    }
}