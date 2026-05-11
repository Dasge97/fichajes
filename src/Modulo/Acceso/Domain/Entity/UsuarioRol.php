<?php

namespace App\Modulo\Acceso\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'usuario_rol')]
#[ORM\UniqueConstraint(name: 'uniq_usuario_rol_tenant', columns: ['tenantId', 'usuario_id', 'rol_id'])]
class UsuarioRol
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(length: 36)]
    private string $tenantId;

    #[ORM\ManyToOne(targetEntity: Usuario::class, inversedBy: 'roles')]
    #[ORM\JoinColumn(name: 'usuario_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Usuario $usuario;

    #[ORM\ManyToOne(targetEntity: Rol::class)]
    #[ORM\JoinColumn(name: 'rol_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Rol $rol;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $asignadoPorUsuarioId;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $asignadoEn;

    public function __construct(string $id, string $tenantId, Usuario $usuario, Rol $rol, ?string $asignadoPorUsuarioId)
    {
        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->usuario = $usuario;
        $this->rol = $rol;
        $this->asignadoPorUsuarioId = $asignadoPorUsuarioId;
        $this->asignadoEn = new DateTimeImmutable();
    }

    public function getRol(): Rol { return $this->rol; }
    public function getTenantId(): string { return $this->tenantId; }
}
