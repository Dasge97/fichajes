<?php

namespace App\Modulo\Ausencias\Application\Servicio;

use App\Modulo\Auditoria\Application\Servicio\RegistrarAuditoria;
use App\Modulo\Ausencias\Infrastructure\Repository\SolicitudAusenciaRepository;

class AprobarAusencia
{
    public function __construct(private readonly SolicitudAusenciaRepository $repository, private readonly RegistrarAuditoria $auditoria) {}

    public function ejecutar(string $tenantId, string $id): void
    {
        $solicitud = $this->repository->buscarPorId($id);
        if ($solicitud === null) {
            throw new \DomainException('Solicitud no encontrada.');
        }
        if ($solicitud->getTenantId() !== $tenantId) {
            throw new \DomainException('TENANT_MISMATCH');
        }
        $antes = ['estado' => $solicitud->getEstado()];
        $solicitud->aprobar();
        $this->repository->guardar($solicitud);
        $this->auditoria->registrar($solicitud->getTenantId(), 'ausencia.aprobada', $antes, ['estado' => $solicitud->getEstado()]);
    }
}
