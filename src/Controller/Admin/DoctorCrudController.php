<?php

namespace App\Controller\Admin;

use App\Entity\Doctor;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;

class DoctorCrudController extends AbstractCrudController
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private TokenGeneratorInterface $tokenGenerator;
    private MailerInterface $mailer;
    private UrlGeneratorInterface $urlGenerator;
    private AdminUrlGenerator $adminUrlGenerator;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        TokenGeneratorInterface $tokenGenerator,
        MailerInterface $mailer,
        UrlGeneratorInterface $urlGenerator,
        AdminUrlGenerator $adminUrlGenerator
    ) {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->tokenGenerator = $tokenGenerator;
        $this->mailer = $mailer;
        $this->urlGenerator = $urlGenerator;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public static function getEntityFqcn(): string
    {
        return Doctor::class;
    }

    // public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    // {
    //     $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

    //     // Filter to only show doctors
    //     $queryBuilder
    //         ->andWhere("entity.roles LIKE :role")
    //         ->setParameter('role', '%ROLE_DOCTOR%');

    //     return $queryBuilder;
    // }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Médecin')
            ->setEntityLabelInPlural('Médecins')
            ->setSearchFields(['id', 'email'])
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::EDIT) // Disable editing doctors
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->onlyOnDetail();

        yield TextField::new('email')
            ->setHelp('The doctor\'s email address')
            ->setFormTypeOption('mapped', false)
            ->hideOnDetail()
            ->hideOnIndex();

        yield AssociationField::new('user')
            ->hideWhenCreating()
            ->hideWhenUpdating();

        yield AssociationField::new('specialty')
            ->setLabel('Spécialité')
            ->setHelp('Sélectionnez la spécialité du médecin')
            //  // Only show this field when creating a new doctor
            ->autocomplete();

        if ($pageName === Crud::PAGE_NEW) {
            // For new doctors, we don't show/set password - it will be auto-generated
            yield TextField::new('generatedPassword')
                ->setFormTypeOption('mapped', false)
                ->setFormTypeOption('disabled', true)
                ->setHelp('Password will be auto-generated and a setup email will be sent');
        }

        yield BooleanField::new('user.isVerified')
            ->setLabel('Email Verified')
            ->renderAsSwitch(false)
            ->formatValue(function ($value, Doctor $doctor) {
                // dd($doctor);
                return $doctor->getUser() && $doctor->getUser()->isVerified() ? 'Yes' : 'No'; // English values
            })
            ->onlyOnDetail()
            ->setHelp('Whether the doctor has verified their email');

        // yield TextField::new('emailVerificationStatus')
        //     ->setLabel('Email Verified')
        //     ->formatValue(function ($value, Doctor $doctor) {
        //         return $doctor->getUser() && $doctor->getUser()->isVerified() ? 'Yes' : 'No';
        //     })
        //     ->onlyOnDetail()
        //     ->setHelp('Whether the doctor has verified their email');
    }

    /**
     * Create a new doctor and send setup email
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        // /** @var User $user */
        // $user = $entityInstance;

        /**
         * @var Doctor $doctor
         */
        $doctor = $entityInstance;
        $email = $this->getContext()->getRequest()->request->all('Doctor')['email'];
        // We create a new user instance
        $user = new User();
        $user->setEmail($email);

        // Generate a random password
        $tempPassword = bin2hex(random_bytes(8));

        // Set roles and hash password
        $user->setRoles(['ROLE_USER', 'ROLE_DOCTOR']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $tempPassword));
        $user->setIsVerified(false);

        // Generate a token for account setup
        $token = $this->tokenGenerator->generateToken();
        $user->setResetToken($token);

        $doctor->setUser($user);


        // Persist the user
        $this->entityManager->persist($doctor);
        parent::persistEntity($entityManager, $user);

        // Send account setup email
        $this->sendWelcomeEmail($user);

        $this->addFlash('success', 'Doctor account created. Setup email sent to ' . $user->getEmail());
    }

    /**
     * Send account setup email to a doctor
     */
    public function sendAccountSetupEmail(AdminContext $context): Response
    {
        /** @var User $user */
        $user = $context->getEntity()->getInstance();

        // Generate a new token if not exists
        if (!$user->getResetToken()) {
            $token = $this->tokenGenerator->generateToken();
            $user->setResetToken($token);
            $this->entityManager->flush();
        }

        // Send the setup email
        $this->sendWelcomeEmail($user);

        $this->addFlash('success', 'Setup email sent to ' . $user->getEmail());

        return $this->redirect($context->getReferrer());
    }

    /**
     * Send welcome email with combined verification and password setup link
     */
    private function sendWelcomeEmail(User $user): void
    {
        // Create the activation URL combining email verification and password reset
        $activationUrl = $this->urlGenerator->generate(
            'app_activate_doctor_account',
            ['token' => $user->getResetToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Send email
        $email = (new TemplatedEmail())
            ->from(new Address('no-reply@sante-rezo.com', 'SantéRézo'))
            ->to($user->getEmail())
            ->subject('Bienvenue sur SantéRézo - Activez votre compte')
            ->htmlTemplate('registration/doctor_welcome_email.html.twig')
            ->context([
                'activationUrl' => $activationUrl
            ]);

        $this->mailer->send($email);
    }
}
