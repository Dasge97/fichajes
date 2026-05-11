<?php

namespace App\Modulo\Horarios\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'horario_trabajo')]
class HorarioTrabajo
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(length: 36)]
    private string $tenantId;

    #[ORM\Column(length: 120)]
    private string $nombre;

    #[ORM\Column(type: 'json')]
    private array $tramos;

    public function __construct(string $id, string $tenantId, string $nombre, array $tramos)
    {
        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->nombre = $nombre;
        $this->tramos = $tramos;
    }

    public function getId(): string { return $this->id; }
    public function getTenantId(): string { return $this->tenantId; }
    public function getNombre(): string { return $this->nombre; }
    public function getTramos(): array { return $this->tramos; }

    public function editar(string $nombre, array $tramos): void
    {
        if ($nombre === '') {
            throw new \DomainException('HORARIO_NOMBRE_REQUERIDO');
        }

        if ($tramos === []) {
            throw new \DomainException('HORARIO_TRAMOS_REQUERIDOS');
        }

        $this->nombre = $nombre;
        $this->tramos = $tramos;
    }
}
