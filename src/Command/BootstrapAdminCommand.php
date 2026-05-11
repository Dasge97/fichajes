<?php

namespace App\Command;

use App\Modulo\Acceso\Domain\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:bootstrap-admin', description: 'Crea el usuario admin inicial si no existe')]
class BootstrapAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly string $adminEmail = 'admin@fichajes.local',
        private readonly string $adminPassword = 'Admin123!'
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repo = $this->entityManager->getRepository(Usuario::class);
        if ($repo->findOneBy(['email' => $this->adminEmail]) !== null) {
            $output->writeln('Admin ya existe.');
            return Command::SUCCESS;
        }

        $usuario = new Usuario(bin2hex(random_bytes(16)), 'T1', $this->adminEmail, ['ROLE_ADMIN', 'ROLE_SUPERVISOR']);
        $usuario->setPassword($this->passwordHasher->hashPassword($usuario, $this->adminPassword));
        $this->entityManager->persist($usuario);
        $this->entityManager->flush();

        $output->writeln('Admin inicial creado correctamente.');
        return Command::SUCCESS;
    }
}
