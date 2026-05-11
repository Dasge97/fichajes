<?php

namespace App\Twig;

use App\Modulo\Plataforma\Application\Tenant\TenantContexto;
use App\Modulo\Plataforma\Domain\Entity\AjusteTenant;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppConfigExtension extends AbstractExtension
{
    private ?array $cache = null;

    public function __construct(
        private readonly TenantContexto $tenantContexto,
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('appConfig', [$this, 'obtener']),
        ];
    }

    public function obtener(?string $clave = null, mixed $defecto = null): mixed
    {
        $datos = $this->cargar();
        if ($clave === null) {
            return $datos;
        }

        return $datos[$clave] ?? $defecto;
    }

    private function cargar(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        try {
            $tenantId = $this->tenantContexto->obtenerTenantId();
            $ajuste = $this->entityManager->find(AjusteTenant::class, $tenantId);
            $this->cache = $ajuste instanceof AjusteTenant ? $ajuste->getDatos() : [];
        } catch (\Throwable) {
            $this->cache = [];
        }

        return $this->cache;
    }
}
