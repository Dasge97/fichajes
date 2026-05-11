<?php

namespace App\Modulo\Ausencias\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'solicitud_ausencia')]
class SolicitudAusencia
{
    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_APROBADA = 'aprobada';
    public const ESTADO_RECHAZADA = 'rechazada';
    public const ESTADO_CANCELADA = 'cancelada';

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(length: 36)]
    private string $tenantId;

    #[ORM\Column(length: 36)]
    private string $empleadoId;

    #[ORM\Column(length: 40)]
    private string $tipo;

    #[ORM\Column(type: 'date_immutable')]
    private DateTimeImmutable $fechaInicio;

    #[ORM\Column(type: 'date_immutable')]
    private DateTimeImmutable $fechaFin;

    #[ORM\Column(length: 20)]
    private string $estado;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $idempotencyKey;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $payloadHash;

    public function __construct(string $id, string $tenantId, string $empleadoId, string $tipo, DateTimeImmutable $fechaInicio, DateTimeImmutable $fechaFin, ?string $idempotencyKey = null, ?string $payloadHash = null)
    {
        if ($fechaFin < $fechaInicio) {
            throw new \DomainException('Periodo de ausencia invalido.');
        }

        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->empleadoId = $empleadoId;
        $this->tipo = $tipo;
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->estado = self::ESTADO_PENDIENTE;
        $this->idempotencyKey = $idempotencyKey;
        $this->payloadHash = $payloadHash;
    }

    public function aprobar(): void
    {
        if ($this->estado !== self::ESTADO_PENDIENTE) {
            throw new \DomainException('Solo se pueden aprobar ausencias pendientes.');
        }
        $this->estado = self::ESTADO_APROBADA;
    }

    public function rechazar(): void
    {
        if ($this->estado !== self::ESTADO_PENDIENTE) {
            throw new \DomainException('Solo se pueden rechazar ausencias pendientes.');
        }
        $this->estado = self::ESTADO_RECHAZADA;
    }

    public function getEstado(): string { return $this->estado; }
    public function getId(): string { return $this->id; }
    public function getTenantId(): string { return $this->tenantId; }
    public function getEmpleadoId(): string { return $this->empleadoId; }
    public function getTipo(): string { return $this->tipo; }
    public function getFechaInicio(): DateTimeImmutable { return $this->fechaInicio; }
    public function getFechaFin(): DateTimeImmutable { return $this->fechaFin; }
    public function getIdempotencyKey(): ?string { return $this->idempotencyKey; }
    public function getPayloadHash(): ?string { return $this->payloadHash; }
}
