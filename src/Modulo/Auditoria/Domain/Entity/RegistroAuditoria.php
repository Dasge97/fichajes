<?php

namespace App\Modulo\Auditoria\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'registro_auditoria')]
class RegistroAuditoria
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(length: 36)]
    private string $tenantId;

    #[ORM\Column(length: 120)]
    private string $accion;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $antes;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $despues;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $creadoEn;

    public function __construct(string $id, string $tenantId, string $accion, ?array $antes, ?array $despues)
    {
        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->accion = $accion;
        $this->antes = $antes;
        $this->despues = $despues;
        $this->creadoEn = new DateTimeImmutable();
    }
}
