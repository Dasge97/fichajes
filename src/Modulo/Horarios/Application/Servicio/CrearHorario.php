<?php

namespace App\Modulo\Horarios\Application\Servicio;

use App\Modulo\Horarios\Domain\Entity\HorarioTrabajo;
use App\Modulo\Horarios\Infrastructure\Repository\HorarioTrabajoRepository;

class CrearHorario
{
    public function __construct(private readonly HorarioTrabajoRepository $repository) {}

    public function ejecutar(string $tenantId, string $nombre, array $tramos): HorarioTrabajo
    {
        $horario = new HorarioTrabajo(bin2hex(random_bytes(16)), $tenantId, $nombre, $tramos);
        $this->repository->guardar($horario);
        return $horario;
    }
}
