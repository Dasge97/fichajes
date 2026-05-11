<?php

namespace App\Modulo\Acceso\Application\Servicio;

use App\Modulo\Acceso\Domain\Entity\Rol;
use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Acceso\Domain\Entity\UsuarioRol;
use App\Modulo\Auditoria\Application\Servicio\RegistrarAuditoria;
use Doctrine\ORM\EntityManagerInterface;

class AsignarRolUsuario
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RegistrarAuditoria $auditoria
    ) {}

    public function ejecutar(string $tenantId, string $usuarioId, string $codigoRol, ?string $actorUsuarioId): void
    {
        $usuario = $this->entityManager->getRepository(Usuario::class)->find($usuarioId);
        $rol = $this->entityManager->getRepository(Rol::class)->findOneBy(['codigo' => $codigoRol]);
        if (!$usuario instanceof Usuario || !$rol instanceof Rol || $usuario->getTenantId() !== $tenantId) {
            throw new \DomainException('ASIGNACION_ROL_INVALIDA');
        }

        $existe = $this->entityManager->getRepository(UsuarioRol::class)->findOneBy([
            'tenantId' => $tenantId,
            'usuario' => $usuario,
            'rol' => $rol,
        ]);
        if ($existe instanceof UsuarioRol) {
            return;
        }

        $this->entityManager->persist(new UsuarioRol(bin2hex(random_bytes(16)), $tenantId, $usuario, $rol, $actorUsuarioId));
        $this->entityManager->flush();
        $this->auditoria->registrar($tenantId, 'acceso.rol.asignado', ['usuarioId' => $usuarioId], ['rol' => $codigoRol, 'actor' => $actorUsuarioId]);
    }
}
