<?php

namespace App\Modulo\Acceso\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'usuario')]
class Usuario implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(length: 36)]
    private string $tenantId;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(type: 'json')]
    private array $rolesLegacy = [];

    #[ORM\Column]
    private string $password;

    #[ORM\Column]
    private bool $activo = true;

    #[ORM\Column(type: 'integer')]
    private int $intentosWebFallidos = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $bloqueadoHasta = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $passwordActualizadoEn = null;

    #[ORM\OneToMany(mappedBy: 'usuario', targetEntity: UsuarioRol::class, cascade: ['persist', 'remove'])]
    private Collection $roles;

    public function __construct(string $id, string $tenantId, string $email, array $rolesLegacy = [])
    {
        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->email = $email;
        $this->rolesLegacy = $rolesLegacy;
        $this->roles = new ArrayCollection();
    }

    public function getId(): string { return $this->id; }
    public function getTenantId(): string { return $this->tenantId; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): void { $this->email = $email; }
    public function getUserIdentifier(): string { return $this->email; }
    public function getRoles(): array
    {
        $roles = ['ROLE_EMPLEADO', ...$this->rolesLegacy];
        foreach ($this->roles as $asignacion) {
            $codigo = $asignacion->getRol()->getCodigo();
            $roles[] = match ($codigo) {
                'owner_tenant' => 'ROLE_ADMIN',
                'gestor_rrhh', 'responsable_equipo' => 'ROLE_SUPERVISOR',
                'trabajador' => 'ROLE_EMPLEADO',
                default => 'ROLE_EMPLEADO',
            };
        }

        return array_values(array_unique($roles));
    }

    /** @return string[] */
    public function getCodigosRolTenant(): array
    {
        $codigos = array_map(
            static fn (UsuarioRol $asignacion): string => $asignacion->getRol()->getCodigo(),
            $this->roles->toArray()
        );

        foreach ($this->rolesLegacy as $rolLegacy) {
            $codigos[] = match ($rolLegacy) {
                'ROLE_ADMIN' => 'owner_tenant',
                'ROLE_SUPERVISOR' => 'gestor_rrhh',
                'ROLE_EMPLEADO' => 'trabajador',
                default => '',
            };
        }

        return array_values(array_filter(array_unique($codigos), static fn (string $codigo): bool => $codigo !== ''));
    }

    public function tieneRolesTenantExplicitos(): bool
    {
        return !$this->roles->isEmpty();
    }

    public function estaActivo(): bool { return $this->activo; }
    public function activar(): void { $this->activo = true; }
    public function desactivar(): void { $this->activo = false; }
    public function getIntentosWebFallidos(): int { return $this->intentosWebFallidos; }
    public function getBloqueadoHasta(): ?\DateTimeImmutable { return $this->bloqueadoHasta; }
    public function incrementarIntentoWebFallido(int $maxIntentos, int $segundosBloqueo): void
    {
        $this->intentosWebFallidos++;
        if ($this->intentosWebFallidos >= $maxIntentos) {
            $this->bloqueadoHasta = (new \DateTimeImmutable())->modify(sprintf('+%d seconds', $segundosBloqueo));
            $this->intentosWebFallidos = 0;
        }
    }
    public function limpiarIntentosWeb(): void { $this->intentosWebFallidos = 0; $this->bloqueadoHasta = null; }
    public function eraseCredentials(): void {}
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): void { $this->password = $password; $this->passwordActualizadoEn = new \DateTimeImmutable(); }
}
