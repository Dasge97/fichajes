<?php

namespace App\Modulo\Fichajes\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'evento_fichaje')]
class EventoFichaje
{
    public const TIPOS_VALIDOS = ['clock-in', 'pause-start', 'pause-end', 'clock-out'];

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(length: 36)]
    private string $tenantId;

    #[ORM\Column(length: 36)]
    private string $empleadoId;

    #[ORM\Column(length: 30)]
    private string $tipo;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $ocurridoEn;

    #[ORM\Column(length: 40)]
    private string $estadoCumplimiento;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $motivoDesvio;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $idempotencyKey;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $payloadHash;

    public function __construct(string $id, string $tenantId, string $empleadoId, string $tipo, DateTimeImmutable $ocurridoEn, string $estadoCumplimiento, ?string $motivoDesvio, ?string $idempotencyKey = null, ?string $payloadHash = null)
    {
        if (!in_array($tipo, self::TIPOS_VALIDOS, true)) {
            throw new \DomainException('TIPO_EVENTO_INVALIDO');
        }

        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->empleadoId = $empleadoId;
        $this->tipo = $tipo;
        $this->ocurridoEn = $ocurridoEn;
        $this->estadoCumplimiento = $estadoCumplimiento;
        $this->motivoDesvio = $motivoDesvio;
        $this->idempotencyKey = $idempotencyKey;
        $this->payloadHash = $payloadHash;
    }

    public function getId(): string { return $this->id; }
    public function getEmpleadoId(): string { return $this->empleadoId; }
    public function getTipo(): string { return $this->tipo; }
    public function getOcurridoEn(): DateTimeImmutable { return $this->ocurridoEn; }
    public function getEstadoCumplimiento(): string { return $this->estadoCumplimiento; }
    public function getMotivoDesvio(): ?string { return $this->motivoDesvio; }
    public function getIdempotencyKey(): ?string { return $this->idempotencyKey; }
    public function getPayloadHash(): ?string { return $this->payloadHash; }
}
