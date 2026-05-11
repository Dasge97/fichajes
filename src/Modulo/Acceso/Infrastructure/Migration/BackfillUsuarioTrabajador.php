<?php

namespace App\Modulo\Acceso\Infrastructure\Migration;

use App\Modulo\Acceso\Domain\Entity\Rol;
use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Acceso\Domain\Entity\UsuarioRol;
use App\Modulo\Trabajadores\Domain\Entity\Trabajador;
use Doctrine\ORM\EntityManagerInterface;

class BackfillUsuarioTrabajador
{
    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function ejecutar(): int
    {
        $repoTrabajador = $this->entityManager->getRepository(Trabajador::class);
        $repoUsuario = $this->entityManager->getRepository(Usuario::class);
        $repoRol = $this->entityManager->getRepository(Rol::class);
        $rolTrabajador = $repoRol->findOneBy(['codigo' => 'trabajador']);
        if (!$rolTrabajador instanceof Rol) {
            throw new \RuntimeException('ROL_TRABAJADOR_NO_ENCONTRADO');
        }

        $migrados = 0;
        foreach ($repoTrabajador->findAll() as $trabajador) {
            if (!$trabajador instanceof Trabajador || $trabajador->getUsuarioId() !== null || $trabajador->getEmail() === null) {
                continue;
            }

            $usuario = $repoUsuario->findOneBy([
                'tenantId' => $trabajador->getTenantId(),
                'email' => $trabajador->getEmail(),
            ]);
            if (!$usuario instanceof Usuario) {
                $usuario = new Usuario(bin2hex(random_bytes(16)), $trabajador->getTenantId(), $trabajador->getEmail(), ['ROLE_EMPLEADO']);
                $usuario->setPassword(password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT));
                $this->entityManager->persist($usuario);
            }

            $trabajador->vincularUsuario($usuario->getId());
            $asignacion = $this->entityManager->getRepository(UsuarioRol::class)->findOneBy([
                'tenantId' => $trabajador->getTenantId(),
                'usuario' => $usuario,
                'rol' => $rolTrabajador,
            ]);
            if (!$asignacion instanceof UsuarioRol) {
                $this->entityManager->persist(new UsuarioRol(bin2hex(random_bytes(16)), $trabajador->getTenantId(), $usuario, $rolTrabajador, null));
            }
            $migrados++;
        }

        $this->entityManager->flush();

        return $migrados;
    }
}
