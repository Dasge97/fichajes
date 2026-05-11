<?php

namespace App\Modulo\Correcciones\Application\Servicio;

use App\Modulo\Acceso\Application\Servicio\ValidarOwnershipTrabajador;
use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Correcciones\Domain\Entity\CorreccionFichaje;
use App\Modulo\Correcciones\Infrastructure\Repository\CorreccionFichajeRepository;
use DateTimeImmutable;

class SolicitarCorreccionFichaje
{
    public function __construct(
        private readonly CorreccionFichajeRepository $repository,
        private readonly ValidarOwnershipTrabajador $validarOwnershipTrabajador
    ) {}

    public function ejecutar(string $tenantId, string $eventoFichajeId, string $motivo, ?string $evidencia, ?DateTimeImmutable $ocurridoEnCorregido, ?string $tipoCorregido, ?Usuario $usuario = null, ?string $trabajadorId = null): CorreccionFichaje
    {
        if ($usuario instanceof Usuario && is_string($trabajadorId) && $trabajadorId !== '') {
            $this->validarOwnershipTrabajador->validar($usuario, $tenantId, $trabajadorId, 'correcciones.solicitar.propio');
        }

        $correccion = new CorreccionFichaje(
            bin2hex(random_bytes(16)),
            $tenantId,
            $eventoFichajeId,
            $motivo,
            $evidencia,
            $ocurridoEnCorregido,
            $tipoCorregido
        );
        $this->repository->guardar($correccion);
        return $correccion;
    }
}
