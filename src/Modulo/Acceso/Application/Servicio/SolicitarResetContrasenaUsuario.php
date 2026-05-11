<?php

namespace App\Modulo\Acceso\Application\Servicio;

use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Auditoria\Application\Servicio\RegistrarAuditoria;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class SolicitarResetContrasenaUsuario
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
        private readonly RegistrarAuditoria $auditoria
    ) {}

    public function ejecutar(string $tenantId, string $usuarioId, ?string $actorUsuarioId, int $ttlSegundos = 900): string
    {
        $usuario = $this->entityManager->getRepository(Usuario::class)->find($usuarioId);
        if (!$usuario instanceof Usuario || $usuario->getTenantId() !== $tenantId) {
            throw new \DomainException('USUARIO_NO_ENCONTRADO');
        }

        $tokenPlano = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $ahora = new \DateTimeImmutable();

        $this->connection->executeStatement('UPDATE usuario_reset_token SET usado_en = :usado WHERE tenant_id = :tenant AND usuario_id = :usuario AND usado_en IS NULL', [
            'usado' => $ahora->format('Y-m-d H:i:s'),
            'tenant' => $tenantId,
            'usuario' => $usuarioId,
        ]);

        $this->connection->insert('usuario_reset_token', [
            'id' => bin2hex(random_bytes(16)),
            'tenant_id' => $tenantId,
            'usuario_id' => $usuarioId,
            'token_hash' => hash('sha256', $tokenPlano),
            'expira_en' => $ahora->modify(sprintf('+%d seconds', $ttlSegundos))->format('Y-m-d H:i:s'),
            'creado_en' => $ahora->format('Y-m-d H:i:s'),
            'usado_en' => null,
        ]);

        $this->auditoria->registrar($tenantId, 'acceso.password.reset.solicitado', ['usuarioId' => $usuarioId], ['actor' => $actorUsuarioId]);

        return $tokenPlano;
    }
}
