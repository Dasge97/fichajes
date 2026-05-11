<?php

namespace App\Modulo\Plataforma\Application\Tenant;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class TenantContexto
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly TokenStorageInterface $tokenStorage
    ) {}

    public function obtenerTenantId(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $tenantCabecera = trim((string) ($request?->headers->get('X-Tenant-Id') ?? ''));

        $token = $this->tokenStorage->getToken();
        $usuario = $token?->getUser();

        $tenantUsuario = '';
        if (is_object($usuario) && method_exists($usuario, 'getTenantId')) {
            $tenantUsuario = trim((string) $usuario->getTenantId());
        }

        if ($tenantUsuario !== '' && $tenantCabecera !== '' && $tenantUsuario !== $tenantCabecera) {
            throw new \DomainException('TENANT_MISMATCH');
        }

        if ($tenantUsuario !== '') {
            return $tenantUsuario;
        }

        if ($tenantCabecera !== '') {
            return $tenantCabecera;
        }

        throw new \DomainException('TENANT_NO_RESUELTO');
    }
}
