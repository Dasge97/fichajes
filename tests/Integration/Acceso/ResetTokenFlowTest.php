<?php

namespace App\Tests\Integration\Acceso;

use App\Modulo\Acceso\Application\Servicio\ConfirmarResetContrasenaUsuario;
use App\Modulo\Acceso\Application\Servicio\SolicitarResetContrasenaUsuario;
use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Auditoria\Application\Servicio\RegistrarAuditoria;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ResetTokenFlowTest extends TestCase
{
    public function testEmiteTokenYRechazaReuso(): void
    {
        $usuario = new Usuario('u-1', 'T1', 'u1@test.local', ['ROLE_EMPLEADO']);
        $em = $this->mockEntityManagerWithUser($usuario);
        $auditoria = $this->createMock(RegistrarAuditoria::class);
        $connection = $this->createMock(Connection::class);
        $connection->method('executeStatement')->willReturn(1);
        $connection->method('insert')->willReturn(1);
        $connection->method('fetchAssociative')->willReturnOnConsecutiveCalls(
            ['id' => 'rt-1', 'usuario_id' => 'u-1', 'expira_en' => '2999-01-01 00:00:00', 'usado_en' => null],
            ['id' => 'rt-1', 'usuario_id' => 'u-1', 'expira_en' => '2999-01-01 00:00:00', 'usado_en' => '2026-05-08 12:00:00']
        );

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hash-nuevo');

        $solicitar = new SolicitarResetContrasenaUsuario($em, $connection, $auditoria);
        $confirmar = new ConfirmarResetContrasenaUsuario($em, $connection, $hasher, $auditoria);

        $token = $solicitar->ejecutar('T1', 'u-1', 'actor');
        self::assertNotSame('', trim($token));

        $confirmar->ejecutar('T1', $token, 'NuevaPassword1A', 'actor');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('RESET_TOKEN_USADO');
        $confirmar->ejecutar('T1', $token, 'OtraPassword1A', 'actor');
    }

    public function testRechazaTokenExpirado(): void
    {
        $usuario = new Usuario('u-2', 'T1', 'u2@test.local', ['ROLE_EMPLEADO']);
        $em = $this->mockEntityManagerWithUser($usuario);
        $auditoria = $this->createMock(RegistrarAuditoria::class);
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'id' => 'rt-2',
            'usuario_id' => 'u-2',
            'expira_en' => '2000-01-01 00:00:00',
            'usado_en' => null,
        ]);

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $confirmar = new ConfirmarResetContrasenaUsuario($em, $connection, $hasher, $auditoria);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('RESET_TOKEN_EXPIRADO');
        $confirmar->ejecutar('T1', 'token-expirado', 'NuevaPassword2A', 'actor');
    }

    private function mockEntityManagerWithUser(Usuario $usuario): EntityManagerInterface
    {
        $repo = new class($usuario) {
            public function __construct(private Usuario $usuario) {}

            public function find(string $id): ?Usuario
            {
                return $id === $this->usuario->getId() ? $this->usuario : null;
            }
        };

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return $em;
    }
}
