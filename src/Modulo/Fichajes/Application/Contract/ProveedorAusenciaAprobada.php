<?php

namespace App\Modulo\Fichajes\Application\Contract;

use DateTimeImmutable;

interface ProveedorAusenciaAprobada
{
    public function tieneAusenciaAprobada(string $tenantId, string $empleadoId, DateTimeImmutable $fecha): bool;
}
