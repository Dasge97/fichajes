<?php

namespace App\Modulo\Correcciones\Infrastructure\Repository;

use App\Modulo\Correcciones\Domain\Entity\CorreccionFichaje;
use Doctrine\ORM\EntityManagerInterface;

class CorreccionFichajeRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function guardar(CorreccionFichaje $correccion): void
    {
        $this->entityManager->persist($correccion);
        $this->entityManager->flush();
    }

    public function buscarPorIdYTenant(string $id, string $tenantId): ?CorreccionFichaje
    {
        return $this->entityManager->getRepository(CorreccionFichaje::class)->findOneBy([
            'id' => $id,
            'tenantId' => $tenantId,
        ]);
    }
}
