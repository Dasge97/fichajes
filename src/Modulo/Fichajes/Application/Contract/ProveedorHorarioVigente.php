<?php

namespace App\Modulo\Fichajes\Application\Contract;

use DateTimeImmutable;

interface ProveedorHorarioVigente
{
    public function estaDentroDeHorario(string $tenantId, string $empleadoId, DateTimeImmutable $fecha): bool;
}
