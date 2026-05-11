<?php

namespace App\Modulo\Fichajes\Infrastructure\Repository;

use App\Modulo\Fichajes\Domain\Entity\IntentoAccesoHerramienta;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class IntentoAccesoHerramientaRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function buscarPorTrabajadorEIp(string $trabajadorId, string $ipHash): ?IntentoAccesoHerramienta
    {
        return $this->entityManager->getRepository(IntentoAccesoHerramienta::class)->findOneBy([
            'trabajadorId' => $trabajadorId,
            'ipHash' => $ipHash,
        ]);
    }

    public function obtenerOCrear(string $trabajadorId, string $ipHash, DateTimeImmutable $ahora): IntentoAccesoHerramienta
    {
        $intento = $this->buscarPorTrabajadorEIp($trabajadorId, $ipHash);
        if ($intento !== null) {
            return $intento;
        }

        $intento = new IntentoAccesoHerramienta(bin2hex(random_bytes(16)), $trabajadorId, $ipHash, $ahora);
        $this->entityManager->persist($intento);

        return $intento;
    }

    public function guardar(): void
    {
        $this->entityManager->flush();
    }

    public function eliminar(IntentoAccesoHerramienta $intento): void
    {
        $this->entityManager->remove($intento);
        $this->entityManager->flush();
    }
}
