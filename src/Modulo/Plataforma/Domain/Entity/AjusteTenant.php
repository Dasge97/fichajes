<?php

namespace App\Modulo\Plataforma\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ajuste_tenant')]
class AjusteTenant
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $tenantId;

    #[ORM\Column(type: 'json')]
    private array $datos = [];

    public function __construct(string $tenantId)
    {
        $this->tenantId = $tenantId;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getDatos(): array
    {
        return $this->datos;
    }

    public function setDatos(array $datos): void
    {
        $this->datos = $datos;
    }

    public function get(string $clave, mixed $defecto = null): mixed
    {
        return $this->datos[$clave] ?? $defecto;
    }

    public function set(string $clave, mixed $valor): void
    {
        $this->datos[$clave] = $valor;
    }
}
