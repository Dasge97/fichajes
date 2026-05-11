<?php

namespace App\Modulo\Acceso\Application\Servicio;

use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Auditoria\Application\Servicio\RegistrarAuditoria;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ConfirmarResetContrasenaUsuario
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly RegistrarAuditoria $auditoria
    ) {}

    public function ejecutar(string $tenantId, string $tokenPlano, string $nuevaContrasena, ?string $actorUsuarioId): void
    {
        $contrasenaLimpia = trim($nuevaContrasena);
        if (mb_strlen($contrasenaLimpia) < 12) {
            throw new \DomainException('PASSWORD_DEMASIADO_CORTA');
        }
        if (!preg_match('/[A-Z]/', $contrasenaLimpia) || !preg_match('/[a-z]/', $contrasenaLimpia) || !preg_match('/\d/', $contrasenaLimpia)) {
            throw new \DomainException('PASSWORD_COMPLEJIDAD_INSUFICIENTE');
        }

        $tokenHash = hash('sha256', trim($tokenPlano));
        $fila = $this->connection->fetchAssociative(
            'SELECT id, usuario_id, expira_en, usado_en FROM usuario_reset_token WHERE tenant_id = :tenant AND token_hash = :hash ORDER BY creado_en DESC LIMIT 1',
            ['tenant' => $tenantId, 'hash' => $tokenHash]
        );
        if ($fila === false) {
            throw new \DomainException('RESET_TOKEN_INVALIDO');
        }
        if ($fila['usado_en'] !== null) {
            throw new \DomainException('RESET_TOKEN_USADO');
        }
        if (new \DateTimeImmutable((string) $fila['expira_en']) <= new \DateTimeImmutable()) {
            throw new \DomainException('RESET_TOKEN_EXPIRADO');
        }

        $usuario = $this->entityManager->getRepository(Usuario::class)->find((string) $fila['usuario_id']);
        if (!$usuario instanceof Usuario || $usuario->getTenantId() !== $tenantId) {
            throw new \DomainException('USUARIO_NO_ENCONTRADO');
        }
        if (!$usuario->estaActivo()) {
            throw new \DomainException('USUARIO_INACTIVO');
        }

        $usuario->setPassword($this->passwordHasher->hashPassword($usuario, $contrasenaLimpia));
        $usuario->limpiarIntentosWeb();
        $this->entityManager->flush();

        $this->connection->update('usuario_reset_token', [
            'usado_en' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], [
            'id' => (string) $fila['id'],
        ]);

        $this->auditoria->registrar($tenantId, 'acceso.password.reset.confirmado', ['usuarioId' => $usuario->getId()], ['actor' => $actorUsuarioId]);
    }
}
