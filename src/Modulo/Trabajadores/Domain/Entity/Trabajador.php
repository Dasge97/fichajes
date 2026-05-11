<?php

namespace App\Modulo\Trabajadores\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'trabajador')]
#[ORM\UniqueConstraint(name: 'uniq_trabajador_tenant_codigo', columns: ['tenantId', 'trabajadorId'])]
class Trabajador
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(length: 36)]
    private string $tenantId;

    #[ORM\Column(length: 50)]
    private string $trabajadorId;

    #[ORM\Column(length: 120)]
    private string $nombre;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email;

    #[ORM\Column]
    private bool $activo;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $fechaAlta;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $claveAccesoHash = null;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $usuarioId = null;

    public function __construct(string $id, string $tenantId, string $trabajadorId, string $nombre, ?string $email, DateTimeImmutable $fechaAlta)
    {
        if ($trabajadorId === '') {
            throw new \DomainException('TRABAJADOR_ID_REQUERIDO');
        }
        if ($nombre === '') {
            throw new \DomainException('TRABAJADOR_NOMBRE_REQUERIDO');
        }

        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->trabajadorId = $trabajadorId;
        $this->nombre = $nombre;
        $this->email = $email;
        $this->activo = true;
        $this->fechaAlta = $fechaAlta;
    }

    public function getId(): string { return $this->id; }
    public function getTenantId(): string { return $this->tenantId; }
    public function getTrabajadorId(): string { return $this->trabajadorId; }
    public function getNombre(): string { return $this->nombre; }
    public function getEmail(): ?string { return $this->email; }
    public function estaActivo(): bool { return $this->activo; }
    public function getFechaAlta(): DateTimeImmutable { return $this->fechaAlta; }
    public function tieneClaveAcceso(): bool { return $this->claveAccesoHash !== null; }
    public function getUsuarioId(): ?string { return $this->usuarioId; }

    public function editar(string $nombre, ?string $email): void
    {
        if ($nombre === '') {
            throw new \DomainException('TRABAJADOR_NOMBRE_REQUERIDO');
        }

        $this->nombre = $nombre;
        $this->email = $email;
    }

    public function cambiarEstado(bool $activo): void
    {
        $this->activo = $activo;
    }

    public function actualizarClaveAcceso(?string $clavePlano): void
    {
        $limpia = trim((string) $clavePlano);
        if ($limpia === '') {
            return;
        }
        if (mb_strlen($limpia) < 4) {
            throw new \DomainException('TRABAJADOR_CLAVE_CORTA');
        }

        $this->claveAccesoHash = password_hash($limpia, PASSWORD_DEFAULT);
    }

    public function validarClaveAcceso(string $clavePlano): bool
    {
        if ($this->claveAccesoHash === null) {
            return false;
        }

        return password_verify($clavePlano, $this->claveAccesoHash);
    }

    public function vincularUsuario(string $usuarioId): void
    {
        if (trim($usuarioId) === '') {
            throw new \DomainException('USUARIO_ID_REQUERIDO');
        }
        $this->usuarioId = $usuarioId;
    }
}
