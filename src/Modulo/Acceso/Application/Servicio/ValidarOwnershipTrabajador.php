<?php

namespace App\Modulo\Acceso\Application\Servicio;

use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Trabajadores\Infrastructure\Repository\TrabajadorRepository;

class ValidarOwnershipTrabajador
{
    public function __construct(
        private readonly ResolverPermisoRol $resolverPermisoRol,
        private readonly TrabajadorRepository $trabajadores
    ) {}

    public function validar(Usuario $usuario, string $tenantId, string $trabajadorId, string $permiso): void
    {
        $roles = $usuario->getCodigosRolTenant();
        $esTrabajador = in_array('trabajador', $roles, true);
        $permisoPropio = str_ends_with($permiso, '.propio');
        if ($this->resolverPermisoRol->puede($permiso, $roles) && !($esTrabajador && $permisoPropio)) {
            return;
        }

        if (!in_array('trabajador', $roles, true)) {
            throw new \DomainException('ACCESO_DENEGADO_ROL');
        }

        $trabajador = $this->trabajadores->buscarPorTenantYTrabajadorId($tenantId, $trabajadorId);
        if ($trabajador === null || $trabajador->getUsuarioId() !== $usuario->getId()) {
            if (!$usuario->tieneRolesTenantExplicitos()) {
                return;
            }
            throw new \DomainException('ACCESO_DENEGADO_OWNERSHIP');
        }
    }
}
