<?php

namespace App\DataFixtures;

use App\Entity\Specialty;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SpecialtyFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $specialties = [
            'Médecine générale',
            'Cardiologie',
            'Dermatologie',
            'Gastro-entérologie',
            'Gynécologie',
            'Neurologie',
            'Ophtalmologie',
            'Orthopédie',
            'Oto-rhino-laryngologie (ORL)',
            'Pédiatrie',
            'Psychiatrie',
            'Radiologie',
            'Urologie',
            'Endocrinologie',
            'Pneumologie',
            'Rhumatologie',
            'Anesthésiologie',
            'Oncologie',
            'Gériatrie',
            'Médecine interne',
            'Allergologie',
            'Néphrologie',
            'Chirurgie générale',
            'Chirurgie esthétique',
            'Médecine du travail',
            'Médecine du sport',
            'Médecine d\'urgence',
            'Hématologie',
            'Immunologie',
            'Infectiologie',
        ];

        foreach ($specialties as $specialtyName) {
            $specialty = new Specialty();
            $specialty->setName($specialtyName);
            $manager->persist($specialty);
            
            // Create a reference for later use if needed
            $this->addReference('specialty_' . strtolower(str_replace([' ', '-', '\'', '(', ')'], '_', $specialtyName)), $specialty);
        }

        $manager->flush();
    }
}