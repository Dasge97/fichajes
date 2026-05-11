<?php

namespace App\Controller\Api\V1;

use App\Modulo\Acceso\Application\Servicio\ResolverPermisoRol;
use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Correcciones\Application\Servicio\AprobarCorreccionFichaje;
use App\Modulo\Correcciones\Application\Servicio\SolicitarCorreccionFichaje;
use App\Modulo\Fichajes\Domain\Entity\EventoFichaje;
use App\Modulo\Plataforma\Application\Tenant\TenantContexto;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/correcciones')]
class CorreccionesController extends AbstractController
{
    public function __construct(
        private readonly TenantContexto $tenantContexto,
        private readonly ResolverPermisoRol $resolverPermisoRol,
        private readonly EntityManagerInterface $entityManager
    ) {}

    #[Route('', methods: ['POST'])]
    public function solicitar(Request $request, SolicitarCorreccionFichaje $servicio): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_EMPLEADO');
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $tenantId = $this->tenantContexto->obtenerTenantId();
        $eventoId = (string) $payload['eventoFichajeId'];
        $evento = $this->entityManager->getRepository(EventoFichaje::class)->findOneBy(['id' => $eventoId, 'tenantId' => $tenantId]);
        if (!$evento instanceof EventoFichaje) {
            return new JsonResponse(['codigo' => 'EVENTO_ORIGINAL_NO_ENCONTRADO'], 404);
        }
        $usuario = $this->getUser();
        $correccion = $servicio->ejecutar(
            $tenantId,
            $eventoId,
            $payload['motivo'],
            $payload['evidencia'] ?? null,
            isset($payload['ocurridoEnCorregido']) ? new DateTimeImmutable($payload['ocurridoEnCorregido']) : null,
            $payload['tipoCorregido'] ?? null,
            $usuario instanceof Usuario ? $usuario : null,
            $evento->getEmpleadoId()
        );

        return new JsonResponse(['id' => $correccion->getId(), 'estado' => $correccion->getEstado()], 201);
    }

    #[Route('/{id}/aprobar', methods: ['POST'])]
    public function aprobar(string $id, AprobarCorreccionFichaje $servicio): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERVISOR');
        $this->asegurarPermiso('correcciones.aprobar');
        try {
            $usuario = $this->getUser();
            $servicio->ejecutar($this->tenantContexto->obtenerTenantId(), $id, $usuario instanceof Usuario ? $usuario : null);
        } catch (\DomainException $e) {
            return new JsonResponse(['codigo' => $e->getMessage()], 422);
        }

        return new JsonResponse(['status' => 'aprobada']);
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
