<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Entity\Patient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PatientUpdateProfileTest extends WebTestCase
{
    private $entityManager;
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        /** @var EntityManagerInterface $em */
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        // Clear and prepare database before each test
        $this->entityManager->createQuery('DELETE FROM App\Entity\Appointment a')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Patient p')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\User u')->execute();
    }

    public function testPatientCanUpdateProfile(): void
    {
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        // Create test user
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword($passwordHasher->hashPassword($user, 'password'));
        $user->setRoles(['ROLE_USER']);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Create associated patient
        $patient = new Patient();
        $patient->setUser($user);
        $this->entityManager->persist($patient);
        $this->entityManager->flush();

        // Log in the user
        $this->client->loginUser($user);

        // Submit the update form
        $crawler = $this->client->request('GET', '/patient');

        $form = $crawler->selectButton('Enregistrer')->form([
            'patient_update[firstName]' => 'John',
            'patient_update[lastName]' => 'Doe',
            'patient_update[city]' => 'Paris',
            'patient_update[address]' => '123 Rue de Test',
        ]);

        $this->client->submit($form);

        // Check redirection
        $this->assertResponseRedirects('/patient');

        // Follow redirect
        $this->client->followRedirect();

        // Verify data in the database
        $updatedPatient = $this->entityManager
            ->getRepository(Patient::class)
            ->findOneBy(['user' => $user]);

        $this->assertEquals('John', $updatedPatient->getFirstName());
        $this->assertEquals('Doe', $updatedPatient->getLastName());
        $this->assertEquals('Paris', $updatedPatient->getCity());
        $this->assertEquals('123 Rue de Test', $updatedPatient->getAddress());
    }

    protected function tearDown(): void
    {
        // Clean up database after each test
        $this->entityManager->createQuery('DELETE FROM App\Entity\Appointment a')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Patient p')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\User u')->execute();
        
        // Close the entity manager to free up resources
        $this->entityManager->close();
        $this->entityManager = null;
        
        parent::tearDown();
    }
}