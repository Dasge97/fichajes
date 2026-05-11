<?php

namespace App\Tests\Api;

use App\Controller\Api\V1\HorariosController;
use App\Modulo\Horarios\Application\Servicio\EditarHorario;
use App\Modulo\Plataforma\Application\Tenant\TenantContexto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class HorariosApiTest extends TestCase
{
    public function testDevuelve403EnTenantNoResueltoAlEditar(): void
    {
        $tenant = $this->createMock(TenantContexto::class);
        $tenant->method('obtenerTenantId')->willThrowException(new \DomainException('TENANT_NO_RESUELTO'));

        $controller = new HorariosController($tenant);

        $servicio = $this->createMock(EditarHorario::class);

        $request = new Request(content: json_encode([
            'nombre' => 'Turno B',
            'tramos' => [['dia' => 1, 'inicio' => '08:00', 'fin' => '16:00']],
        ], JSON_THROW_ON_ERROR));

        $response = $controller->editarPlantilla('H1', $request, $servicio);

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('TENANT_NO_RESUELTO', (string) $response->getContent());
    }
}
