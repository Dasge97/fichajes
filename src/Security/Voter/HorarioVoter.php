<?php

namespace App\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class HorarioVoter extends Voter
{
    public const GESTIONAR = 'HORARIO_GESTIONAR';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::GESTIONAR;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $usuario = $token->getUser();
        if (!is_object($usuario) || !method_exists($usuario, 'getRoles')) {
            return false;
        }

        return in_array('ROLE_SUPERVISOR', $usuario->getRoles(), true) || in_array('ROLE_ADMIN', $usuario->getRoles(), true);
    }
}
