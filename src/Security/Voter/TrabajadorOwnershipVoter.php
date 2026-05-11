<?php

namespace App\Security\Voter;

use App\Modulo\Acceso\Application\Servicio\ResolverPermisoRol;
use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Trabajadores\Domain\Entity\Trabajador;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class TrabajadorOwnershipVoter extends Voter
{
    public const VER = 'TRABAJADOR_VER';
    public const GESTIONAR = 'TRABAJADOR_GESTIONAR';

    public function __construct(private readonly ResolverPermisoRol $resolverPermisoRol) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VER, self::GESTIONAR], true) && $subject instanceof Trabajador;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $usuario = $token->getUser();
        if (!$usuario instanceof Usuario || !$subject instanceof Trabajador) {
            return false;
        }
        if ($usuario->getTenantId() !== $subject->getTenantId()) {
            return false;
        }

        $rolesTenant = $usuario->getCodigosRolTenant();
        $permiso = $attribute === self::GESTIONAR ? 'trabajadores.editar' : 'trabajadores.ver';
        $esTrabajador = in_array('trabajador', $rolesTenant, true);
        if ($this->resolverPermisoRol->puede($permiso, $rolesTenant) && !$esTrabajador) {
            return true;
        }

        return $esTrabajador
            && $subject->getUsuarioId() !== null
            && $subject->getUsuarioId() === $usuario->getId();
    }
}
