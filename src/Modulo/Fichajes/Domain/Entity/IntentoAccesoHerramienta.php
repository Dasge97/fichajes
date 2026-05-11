<?php

namespace App\Modulo\Fichajes\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'intento_acceso_herramienta')]
#[ORM\UniqueConstraint(name: 'uniq_intento_herramienta_trabajador_ip', columns: ['trabajadorId', 'ipHash'])]
class IntentoAccesoHerramienta
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(length: 50)]
    private string $trabajadorId;

    #[ORM\Column(length: 64)]
    private string $ipHash;

    #[ORM\Column]
    private int $fallosAcumulados;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $ventanaIniciadaEn;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $bloqueadoHasta;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $actualizadoEn;

    public function __construct(string $id, string $trabajadorId, string $ipHash, DateTimeImmutable $ahora)
    {
        $this->id = $id;
        $this->trabajadorId = $trabajadorId;
        $this->ipHash = $ipHash;
        $this->fallosAcumulados = 0;
        $this->ventanaIniciadaEn = $ahora;
        $this->bloqueadoHasta = null;
        $this->actualizadoEn = $ahora;
    }

    public function getTrabajadorId(): string
    {
        return $this->trabajadorId;
    }

    public function getFallosAcumulados(): int
    {
        return $this->fallosAcumulados;
    }

    public function getBloqueadoHasta(): ?DateTimeImmutable
    {
        return $this->bloqueadoHasta;
    }

    public function estaBloqueado(DateTimeImmutable $ahora): bool
    {
        return $this->bloqueadoHasta !== null && $this->bloqueadoHasta > $ahora;
    }

    public function reiniciarVentana(DateTimeImmutable $ahora): void
    {
        $this->fallosAcumulados = 0;
        $this->ventanaIniciadaEn = $ahora;
        $this->bloqueadoHasta = null;
        $this->actualizadoEn = $ahora;
    }

    public function registrarFallo(DateTimeImmutable $ahora, int $limite, int $ventanaSegundos, int $bloqueoSegundos): void
    {
        $finVentana = $this->ventanaIniciadaEn->modify(sprintf('+%d seconds', $ventanaSegundos));
        if ($ahora >= $finVentana) {
            $this->fallosAcumulados = 0;
            $this->ventanaIniciadaEn = $ahora;
            $this->bloqueadoHasta = null;
        }

        ++$this->fallosAcumulados;
        if ($this->fallosAcumulados >= $limite) {
            $this->bloqueadoHasta = $ahora->modify(sprintf('+%d seconds', $bloqueoSegundos));
        }

        $this->actualizadoEn = $ahora;
    }
}
