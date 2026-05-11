<?php

namespace App\Modulo\Trabajadores\Application\Servicio;

use App\Modulo\Trabajadores\Domain\Entity\Trabajador;
use App\Modulo\Trabajadores\Infrastructure\Repository\TrabajadorRepository;

class EditarTrabajador
{
    public function __construct(private readonly TrabajadorRepository $repository) {}

    public function ejecutar(string $tenantId, string $trabajadorId, string $nombre, ?string $email, ?string $claveAcceso = null): Trabajador
    {
        $trabajador = $this->repository->buscarPorTenantYTrabajadorId($tenantId, $trabajadorId);
        if ($trabajador === null) {
            throw new \DomainException('TRABAJADOR_NO_ENCONTRADO');
        }

        $trabajador->editar(trim($nombre), $this->normalizarEmail($email));
        $trabajador->actualizarClaveAcceso($claveAcceso);
        $this->repository->guardar($trabajador);

        return $trabajador;
    }

    private function normalizarEmail(?string $email): ?string
    {
        $limpio = trim((string) $email);

        return $limpio === '' ? null : $limpio;
    }
}
