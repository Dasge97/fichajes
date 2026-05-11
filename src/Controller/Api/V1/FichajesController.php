<?php

namespace App\Controller\Api\V1;

use App\Modulo\Fichajes\Application\Servicio\RegistrarEventoFichaje;
use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Plataforma\Application\Tenant\TenantContexto;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/fichajes')]
class FichajesController extends AbstractController
{
    public function __construct(private readonly TenantContexto $tenantContexto) {}

    #[Route('/eventos', methods: ['POST'])]
    public function registrar(Request $request, RegistrarEventoFichaje $servicio): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $tenantId = $this->tenantContexto->obtenerTenantId();
        try {
            $usuario = $this->getUser();
            $servicio->ejecutar(
                $tenantId,
                $payload['empleadoId'],
                $payload['tipo'],
                new DateTimeImmutable($payload['ocurridoEn']),
                $payload['politica'] ?? 'bloquear',
                $payload['idempotencyKey'] ?? null,
                $usuario instanceof Usuario ? $usuario : null
            );
        } catch (\DomainException $e) {
            return new JsonResponse(['codigo' => $e->getMessage()], 422);
        }

        return new JsonResponse(['status' => 'ok'], 201);
    }
}
