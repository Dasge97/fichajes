<?php

namespace App\Modulo\Horarios\Application\Servicio;

use App\Modulo\Horarios\Domain\Entity\AsignacionHorarioEmpleado;
use App\Modulo\Horarios\Infrastructure\Repository\AsignacionHorarioRepository;
use DateTimeImmutable;

class AsignarHorarioEmpleado
{
    public function __construct(private readonly AsignacionHorarioRepository $repository) {}

    public function ejecutar(string $tenantId, string $empleadoId, string $horarioId, DateTimeImmutable $desde, ?DateTimeImmutable $hasta): void
    {
        if ($this->repository->existeSolape($tenantId, $empleadoId, $desde, $hasta)) {
            throw new \DomainException('SOLAPE_VIGENCIA');
        }

        $asignacion = new AsignacionHorarioEmpleado(bin2hex(random_bytes(16)), $tenantId, $empleadoId, $horarioId, $desde, $hasta);
        $this->repository->guardar($asignacion);
    }
}
