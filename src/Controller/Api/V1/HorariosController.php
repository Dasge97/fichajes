<?php

namespace App\Controller\Api\V1;

use App\Modulo\Horarios\Application\Servicio\AsignarHorarioEmpleado;
use App\Modulo\Horarios\Application\Servicio\CrearHorario;
use App\Modulo\Horarios\Application\Servicio\EditarHorario;
use App\Modulo\Horarios\Infrastructure\Repository\AsignacionHorarioRepository;
use App\Modulo\Plataforma\Application\Tenant\TenantContexto;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/horarios')]
class HorariosController
{
    public function __construct(private readonly TenantContexto $tenantContexto) {}

    #[Route('/plantillas', methods: ['POST'])]
    public function crearPlantilla(Request $request, CrearHorario $servicio): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        try {
            $horario = $servicio->ejecutar($this->tenantContexto->obtenerTenantId(), $payload['nombre'], $payload['tramos'] ?? []);
        } catch (\DomainException $e) {
            $codigo = $e->getMessage();
            $status = $codigo === 'TENANT_MISMATCH' || $codigo === 'TENANT_NO_RESUELTO' ? 403 : 422;

            return new JsonResponse(['codigo' => $codigo], $status);
        }

        return new JsonResponse(['id' => $horario->getId()], 201);
    }

    #[Route('/plantillas/{id}', methods: ['PUT', 'PATCH'])]
    public function editarPlantilla(string $id, Request $request, EditarHorario $servicio): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        try {
            $horario = $servicio->ejecutar(
                $this->tenantContexto->obtenerTenantId(),
                $id,
                (string) ($payload['nombre'] ?? ''),
                $payload['tramos'] ?? []
            );
        } catch (\DomainException $e) {
            $codigo = $e->getMessage();
            $status = $codigo === 'TENANT_MISMATCH' || $codigo === 'TENANT_NO_RESUELTO' ? 403 : 422;

            return new JsonResponse(['codigo' => $codigo], $status);
        }

        return new JsonResponse([
            'id' => $horario->getId(),
            'nombre' => $horario->getNombre(),
            'tramos' => $horario->getTramos(),
        ]);
    }

    #[Route('/asignaciones', methods: ['POST'])]
    public function asignar(Request $request, AsignarHorarioEmpleado $servicio): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        try {
            $servicio->ejecutar(
                $this->tenantContexto->obtenerTenantId(),
                $payload['empleadoId'],
                $payload['horarioId'],
                new DateTimeImmutable($payload['vigenteDesde']),
                isset($payload['vigenteHasta']) ? new DateTimeImmutable($payload['vigenteHasta']) : null
            );
        } catch (\DomainException $e) {
            $codigo = $e->getMessage();
            $status = $codigo === 'TENANT_MISMATCH' || $codigo === 'TENANT_NO_RESUELTO' ? 403 : 422;

            return new JsonResponse(['codigo' => $codigo], $status);
        }

        return new JsonResponse(['status' => 'ok'], 201);
    }

    #[Route('/asignaciones/{empleadoId}', methods: ['GET'])]
    public function listarAsignaciones(string $empleadoId, AsignacionHorarioRepository $repository): JsonResponse
    {
        try {
            $tenantId = $this->tenantContexto->obtenerTenantId();
        } catch (\DomainException $e) {
            $codigo = $e->getMessage();
            if ($codigo === 'TENANT_MISMATCH' || $codigo === 'TENANT_NO_RESUELTO') {
                return new JsonResponse(['codigo' => $codigo], 403);
            }

            return new JsonResponse(['codigo' => $codigo], 422);
        }

        $items = $repository->buscarPorEmpleado($tenantId, $empleadoId);
        $data = array_map(static fn ($asignacion) => [
            'empleadoId' => $empleadoId,
            'horarioId' => $asignacion->getHorarioId(),
            'vigenteDesde' => $asignacion->getVigenteDesde()->format(DATE_ATOM),
            'vigenteHasta' => $asignacion->getVigenteHasta()?->format(DATE_ATOM),
        ], $items);

        return new JsonResponse(['items' => $data]);
    }
}
