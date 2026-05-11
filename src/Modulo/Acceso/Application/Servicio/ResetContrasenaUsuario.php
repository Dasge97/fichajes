<?php

namespace App\Modulo\Acceso\Application\Servicio;

use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Auditoria\Application\Servicio\RegistrarAuditoria;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ResetContrasenaUsuario
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly RegistrarAuditoria $auditoria
    ) {}

    public function ejecutar(string $tenantId, string $usuarioId, string $nuevaContrasena, ?string $actorUsuarioId): void
    {
        $contrasenaLimpia = trim($nuevaContrasena);
        if (mb_strlen($contrasenaLimpia) < 12) {
            throw new \DomainException('PASSWORD_DEMASIADO_CORTA');
        }
        if (!preg_match('/[A-Z]/', $contrasenaLimpia) || !preg_match('/[a-z]/', $contrasenaLimpia) || !preg_match('/\d/', $contrasenaLimpia)) {
            throw new \DomainException('PASSWORD_COMPLEJIDAD_INSUFICIENTE');
        }

        $usuario = $this->entityManager->getRepository(Usuario::class)->find($usuarioId);
        if (!$usuario instanceof Usuario || $usuario->getTenantId() !== $tenantId) {
            throw new \DomainException('USUARIO_NO_ENCONTRADO');
        }
        if (!$usuario->estaActivo()) {
            throw new \DomainException('USUARIO_INACTIVO');
        }

        $usuario->setPassword($this->passwordHasher->hashPassword($usuario, $contrasenaLimpia));
        $usuario->limpiarIntentosWeb();
        $this->entityManager->flush();

        $this->auditoria->registrar($tenantId, 'acceso.password.reset', ['usuarioId' => $usuarioId], ['actor' => $actorUsuarioId]);
    }
}
