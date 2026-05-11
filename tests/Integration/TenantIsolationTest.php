<?php

namespace App\Tests\Integration;

use App\Modulo\Ausencias\Application\Servicio\SolicitarAusencia;
use App\Modulo\Ausencias\Domain\Entity\SolicitudAusencia;
use App\Modulo\Ausencias\Infrastructure\Repository\SolicitudAusenciaRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class TenantIsolationTest extends TestCase
{
    public function testIdempotenciaSeAislaPorTenant(): void
    {
        $repo = $this->createMock(SolicitudAusenciaRepository::class);
        $servicio = new SolicitarAusencia($repo);

        $guardadas = [];
        $repo->method('buscarPorIdempotencia')->willReturnCallback(static function (string $tenantId, string $key) use (&$guardadas) {
            return $guardadas[$tenantId . ':' . $key] ?? null;
        });
        $repo->method('guardar')->willReturnCallback(static function (SolicitudAusencia $solicitud) use (&$guardadas): void {
            $guardadas[$solicitud->getTenantId() . ':' . $solicitud->getIdempotencyKey()] = $solicitud;
        });

        $a = $servicio->ejecutar('T1', 'E1', 'vacaciones', new DateTimeImmutable('2026-05-10'), new DateTimeImmutable('2026-05-12'), 'A1');
        $b = $servicio->ejecutar('T2', 'E1', 'vacaciones', new DateTimeImmutable('2026-05-10'), new DateTimeImmutable('2026-05-12'), 'A1');

        self::assertNotSame($a->getId(), $b->getId());
    }
}
