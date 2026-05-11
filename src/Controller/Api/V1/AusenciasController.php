<?php

namespace App\Controller\Api\V1;

use App\Modulo\Acceso\Application\Servicio\ResolverPermisoRol;
use App\Modulo\Acceso\Application\Servicio\ValidarOwnershipTrabajador;
use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Ausencias\Application\Servicio\AprobarAusencia;
use App\Modulo\Ausencias\Application\Servicio\ConstruirCalendarioAusencias;
use App\Modulo\Ausencias\Application\Servicio\RechazarAusencia;
use App\Modulo\Ausencias\Application\Servicio\SolicitarAusencia;
use App\Modulo\Plataforma\Application\Tenant\TenantContexto;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/api/v1/ausencias')]
class AusenciasController extends AbstractController
{
    public function __construct(
        private readonly TenantContexto $tenantContexto,
        private readonly ResolverPermisoRol $resolverPermisoRol
    ) {}

    #[Route('', methods: ['POST'])]
    public function solicitar(Request $request, SolicitarAusencia $servicio, ValidarOwnershipTrabajador $ownership): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_EMPLEADO');
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->asegurarPermiso('fichajes.registrar.propio');
        $tenantId = $this->tenantContexto->obtenerTenantId();
        $empleadoId = (string) $payload['empleadoId'];
        $usuario = $this->getUser();
        if ($usuario instanceof Usuario) {
            $ownership->validar($usuario, $tenantId, $empleadoId, 'fichajes.registrar.propio');
        }
        $item = $servicio->ejecutar(
            $tenantId,
            $empleadoId,
            $payload['tipo'],
            new DateTimeImmutable($payload['fechaInicio']),
            new DateTimeImmutable($payload['fechaFin']),
            $payload['idempotencyKey'] ?? null
        );

        return new JsonResponse(['id' => $item->getId(), 'estado' => $item->getEstado()], 201);
    }

    #[Route('/{id}/aprobar', methods: ['POST'])]
    public function aprobar(string $id, AprobarAusencia $servicio): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERVISOR');
        $this->asegurarPermiso('correcciones.aprobar');
        try {
            $servicio->ejecutar($this->tenantContexto->obtenerTenantId(), $id);
        } catch (\DomainException $e) {
            return new JsonResponse(['codigo' => $e->getMessage()], 422);
        }

        return new JsonResponse(['status' => 'aprobada']);
    }

    #[Route('/{id}/rechazar', methods: ['POST'])]
    public function rechazar(string $id, RechazarAusencia $servicio): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERVISOR');
        $this->asegurarPermiso('correcciones.aprobar');
        try {
            $servicio->ejecutar($this->tenantContexto->obtenerTenantId(), $id);
        } catch (\DomainException $e) {
            return new JsonResponse(['codigo' => $e->getMessage()], 422);
        }

        return new JsonResponse(['status' => 'rechazada']);
    }

    #[Route('', methods: ['GET'])]
    public function calendario(Request $request, ConstruirCalendarioAusencias $servicio, ValidarOwnershipTrabajador $ownership): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_EMPLEADO');
        $empleadoId = (string) $request->query->get('empleadoId', '');
        $tenantId = $this->tenantContexto->obtenerTenantId();
        $usuario = $this->getUser();
        if ($usuario instanceof Usuario && $empleadoId !== '') {
            $ownership->validar($usuario, $tenantId, $empleadoId, 'fichajes.registrar.propio');
        }
        $desde = new DateTimeImmutable((string) $request->query->get('desde', 'now'));
        $hasta = new DateTimeImmutable((string) $request->query->get('hasta', 'now'));

        $calendario = $servicio->ejecutar(
            $tenantId,
            $empleadoId,
            $desde,
            $hasta
        );

        return new JsonResponse([
            'empleadoId' => $empleadoId,
            'desde' => $desde->format('Y-m-d'),
            'hasta' => $hasta->format('Y-m-d'),
            'dias' => $calendario,
        ]);
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
