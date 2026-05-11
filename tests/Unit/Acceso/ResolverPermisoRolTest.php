<?php

namespace App\Tests\Unit\Acceso;

use App\Modulo\Acceso\Application\Servicio\ResolverPermisoRol;
use PHPUnit\Framework\TestCase;

class ResolverPermisoRolTest extends TestCase
{
    public function testPermiteAccionConRolAutorizado(): void
    {
        $resolver = new ResolverPermisoRol();

        self::assertTrue($resolver->puede('trabajadores.editar', ['gestor_rrhh']));
    }

    public function testDeniegaAccionSinRolAutorizado(): void
    {
        $resolver = new ResolverPermisoRol();

        self::assertFalse($resolver->puede('trabajadores.editar', ['trabajador']));
    }

    public function testNominaFueraDeAlcance(): void
    {
        $resolver = new ResolverPermisoRol();

        self::assertFalse($resolver->puede('nomina.ver', ['owner_tenant']));
    }
}
