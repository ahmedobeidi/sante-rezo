<?php

namespace App\Form;

use App\Entity\Patient;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Validator\Constraints\NotBlank;

class PatientUpdateType extends AbstractType
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
        
        $citiesChoices = array_combine($cities, $cities);
        
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
                'required' => false, // Changed to false to prevent HTML5 validation
                'empty_data' => '', // Provides an empty string instead of null
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
                'required' => false, // Changed to false to prevent HTML5 validation
                'empty_data' => '', // Provides an empty string instead of null
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
                    'placeholder' => 'Adresse'
                ],
                'label' => 'Adresse',
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-gray-700 mb-1'
                ],
                'required' => false, // Changed to false to prevent HTML5 validation
                'empty_data' => '', // Provides an empty string instead of null
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
            'data_class' => Patient::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_csrf_token',
            'csrf_token_id' => 'update-patient',
            'validation_groups' => ['Default'], // Use default validation group
            'allow_extra_fields' => true,
        ]);
    }
}