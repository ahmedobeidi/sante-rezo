<?php

namespace App\DataFixtures;

use App\Entity\Appointment;
use App\Entity\Doctor;
use App\Entity\Patient;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AppointmentFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Get all existing doctors and patients
        $doctors = $manager->getRepository(Doctor::class)->findAll();
        $patients = $manager->getRepository(Patient::class)->findAll();

        if (empty($doctors)) {
            echo "No doctors found. Please run DoctorFixtures first.\n";
            return;
        }

        if (empty($patients)) {
            echo "No patients found. Please run PatientFixtures first.\n";
            return;
        }

        // Define appointment statuses
        $statuses = ['disponible', 'réservé'];
        $statusWeights = [
            'disponible' => 60, // 60% will be available
            'réservé' => 40     // 40% will be booked
        ];

        // Create weighted status array
        $weightedStatuses = [];
        foreach ($statusWeights as $status => $weight) {
            for ($i = 0; $i < $weight; $i++) {
                $weightedStatuses[] = $status;
            }
        }

        $createdCount = 0;
        $maxAttempts = 5000; // Prevent infinite loops
        $attempts = 0;

        while ($createdCount < 1000 && $attempts < $maxAttempts) {
            $attempts++;

            // Random doctor
            $doctor = $doctors[array_rand($doctors)];

            // Generate random appointment date (within next 3 months)
            $startDate = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
            $endDate = (clone $startDate)->modify('+3 months');
            
            $randomTimestamp = rand($startDate->getTimestamp(), $endDate->getTimestamp());
            $appointmentDate = new \DateTime('@' . $randomTimestamp);
            $appointmentDate->setTimezone(new \DateTimeZone('Europe/Paris'));

            // Set realistic appointment hours (8:00 to 18:00, Monday to Saturday)
            $dayOfWeek = (int)$appointmentDate->format('N'); // 1 = Monday, 7 = Sunday
            
            // Skip Sundays
            if ($dayOfWeek === 7) {
                continue;
            }

            // Set random hour between 8:00 and 18:00
            $hour = rand(8, 17);
            $minute = rand(0, 3) * 15; // 0, 15, 30, or 45 minutes
            $appointmentDate->setTime($hour, $minute, 0);

            // Check if appointment already exists at this exact time for this doctor
            $existingAppointment = $manager->getRepository(Appointment::class)->findOneBy([
                'doctor' => $doctor,
                'date' => $appointmentDate
            ]);

            if ($existingAppointment) {
                continue; // Skip if appointment already exists
            }

            // Create new appointment
            $appointment = new Appointment();
            $appointment->setDoctor($doctor);
            $appointment->setDate($appointmentDate);

            // Randomly assign status
            $status = $weightedStatuses[array_rand($weightedStatuses)];
            $appointment->setStatus($status);

            // If status is 'réservé', assign a random patient
            if ($status === 'réservé') {
                $patient = $patients[array_rand($patients)];
                
                // Check if patient already has 2+ appointments with this doctor
                $existingPatientAppointments = $manager->getRepository(Appointment::class)
                    ->createQueryBuilder('a')
                    ->where('a.patient = :patient')
                    ->andWhere('a.doctor = :doctor')
                    ->andWhere('a.date >= :now')
                    ->setParameter('patient', $patient)
                    ->setParameter('doctor', $doctor)
                    ->setParameter('now', new \DateTime('now'))
                    ->getQuery()
                    ->getResult();

                // If patient already has 2+ appointments with this doctor, make it available instead
                if (count($existingPatientAppointments) >= 2) {
                    $appointment->setStatus('disponible');
                    $appointment->setPatient(null);
                } else {
                    $appointment->setPatient($patient);
                }
            } else {
                $appointment->setPatient(null);
            }

            $manager->persist($appointment);
            $createdCount++;

            // Flush every 100 appointments to avoid memory issues
            if ($createdCount % 100 === 0) {
                $manager->flush();
                echo "Created {$createdCount} appointments...\n";
            }
        }

        // Final flush
        $manager->flush();

        echo "Successfully created {$createdCount} appointments.\n";
        if ($attempts >= $maxAttempts) {
            echo "Reached maximum attempts. Some time slots may have been skipped due to conflicts.\n";
        }
    }

    public function getDependencies(): array
    {
        return [
            DoctorFixtures::class,
            PatientFixtures::class,
        ];
    }
}