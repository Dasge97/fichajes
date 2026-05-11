<?php

namespace App\Modulo\Ausencias\Application\Servicio;

use App\Modulo\Ausencias\Domain\Entity\SolicitudAusencia;
use App\Modulo\Ausencias\Infrastructure\Repository\SolicitudAusenciaRepository;
use DateTimeImmutable;

class SolicitarAusencia
{
    public function __construct(private readonly SolicitudAusenciaRepository $repository) {}

    public function ejecutar(string $tenantId, string $empleadoId, string $tipo, DateTimeImmutable $inicio, DateTimeImmutable $fin, ?string $idempotencyKey = null): SolicitudAusencia
    {
        $hash = hash('sha256', json_encode([
            'empleadoId' => $empleadoId,
            'tipo' => $tipo,
            'fechaInicio' => $inicio->format('Y-m-d'),
            'fechaFin' => $fin->format('Y-m-d'),
        ], JSON_THROW_ON_ERROR));

        if ($idempotencyKey !== null) {
            $existente = $this->repository->buscarPorIdempotencia($tenantId, $idempotencyKey);
            if ($existente !== null) {
                if ($existente->getPayloadHash() !== $hash) {
                    throw new \DomainException('IDEMPOTENCY_CONFLICT');
                }

                return $existente;
            }
        }

        $solicitud = new SolicitudAusencia(bin2hex(random_bytes(16)), $tenantId, $empleadoId, $tipo, $inicio, $fin, $idempotencyKey, $hash);
        $this->repository->guardar($solicitud);
        return $solicitud;
    }
}
