<?php

namespace App\DataFixtures;

use App\Entity\Patient;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PatientFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // French first names
        $firstNames = [
            'Antoine', 'Pierre', 'Nicolas', 'Jean', 'Philippe', 'Laurent', 'David', 'Michel', 'Christophe', 'Daniel',
            'Marie', 'Françoise', 'Monique', 'Catherine', 'Nathalie', 'Isabelle', 'Sylvie', 'Christine', 'Martine', 'Sophie',
            'Alexandre', 'Julien', 'Stéphane', 'Thierry', 'Patrick', 'Frédéric', 'Olivier', 'Sébastien', 'Bruno', 'Vincent',
            'Anne', 'Brigitte', 'Sandrine', 'Valérie', 'Corinne', 'Pascale', 'Véronique', 'Laurence', 'Cécile', 'Céline',
            'Louis', 'Henri', 'François', 'Bernard', 'Alain', 'Marcel', 'André', 'Robert', 'Paul', 'Claude',
            'Jeanne', 'Marguerite', 'Hélène', 'Paulette', 'Suzanne', 'Denise', 'Simone', 'Lucienne', 'Andrée', 'Germaine'
        ];

        // French last names
        $lastNames = [
            'Martin', 'Bernard', 'Thomas', 'Petit', 'Robert', 'Richard', 'Durand', 'Dubois', 'Moreau', 'Laurent',
            'Simon', 'Michel', 'Lefebvre', 'Leroy', 'Roux', 'David', 'Bertrand', 'Morel', 'Fournier', 'Girard',
            'Bonnet', 'Dupont', 'Lambert', 'Fontaine', 'Rousseau', 'Vincent', 'Muller', 'Lefevre', 'Faure', 'Andre',
            'Mercier', 'Blanc', 'Guerin', 'Boyer', 'Garnier', 'Chevalier', 'Francois', 'Legrand', 'Gauthier', 'Garcia',
            'Perrin', 'Robin', 'Clement', 'Morin', 'Nicolas', 'Henry', 'Roussel', 'Mathieu', 'Gautier', 'Masson',
            'Marchand', 'Duval', 'Denis', 'Dumont', 'Marie', 'Lemaire', 'Noel', 'Meyer', 'Dufour', 'Meunier'
        ];

        // French cities (same as in your forms)
        $cities = [
            "Paris", "Marseille", "Lyon", "Toulouse", "Nice", "Nantes", "Strasbourg", "Montpellier", 
            "Bordeaux", "Lille", "Rennes", "Reims", "Le Havre", "Saint-Étienne", "Toulon", 
            "Grenoble", "Dijon", "Angers", "Villeurbanne", "Le Mans", "Aix-en-Provence", 
            "Clermont-Ferrand", "Brest", "Tours", "Limoges", "Amiens", "Annecy", "Perpignan", 
            "Boulogne-Billancourt", "Metz", "Besançon", "Orléans", "Argenteuil", "Rouen", 
            "Mulhouse", "Caen", "Nancy", "Saint-Denis", "Saint-Paul", "Montreuil", "Avignon"
        ];

        // Street types and names for addresses
        $streetTypes = ['Rue', 'Avenue', 'Boulevard', 'Place', 'Impasse', 'Allée', 'Chemin'];
        $streetNames = [
            'de la République', 'Victor Hugo', 'Jean Jaurès', 'de la Paix', 'du Général de Gaulle', 'des Roses',
            'de la Liberté', 'Nationale', 'des Écoles', 'du Commerce', 'de l\'Église', 'des Tilleuls',
            'Mozart', 'Beethoven', 'des Champs', 'de la Gare', 'du Marché', 'de la Mairie', 'du Stade',
            'des Lilas', 'de la Fontaine', 'du Moulin', 'des Platanes', 'Saint-Antoine', 'Sainte-Catherine'
        ];

        for ($i = 1; $i <= 100; $i++) {
            // Create User entity first
            $user = new User();
            $user->setEmail('patient' . $i . '@santerezo.com');
            $user->setRoles(['ROLE_USER', 'ROLE_PATIENT']);
            
            // Hash a default password
            $hashedPassword = $this->passwordHasher->hashPassword($user, 'Ahmed1&');
            $user->setPassword($hashedPassword);
            $user->setIsVerified(true);
            $user->setIsDeleted(false);

            $manager->persist($user);

            // Create Patient entity
            $patient = new Patient();
            $patient->setUser($user);
            
            // Random French name
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            $patient->setFirstName($firstName);
            $patient->setLastName($lastName);

            // Random city
            $city = $cities[array_rand($cities)];
            $patient->setCity($city);

            // Generate random address
            $streetNumber = rand(1, 999);
            $streetType = $streetTypes[array_rand($streetTypes)];
            $streetName = $streetNames[array_rand($streetNames)];
            $address = $streetNumber . ' ' . $streetType . ' ' . $streetName;
            $patient->setAddress($address);

            // Set default profile image to default2.png for all patients
            $patient->setProfileImage(null);

            $manager->persist($patient);

            // Add reference for potential use in other fixtures
            $this->addReference('patient_' . $i, $patient);
        }

        $manager->flush();
    }
}