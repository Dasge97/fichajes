<?php

namespace App\Modulo\Horarios\Infrastructure\Repository;

use App\Modulo\Horarios\Domain\Entity\HorarioTrabajo;
use Doctrine\ORM\EntityManagerInterface;

class HorarioTrabajoRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function guardar(HorarioTrabajo $horario): void
    {
        $this->entityManager->persist($horario);
        $this->entityManager->flush();
    }

    public function buscarPorIdYTenant(string $id, string $tenantId): ?HorarioTrabajo
    {
        return $this->entityManager->getRepository(HorarioTrabajo::class)->findOneBy(['id' => $id, 'tenantId' => $tenantId]);
    }
}
