<?php

namespace App\Modulo\Correcciones\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'correccion_fichaje')]
class CorreccionFichaje
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(length: 36)]
    private string $tenantId;

    #[ORM\Column(length: 36)]
    private string $eventoFichajeId;

    #[ORM\Column(length: 20)]
    private string $estado;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $ocurridoEnCorregido;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $tipoCorregido;

    #[ORM\Column(length: 255)]
    private string $motivo;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $evidencia;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $eventoAplicadoId;

    public function __construct(string $id, string $tenantId, string $eventoFichajeId, string $motivo, ?string $evidencia, ?DateTimeImmutable $ocurridoEnCorregido, ?string $tipoCorregido)
    {
        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->eventoFichajeId = $eventoFichajeId;
        $this->estado = 'pendiente';
        $this->motivo = $motivo;
        $this->evidencia = $evidencia;
        $this->ocurridoEnCorregido = $ocurridoEnCorregido;
        $this->tipoCorregido = $tipoCorregido;
        $this->eventoAplicadoId = null;
    }

    public function aprobar(string $eventoAplicadoId): void
    {
        if ($this->estado !== 'pendiente') {
            throw new \DomainException('CORRECCION_NO_PENDIENTE');
        }

        $this->estado = 'aprobada';
        $this->eventoAplicadoId = $eventoAplicadoId;
    }

    public function getId(): string { return $this->id; }
    public function getTenantId(): string { return $this->tenantId; }
    public function getEventoFichajeId(): string { return $this->eventoFichajeId; }
    public function getEstado(): string { return $this->estado; }
    public function getOcurridoEnCorregido(): ?DateTimeImmutable { return $this->ocurridoEnCorregido; }
    public function getTipoCorregido(): ?string { return $this->tipoCorregido; }
}
