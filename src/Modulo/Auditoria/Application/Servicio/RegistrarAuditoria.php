<?php

namespace App\Modulo\Auditoria\Application\Servicio;

use App\Modulo\Auditoria\Domain\Entity\RegistroAuditoria;
use Doctrine\ORM\EntityManagerInterface;

class RegistrarAuditoria
{
    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function registrar(string $tenantId, string $accion, ?array $antes = null, ?array $despues = null): void
    {
        $registro = new RegistroAuditoria(bin2hex(random_bytes(16)), $tenantId, $accion, $antes, $despues);
        $this->entityManager->persist($registro);
        $this->entityManager->flush();
    }
}
