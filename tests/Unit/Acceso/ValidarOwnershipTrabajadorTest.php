<?php

namespace App\Tests\Unit\Acceso;

use App\Modulo\Acceso\Application\Servicio\ResolverPermisoRol;
use App\Modulo\Acceso\Application\Servicio\ValidarOwnershipTrabajador;
use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Trabajadores\Domain\Entity\Trabajador;
use App\Modulo\Trabajadores\Infrastructure\Repository\TrabajadorRepository;
use PHPUnit\Framework\TestCase;

class ValidarOwnershipTrabajadorTest extends TestCase
{
    public function testDeniegaOwnershipCruzadoConRolesTenantExplicitos(): void
    {
        $repo = $this->createMock(TrabajadorRepository::class);
        $trabajador = new Trabajador('W-ID', 'T1', 'W2', 'W Dos', 'w2@test.local', new \DateTimeImmutable('2026-05-08T09:00:00+00:00'));
        $trabajador->vincularUsuario('U2');
        $repo->method('buscarPorTenantYTrabajadorId')->willReturn($trabajador);

        $servicio = new ValidarOwnershipTrabajador(new ResolverPermisoRol(), $repo);

        $usuario = $this->createMock(Usuario::class);
        $usuario->method('getId')->willReturn('U1');
        $usuario->method('getCodigosRolTenant')->willReturn(['trabajador']);
        $usuario->method('tieneRolesTenantExplicitos')->willReturn(true);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ACCESO_DENEGADO_OWNERSHIP');
        $servicio->validar($usuario, 'T1', 'W2', 'fichajes.registrar.propio');
    }
}
