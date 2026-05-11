<?php

namespace App\Security;

use App\Modulo\Acceso\Application\Servicio\GestorBloqueoLoginWeb;
use App\Modulo\Acceso\Domain\Entity\Usuario;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UsuarioWebChecker implements UserCheckerInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly GestorBloqueoLoginWeb $bloqueoLoginWeb
    ) {}

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Usuario) {
            return;
        }
        if (!$user->estaActivo()) {
            throw new CustomUserMessageAccountStatusException('CUENTA_DESACTIVADA');
        }
        $bloqueadoHasta = $user->getBloqueadoHasta();
        if ($bloqueadoHasta !== null && $bloqueadoHasta > new \DateTimeImmutable()) {
            throw new CustomUserMessageAccountStatusException('CUENTA_BLOQUEADA_TEMPORALMENTE');
        }

        $ip = (string) ($this->requestStack->getCurrentRequest()?->getClientIp() ?? '0.0.0.0');
        if ($this->bloqueoLoginWeb->estaBloqueado($user->getTenantId(), $user->getEmail(), $ip)) {
            throw new CustomUserMessageAccountStatusException('IP_BLOQUEADA_TEMPORALMENTE');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
