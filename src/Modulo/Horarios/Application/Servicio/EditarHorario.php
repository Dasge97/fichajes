<?php

namespace App\Modulo\Horarios\Application\Servicio;

use App\Modulo\Horarios\Domain\Entity\HorarioTrabajo;
use App\Modulo\Horarios\Infrastructure\Repository\HorarioTrabajoRepository;

class EditarHorario
{
    public function __construct(private readonly HorarioTrabajoRepository $repository) {}

    public function ejecutar(string $tenantId, string $horarioId, string $nombre, array $tramos): HorarioTrabajo
    {
        $horario = $this->repository->buscarPorIdYTenant($horarioId, $tenantId);
        if ($horario === null) {
            throw new \DomainException('HORARIO_NO_ENCONTRADO');
        }

        $horario->editar($nombre, $tramos);
        $this->repository->guardar($horario);

        return $horario;
    }
}
