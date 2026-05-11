<?php

namespace App\Modulo\Trabajadores\Application\Servicio;

use App\Modulo\Trabajadores\Domain\Entity\Trabajador;
use App\Modulo\Trabajadores\Infrastructure\Repository\TrabajadorRepository;
use DateTimeImmutable;

class CrearTrabajador
{
    public function __construct(private readonly TrabajadorRepository $repository) {}

    public function ejecutar(string $tenantId, string $trabajadorId, string $nombre, ?string $email, ?string $claveAcceso = null): Trabajador
    {
        if ($this->repository->buscarPorTenantYTrabajadorId($tenantId, $trabajadorId) !== null) {
            throw new \DomainException('TRABAJADOR_ID_DUPLICADO');
        }

        $trabajador = new Trabajador(
            bin2hex(random_bytes(16)),
            $tenantId,
            trim($trabajadorId),
            trim($nombre),
            $this->normalizarEmail($email),
            new DateTimeImmutable()
        );
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
