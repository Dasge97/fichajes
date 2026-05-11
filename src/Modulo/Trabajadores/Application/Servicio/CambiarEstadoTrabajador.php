<?php

namespace App\Modulo\Trabajadores\Application\Servicio;

use App\Modulo\Trabajadores\Domain\Entity\Trabajador;
use App\Modulo\Trabajadores\Infrastructure\Repository\TrabajadorRepository;

class CambiarEstadoTrabajador
{
    public function __construct(private readonly TrabajadorRepository $repository) {}

    public function ejecutar(string $tenantId, string $trabajadorId, bool $activo): Trabajador
    {
        $trabajador = $this->repository->buscarPorTenantYTrabajadorId($tenantId, $trabajadorId);
        if ($trabajador === null) {
            throw new \DomainException('TRABAJADOR_NO_ENCONTRADO');
        }

        $trabajador->cambiarEstado($activo);
        $this->repository->guardar($trabajador);

        return $trabajador;
    }
}
