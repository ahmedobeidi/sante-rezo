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
            'Généraliste',
            'Cardiologue',
            'Dermatologue',
            'Gastro-entérologue',
            'Gynécologue',
            'Neurologue',
            'Ophtalmologue',
            'Orthopédiste',
            'ORL',
            'Pédiatre',
            'Psychiatre',
            'Radiologue',
            'Urologue',
            'Endocrinologue',
            'Pneumologue',
            'Rhumatologue',
            'Anesthésiste',
            'Oncologue',
            'Gériatre',
            'Interniste',
            'Allergologue',
            'Néphrologue',
            'Chirurgien général',
            'Chirurgien esthétique',
            'Médecin du travail',
            'Médecin du sport',
            'Urgentiste',
            'Hématologue',
            'Immunologue',
            'Infectiologue',
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