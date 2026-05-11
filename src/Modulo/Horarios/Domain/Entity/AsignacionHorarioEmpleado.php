<?php

namespace App\Modulo\Horarios\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'asignacion_horario_empleado')]
class AsignacionHorarioEmpleado
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(length: 36)]
    private string $tenantId;

    #[ORM\Column(length: 36)]
    private string $empleadoId;

    #[ORM\Column(length: 36)]
    private string $horarioId;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $vigenteDesde;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $vigenteHasta;

    public function __construct(string $id, string $tenantId, string $empleadoId, string $horarioId, DateTimeImmutable $vigenteDesde, ?DateTimeImmutable $vigenteHasta)
    {
        if ($vigenteHasta !== null && $vigenteHasta < $vigenteDesde) {
            throw new \DomainException('Rango de vigencia invalido.');
        }

        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->empleadoId = $empleadoId;
        $this->horarioId = $horarioId;
        $this->vigenteDesde = $vigenteDesde;
        $this->vigenteHasta = $vigenteHasta;
    }

    public function getEmpleadoId(): string { return $this->empleadoId; }
    public function getTenantId(): string { return $this->tenantId; }
    public function getHorarioId(): string { return $this->horarioId; }
    public function getVigenteDesde(): DateTimeImmutable { return $this->vigenteDesde; }
    public function getVigenteHasta(): ?DateTimeImmutable { return $this->vigenteHasta; }
}
