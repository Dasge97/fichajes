<?php

namespace App\Controller\Api\V1;

use App\Modulo\Plataforma\Application\Tenant\TenantContexto;
use App\Modulo\Acceso\Application\Servicio\AsignarRolUsuario;
use App\Modulo\Acceso\Application\Servicio\ConfirmarResetContrasenaUsuario;
use App\Modulo\Acceso\Application\Servicio\ResolverPermisoRol;
use App\Modulo\Acceso\Application\Servicio\SolicitarResetContrasenaUsuario;
use App\Modulo\Acceso\Application\Servicio\RevocarRolUsuario;
use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Trabajadores\Application\Servicio\CambiarEstadoTrabajador;
use App\Modulo\Trabajadores\Application\Servicio\CrearTrabajador;
use App\Modulo\Trabajadores\Application\Servicio\EditarTrabajador;
use App\Modulo\Trabajadores\Application\Servicio\ListarTrabajadores;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/api/v1/trabajadores')]
class TrabajadoresController extends AbstractController
{
    public function __construct(
        private readonly TenantContexto $tenantContexto,
        private readonly EntityManagerInterface $entityManager,
        private readonly ResolverPermisoRol $resolverPermisoRol
    ) {}

    #[Route('', methods: ['GET'])]
    public function listar(ListarTrabajadores $servicio): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_EMPLEADO');
        $tenantId = $this->tenantContexto->obtenerTenantId();
        $items = $servicio->ejecutar($tenantId);
        $data = array_map(static fn ($t) => [
            'trabajadorId' => $t->getTrabajadorId(),
            'nombre' => $t->getNombre(),
            'email' => $t->getEmail(),
            'activo' => $t->estaActivo(),
            'fechaAlta' => $t->getFechaAlta()->format(DATE_ATOM),
        ], $items);

