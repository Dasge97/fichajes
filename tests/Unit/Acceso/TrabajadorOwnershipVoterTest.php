<?php

namespace App\Tests\Unit\Acceso;

use App\Modulo\Acceso\Application\Servicio\ResolverPermisoRol;
use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Trabajadores\Domain\Entity\Trabajador;
use App\Security\Voter\TrabajadorOwnershipVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class TrabajadorOwnershipVoterTest extends TestCase
{
    public function testTrabajadorPuedeVerSuPropioRecurso(): void
    {
        $resolver = new ResolverPermisoRol();
        $voter = new TrabajadorOwnershipVoter($resolver);
        $trabajador = new Trabajador('W-ID', 'T1', 'W1', 'Nombre', 'w1@example.com', new \DateTimeImmutable('2026-05-08T09:00:00+00:00'));
        $trabajador->vincularUsuario('U1');

        $usuario = $this->createMock(Usuario::class);
        $usuario->method('getTenantId')->willReturn('T1');
        $usuario->method('getId')->willReturn('U1');
        $usuario->method('getCodigosRolTenant')->willReturn(['trabajador']);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($usuario);

        $resultado = $voter->vote($token, $trabajador, [TrabajadorOwnershipVoter::VER]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $resultado);
    }

    public function testTrabajadorNoPuedeVerRecursoAjeno(): void
    {
        $resolver = new ResolverPermisoRol();
        $voter = new TrabajadorOwnershipVoter($resolver);
        $trabajador = new Trabajador('W-ID', 'T1', 'W2', 'Nombre 2', 'w2@example.com', new \DateTimeImmutable('2026-05-08T09:00:00+00:00'));
        $trabajador->vincularUsuario('U2');

        $usuario = $this->createMock(Usuario::class);
        $usuario->method('getTenantId')->willReturn('T1');
        $usuario->method('getId')->willReturn('U1');
        $usuario->method('getCodigosRolTenant')->willReturn(['trabajador']);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($usuario);

        $resultado = $voter->vote($token, $trabajador, [TrabajadorOwnershipVoter::VER]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $resultado);
    }
}
