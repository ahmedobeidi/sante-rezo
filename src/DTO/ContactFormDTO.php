<?php
namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ContactFormDTO
{
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'Le prénom ne peut pas dépasser 255 caractères.')]
    public ?string $firstName = null;

    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'Le nom ne peut pas dépasser 255 caractères.')]
    public ?string $lastName = null;

    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'Veuillez entrer une adresse email valide.')]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'Le message est obligatoire.')]
    #[Assert\Length(min: 10, minMessage: 'Le message doit contenir au moins 10 caractères.')]
    public ?string $message = null;
}