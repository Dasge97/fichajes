<?php

namespace App\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AusenciaVoter extends Voter
{
    public const APROBAR = 'AUSENCIA_APROBAR';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::APROBAR;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $usuario = $token->getUser();
        return is_object($usuario) && method_exists($usuario, 'getRoles') && (in_array('ROLE_SUPERVISOR', $usuario->getRoles(), true) || in_array('ROLE_ADMIN', $usuario->getRoles(), true));
    }
}
