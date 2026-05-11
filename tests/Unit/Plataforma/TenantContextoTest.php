<?php

namespace App\Tests\Unit\Plataforma;

use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Plataforma\Application\Tenant\TenantContexto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class TenantContextoTest extends TestCase
{
    public function testPriorizaTenantDelUsuarioAutenticado(): void
    {
        $request = new Request();
        $request->headers->set('X-Tenant-Id', 'T2');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(new Usuario('u-1', 'T1', 'u1@example.com', ['ROLE_EMPLEADO']));
        $tokenStorage->method('getToken')->willReturn($token);

        $contexto = new TenantContexto($requestStack, $tokenStorage);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('TENANT_MISMATCH');
        $contexto->obtenerTenantId();
    }

    public function testUsaCabeceraComoFallbackSeguro(): void
    {
        $request = new Request();
        $request->headers->set('X-Tenant-Id', 'T2');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $contexto = new TenantContexto($requestStack, $tokenStorage);

        self::assertSame('T2', $contexto->obtenerTenantId());
    }

    public function testFallaCuandoNoHayTenantEnRequestNiSesion(): void
    {
        $requestStack = new RequestStack();

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $contexto = new TenantContexto($requestStack, $tokenStorage);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('TENANT_NO_RESUELTO');
        $contexto->obtenerTenantId();
    }
}
