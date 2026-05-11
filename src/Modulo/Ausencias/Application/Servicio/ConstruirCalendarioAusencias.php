<?php

namespace App\Modulo\Ausencias\Application\Servicio;

use App\Modulo\Ausencias\Infrastructure\Repository\SolicitudAusenciaRepository;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;

class ConstruirCalendarioAusencias
{
    public function __construct(private readonly SolicitudAusenciaRepository $repository) {}

    public function ejecutar(string $tenantId, string $empleadoId, DateTimeImmutable $desde, DateTimeImmutable $hasta): array
    {
        $solicitudes = $this->repository->buscarAprobadasEnPeriodo($tenantId, $empleadoId, $desde, $hasta);
        $dias = [];

        foreach ($solicitudes as $solicitud) {
            $inicio = $solicitud->getFechaInicio() > $desde ? $solicitud->getFechaInicio() : $desde;
            $fin = $solicitud->getFechaFin() < $hasta ? $solicitud->getFechaFin() : $hasta;

            $periodo = new DatePeriod($inicio, new DateInterval('P1D'), $fin->modify('+1 day'));
            foreach ($periodo as $dia) {
                $dias[$dia->format('Y-m-d')] = [
                    'ausente' => true,
                    'tipo' => $solicitud->getTipo(),
                    'solicitudId' => $solicitud->getId(),
                ];
            }
        }

        ksort($dias);
        return $dias;
    }
}
