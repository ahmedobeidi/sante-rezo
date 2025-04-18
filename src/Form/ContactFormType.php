<?php

namespace App\Form;

use App\DTO\ContactFormDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'attr' => [
                    'placeholder' => 'Prénom',
                    'class' => 'w-full border border-dotted border-gray-500 rounded p-2 focus:border-off-blue'
                ],
                'label' => 'Prénom',
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-dark-gray mb-1'
                ],
                'required' => true
            ])
            ->add('lastName', TextType::class, [
                'attr' => [
                    'placeholder' => 'Nom',
                    'class' => 'w-full border border-dotted border-gray-500 rounded p-2 focus:border-off-blue'
                ],
                'label' => 'Nom',
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-dark-gray mb-1'
                ],
                'required' => true
            ])
            ->add('email', EmailType::class, [
                'attr' => [
                    'placeholder' => 'Email',
                    'class' => 'w-full border border-dotted border-gray-500 rounded p-2 focus:border-off-blue'
                ],
                'label' => 'Adresse email',
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-dark-gray mb-1'
                ],
                'required' => true
            ])
            ->add('message', TextareaType::class, [
                'attr' => [
                    'placeholder' => 'Écrivez votre message ici...',
                    'class' => 'border border-dotted border-gray-500 rounded p-2 w-full focus:border-off-blue',
                    'rows' => 4
                ],
                'label' => 'Message',
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-dark-gray mb-1'
                ],
                'required' => true
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactFormDTO::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'contact_form'
        ]);
    }
}