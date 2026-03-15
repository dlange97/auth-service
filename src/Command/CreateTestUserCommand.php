<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\PermissionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:create-test-user',
    description: 'Create a test user for development',
)]
class CreateTestUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'User email', 'test@micro.com')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'User password', 'TestPass123!@#')
            ->addOption('firstName', null, InputOption::VALUE_OPTIONAL, 'User first name', 'Test')
            ->addOption('lastName', null, InputOption::VALUE_OPTIONAL, 'User last name', 'User')
            ->addOption('role', null, InputOption::VALUE_OPTIONAL, 'User role', PermissionService::ROLE_ADMIN)
            ->addOption('upsert', null, InputOption::VALUE_NONE, 'Update existing user if already exists')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getOption('email');
        $password = $input->getOption('password');
        $firstName = $input->getOption('firstName');
        $lastName = $input->getOption('lastName');
        $role = $input->getOption('role');
        $upsert = (bool) $input->getOption('upsert');

        if (
            !in_array($role, [
            PermissionService::ROLE_ADMIN,
            PermissionService::ROLE_MANAGER,
            PermissionService::ROLE_EDITOR,
            PermissionService::ROLE_USER,
            ], true)
        ) {
            $io->error(sprintf('Unsupported role "%s".', (string) $role));
            return Command::INVALID;
        }

        $existingUser = $this->userRepository->findOneBy(['email' => $email]);
        if ($existingUser) {
            if (!$upsert) {
                $io->error(sprintf('User with email "%s" already exists! Use --upsert to update.', $email));
                return Command::FAILURE;
            }

            $existingUser->setFirstName($firstName);
            $existingUser->setLastName($lastName);
            $existingUser->setRoles([$role]);
            $existingUser->setPassword($this->passwordHasher->hashPassword($existingUser, $password));
            $this->em->flush();

            $io->success(sprintf('User "%s" updated (role: %s).', $email, $role));
            return Command::SUCCESS;
        }

        $user = new User();
        $user->setId((string) Uuid::v4());
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles([$role]);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf(
            'Test user created successfully!%s  Email: %s%s  Password: %s%s  Name: %s %s%s  Role: %s',
            "\n",
            $email,
            "\n",
            $password,
            "\n",
            $firstName,
            $lastName,
            "\n",
            $role,
        ));

        return Command::SUCCESS;
    }
}
