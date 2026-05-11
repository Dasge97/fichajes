<?php

namespace App\Security\Subscriber;

use App\Modulo\Acceso\Application\Servicio\GestorBloqueoLoginWeb;
use App\Modulo\Acceso\Domain\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginWebSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GestorBloqueoLoginWeb $bloqueoLoginWeb
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LoginFailureEvent::class => 'onLoginFailure',
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $email = trim((string) ($request?->request->get('_username') ?? $request?->request->get('email') ?? ''));
        if ($email === '') {
            return;
        }
        $usuario = $this->entityManager->getRepository(Usuario::class)->findOneBy(['email' => $email]);
        if (!$usuario instanceof Usuario) {
            return;
        }
        $ip = (string) ($request?->getClientIp() ?? '0.0.0.0');

        $usuario->incrementarIntentoWebFallido(5, 900);
        $this->entityManager->flush();
        $this->bloqueoLoginWeb->registrarFallo($usuario->getTenantId(), $usuario->getEmail(), $ip, 5, 900);
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $usuario = $event->getUser();
        if (!$usuario instanceof Usuario) {
            return;
        }
        $request = $event->getRequest();
        $ip = (string) ($request?->getClientIp() ?? '0.0.0.0');
        $usuario->limpiarIntentosWeb();
        $this->entityManager->flush();
        $this->bloqueoLoginWeb->limpiar($usuario->getTenantId(), $usuario->getEmail(), $ip);
    }
}
