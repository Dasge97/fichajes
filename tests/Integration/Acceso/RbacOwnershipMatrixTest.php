<?php

namespace App\Tests\Integration\Acceso;

use App\Modulo\Acceso\Application\Servicio\ResolverPermisoRol;
use App\Modulo\Acceso\Application\Servicio\ValidarOwnershipTrabajador;
use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Trabajadores\Domain\Entity\Trabajador;
use App\Modulo\Trabajadores\Infrastructure\Repository\TrabajadorRepository;
use PHPUnit\Framework\TestCase;

class RbacOwnershipMatrixTest extends TestCase
{
    public function testMatrizRbacYOwnershipPropioAjeno(): void
    {
        $resolver = new ResolverPermisoRol();
        self::assertTrue($resolver->puede('trabajadores.crear', ['gestor_rrhh']));
        self::assertFalse($resolver->puede('trabajadores.crear', ['trabajador']));
        self::assertFalse($resolver->puede('nomina.ver', ['owner_tenant']));

        $repo = $this->createMock(TrabajadorRepository::class);
        $repo->method('buscarPorTenantYTrabajadorId')->willReturnCallback(static function (string $tenantId, string $trabajadorId): ?Trabajador {
            $trabajador = new Trabajador('wid-'.$trabajadorId, $tenantId, $trabajadorId, 'Nombre '.$trabajadorId, null, new \DateTimeImmutable('2026-05-08T09:00:00+00:00'));
            $trabajador->vincularUsuario($trabajadorId === 'W1' ? 'U1' : 'U2');

            return $trabajador;
        });

        $servicio = new ValidarOwnershipTrabajador($resolver, $repo);
        $usuario = $this->createMock(Usuario::class);
        $usuario->method('getId')->willReturn('U1');
        $usuario->method('getCodigosRolTenant')->willReturn(['trabajador']);
        $usuario->method('tieneRolesTenantExplicitos')->willReturn(true);

        $servicio->validar($usuario, 'T1', 'W1', 'fichajes.registrar.propio');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ACCESO_DENEGADO_OWNERSHIP');
        $servicio->validar($usuario, 'T1', 'W2', 'fichajes.registrar.propio');
    }
}