        return new JsonResponse(['items' => $data]);
    }

    #[Route('', methods: ['POST'])]
    public function crear(Request $request, CrearTrabajador $servicio): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERVISOR');
        $this->asegurarPermiso('trabajadores.crear');
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        try {
            $trabajador = $servicio->ejecutar(
                $this->tenantContexto->obtenerTenantId(),
                trim((string) ($payload['trabajadorId'] ?? '')),
                trim((string) ($payload['nombre'] ?? '')),
                isset($payload['email']) ? (string) $payload['email'] : null,
                isset($payload['pinKiosko']) ? (string) $payload['pinKiosko'] : null
            );
        } catch (\DomainException $e) {
            return new JsonResponse(['codigo' => $e->getMessage()], 422);
        }

        return new JsonResponse(['trabajadorId' => $trabajador->getTrabajadorId()], 201);
    }

    #[Route('/{trabajadorId}', methods: ['PUT', 'PATCH'])]
    public function editar(string $trabajadorId, Request $request, EditarTrabajador $servicio): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERVISOR');
        $this->asegurarPermiso('trabajadores.editar');
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        try {
            $trabajador = $servicio->ejecutar(
                $this->tenantContexto->obtenerTenantId(),
                $trabajadorId,
                trim((string) ($payload['nombre'] ?? '')),
                isset($payload['email']) ? (string) $payload['email'] : null,
                isset($payload['pinKiosko']) ? (string) $payload['pinKiosko'] : null
            );
        } catch (\DomainException $e) {
            return new JsonResponse(['codigo' => $e->getMessage()], 422);
        }

        return new JsonResponse([
            'trabajadorId' => $trabajador->getTrabajadorId(),
            'nombre' => $trabajador->getNombre(),
            'email' => $trabajador->getEmail(),
            'activo' => $trabajador->estaActivo(),
        ]);
    }

    #[Route('/{trabajadorId}/estado', methods: ['POST'])]
    public function estado(string $trabajadorId, Request $request, CambiarEstadoTrabajador $servicio): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERVISOR');
        $this->asegurarPermiso('trabajadores.editar');
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        try {
            $trabajador = $servicio->ejecutar(
                $this->tenantContexto->obtenerTenantId(),
                $trabajadorId,
                (bool) ($payload['activo'] ?? false)
            );
        } catch (\DomainException $e) {
            return new JsonResponse(['codigo' => $e->getMessage()], 422);
        }

        return new JsonResponse(['trabajadorId' => $trabajador->getTrabajadorId(), 'activo' => $trabajador->estaActivo()]);
    }

    #[Route('/{trabajadorId}/cuenta/crear', methods: ['POST'])]
    public function crearCuenta(string $trabajadorId, Request $request, UserPasswordHasherInterface $passwordHasher, AsignarRolUsuario $asignarRol): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERVISOR');
        $this->asegurarPermiso('trabajadores.roles.gestionar');
        $tenantId = $this->tenantContexto->obtenerTenantId();
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $trabajador = $this->entityManager->createQueryBuilder()->select('t')->from('App\\Modulo\\Trabajadores\\Domain\\Entity\\Trabajador', 't')
            ->andWhere('t.tenantId = :tenant')->andWhere('t.trabajadorId = :trabajadorId')
            ->setParameter('tenant', $tenantId)->setParameter('trabajadorId', $trabajadorId)->getQuery()->getOneOrNullResult();
        if ($trabajador === null) {
            return new JsonResponse(['codigo' => 'TRABAJADOR_NO_ENCONTRADO'], 404);
        }

        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $usuario = new Usuario(bin2hex(random_bytes(16)), $tenantId, $email, ['ROLE_EMPLEADO']);
        $usuario->setPassword($passwordHasher->hashPassword($usuario, $password));
        $this->entityManager->persist($usuario);
        $trabajador->vincularUsuario($usuario->getId());
        $this->entityManager->flush();
        $asignarRol->ejecutar($tenantId, $usuario->getId(), 'trabajador', $this->actorUsuarioId());

        return new JsonResponse(['usuarioId' => $usuario->getId()], 201);
    }

    #[Route('/{trabajadorId}/cuenta/activar', methods: ['POST'])]
    public function activarCuenta(string $trabajadorId): JsonResponse
    {
        $this->asegurarPermiso('trabajadores.roles.gestionar');

        return $this->cambiarEstadoCuenta($trabajadorId, true);
    }

    #[Route('/{trabajadorId}/cuenta/desactivar', methods: ['POST'])]
    public function desactivarCuenta(string $trabajadorId): JsonResponse
    {
        $this->asegurarPermiso('trabajadores.roles.gestionar');

        return $this->cambiarEstadoCuenta($trabajadorId, false);
    }

    #[Route('/{trabajadorId}/cuenta/reset-password', methods: ['POST'])]
    public function solicitarResetPassword(string $trabajadorId, SolicitarResetContrasenaUsuario $solicitarReset): JsonResponse
    {
        $this->asegurarPermiso('trabajadores.roles.gestionar');
        $tenantId = $this->tenantContexto->obtenerTenantId();
        $usuario = $this->resolverUsuarioTrabajador($tenantId, $trabajadorId);
        if ($usuario === null) {
            return new JsonResponse(['codigo' => 'CUENTA_NO_ENCONTRADA'], 404);
        }

        $token = $solicitarReset->ejecutar($tenantId, $usuario->getId(), $this->actorUsuarioId());

        return new JsonResponse(['status' => 'token_emitido', 'resetToken' => $token]);
    }

    #[Route('/{trabajadorId}/cuenta/reset-password/confirmar', methods: ['POST'])]
    public function confirmarResetPassword(string $trabajadorId, Request $request, ConfirmarResetContrasenaUsuario $confirmarReset): JsonResponse
    {
        $this->asegurarPermiso('trabajadores.roles.gestionar');
        $tenantId = $this->tenantContexto->obtenerTenantId();
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $usuario = $this->resolverUsuarioTrabajador($tenantId, $trabajadorId);
        if ($usuario === null) {
            return new JsonResponse(['codigo' => 'CUENTA_NO_ENCONTRADA'], 404);
        }
        $confirmarReset->ejecutar($tenantId, (string) ($payload['token'] ?? ''), (string) ($payload['password'] ?? ''), $this->actorUsuarioId());

        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/{trabajadorId}/cuenta/roles/asignar', methods: ['POST'])]
    public function asignarRol(string $trabajadorId, Request $request, AsignarRolUsuario $asignarRolUsuario): JsonResponse
    {
        $this->asegurarPermiso('trabajadores.roles.gestionar');
        $tenantId = $this->tenantContexto->obtenerTenantId();
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $usuario = $this->resolverUsuarioTrabajador($tenantId, $trabajadorId);
        if ($usuario === null) {
            return new JsonResponse(['codigo' => 'CUENTA_NO_ENCONTRADA'], 404);
        }
        $asignarRolUsuario->ejecutar($tenantId, $usuario->getId(), (string) ($payload['rol'] ?? ''), $this->actorUsuarioId());

        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/{trabajadorId}/cuenta/roles/revocar', methods: ['POST'])]
    public function revocarRol(string $trabajadorId, Request $request, RevocarRolUsuario $revocarRolUsuario): JsonResponse
    {
        $this->asegurarPermiso('trabajadores.roles.gestionar');
        $tenantId = $this->tenantContexto->obtenerTenantId();
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $usuario = $this->resolverUsuarioTrabajador($tenantId, $trabajadorId);
        if ($usuario === null) {
            return new JsonResponse(['codigo' => 'CUENTA_NO_ENCONTRADA'], 404);
        }
        $revocarRolUsuario->ejecutar($tenantId, $usuario->getId(), (string) ($payload['rol'] ?? ''), $this->actorUsuarioId());

        return new JsonResponse(['status' => 'ok']);
    }

    private function cambiarEstadoCuenta(string $trabajadorId, bool $activo): JsonResponse
    {
        $tenantId = $this->tenantContexto->obtenerTenantId();
        $usuario = $this->resolverUsuarioTrabajador($tenantId, $trabajadorId);
        if ($usuario === null) {
            return new JsonResponse(['codigo' => 'CUENTA_NO_ENCONTRADA'], 404);
        }
        $activo ? $usuario->activar() : $usuario->desactivar();
        $this->entityManager->flush();

        return new JsonResponse(['status' => 'ok', 'activo' => $usuario->estaActivo()]);
    }

    private function resolverUsuarioTrabajador(string $tenantId, string $trabajadorId): ?Usuario
    {
        $trabajador = $this->entityManager->createQueryBuilder()->select('t')->from('App\\Modulo\\Trabajadores\\Domain\\Entity\\Trabajador', 't')
            ->andWhere('t.tenantId = :tenant')->andWhere('t.trabajadorId = :trabajadorId')
            ->setParameter('tenant', $tenantId)->setParameter('trabajadorId', $trabajadorId)->getQuery()->getOneOrNullResult();
        if ($trabajador === null || $trabajador->getUsuarioId() === null) {
            return null;
        }

        $usuario = $this->entityManager->getRepository(Usuario::class)->find($trabajador->getUsuarioId());

        return $usuario instanceof Usuario ? $usuario : null;
    }

    private function actorUsuarioId(): ?string
    {
        $actor = $this->getUser();

        return $actor instanceof Usuario ? $actor->getId() : null;
    }

    private function asegurarPermiso(string $permiso): void
    {
        $usuario = $this->getUser();
        if (!$usuario instanceof Usuario) {
            throw $this->createAccessDeniedException('Usuario no autenticado.');
        }
        if (!$this->resolverPermisoRol->puede($permiso, $usuario->getCodigosRolTenant())) {
            throw $this->createAccessDeniedException('Permiso insuficiente.');
        }
    }
}
