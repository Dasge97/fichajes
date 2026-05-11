<?php

namespace App\Tests\Unit\Ausencias;

use App\Modulo\Auditoria\Application\Servicio\RegistrarAuditoria;
use App\Modulo\Ausencias\Application\Servicio\AprobarAusencia;
use App\Modulo\Ausencias\Domain\Entity\SolicitudAusencia;
use App\Modulo\Ausencias\Infrastructure\Repository\SolicitudAusenciaRepository;
use PHPUnit\Framework\TestCase;

class AprobarAusenciaTenantTest extends TestCase
{
    public function testRechazaAprobacionConTenantDistinto(): void
    {
        $repo = $this->createMock(SolicitudAusenciaRepository::class);
        $solicitud = new SolicitudAusencia('A1', 'T1', 'EMP-1', 'vacaciones', new \DateTimeImmutable('2026-05-10'), new \DateTimeImmutable('2026-05-11'));
        $repo->method('buscarPorId')->willReturn($solicitud);

        $auditoria = $this->createMock(RegistrarAuditoria::class);
        $auditoria->expects(self::never())->method('registrar');

        $servicio = new AprobarAusencia($repo, $auditoria);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('TENANT_MISMATCH');
        $servicio->ejecutar('T2', 'A1');
    }
}
