<?php

namespace App\Modulo\Acceso\Application\Servicio;

class ResolverPermisoRol
{
    private const MATRIZ = [
        'trabajadores.crear' => ['owner_tenant', 'gestor_rrhh'],
        'trabajadores.editar' => ['owner_tenant', 'gestor_rrhh'],
        'trabajadores.ver' => ['owner_tenant', 'gestor_rrhh', 'responsable_equipo', 'trabajador'],
        'trabajadores.roles.gestionar' => ['owner_tenant', 'gestor_rrhh'],
        'fichajes.registrar.propio' => ['trabajador', 'responsable_equipo', 'owner_tenant'],
        'fichajes.registrar.equipo' => ['responsable_equipo', 'owner_tenant'],
        'correcciones.solicitar.propio' => ['trabajador', 'responsable_equipo', 'owner_tenant'],
        'correcciones.aprobar' => ['responsable_equipo', 'gestor_rrhh', 'owner_tenant'],
    ];

    /** @param string[] $codigosRol */
    public function puede(string $permiso, array $codigosRol): bool
    {
        if (str_starts_with($permiso, 'nomina.')) {
            return false;
        }
        $permitidos = self::MATRIZ[$permiso] ?? [];

        return count(array_intersect($codigosRol, $permitidos)) > 0;
    }
}
