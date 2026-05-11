<?php

namespace App\Tests\Unit\Fichajes;

use App\Modulo\Auditoria\Application\Servicio\RegistrarAuditoria;
use App\Modulo\Fichajes\Application\Contract\ProveedorAusenciaAprobada;
use App\Modulo\Fichajes\Application\Contract\ProveedorHorarioVigente;
use App\Modulo\Fichajes\Application\Servicio\RegistrarEventoFichaje;
use App\Modulo\Fichajes\Application\Servicio\ValidadorTransicionFichaje;
use App\Modulo\Fichajes\Domain\Entity\EventoFichaje;
use App\Modulo\Fichajes\Infrastructure\Repository\EventoFichajeRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class RegistrarEventoFichajeTest extends TestCase
{
    public function testReintentoIdempotenteDevuelveMismoEvento(): void
    {
        $evento = new EventoFichaje('EV1', 'T1', 'E1', 'clock-in', new DateTimeImmutable('2026-05-08T09:00:00+00:00'), 'dentro_horario', null, 'K1', hash('sha256', json_encode([
            'empleadoId' => 'E1',
            'tipo' => 'clock-in',
            'ocurridoEn' => '2026-05-08T09:00:00+00:00',
            'politica' => 'bloquear',
        ], JSON_THROW_ON_ERROR)));

        $repo = $this->createMock(EventoFichajeRepository::class);
        $repo->method('buscarPorIdempotencia')->willReturn($evento);
        $repo->expects(self::never())->method('guardar');
        $repo->method('ultimoEventoDelDia')->willReturn(null);

        $horario = $this->createMock(ProveedorHorarioVigente::class);
        $horario->method('estaDentroDeHorario')->willReturn(true);
        $ausencia = $this->createMock(ProveedorAusenciaAprobada::class);
        $ausencia->method('tieneAusenciaAprobada')->willReturn(false);
        $auditoria = $this->createMock(RegistrarAuditoria::class);

        $servicio = new RegistrarEventoFichaje($repo, $horario, $ausencia, $auditoria, new ValidadorTransicionFichaje());
        $resultado = $servicio->ejecutar('T1', 'E1', 'clock-in', new DateTimeImmutable('2026-05-08T09:00:00+00:00'), 'bloquear', 'K1');

        self::assertSame('EV1', $resultado->getId());
    }

    public function testRechazaTransicionInvalida(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('TRANSICION_INVALIDA');

        $repo = $this->createMock(EventoFichajeRepository::class);
        $repo->method('buscarPorIdempotencia')->willReturn(null);
        $repo->method('ultimoEventoDelDia')->willReturn(null);
        $horario = $this->createMock(ProveedorHorarioVigente::class);
        $horario->method('estaDentroDeHorario')->willReturn(true);
        $ausencia = $this->createMock(ProveedorAusenciaAprobada::class);
        $ausencia->method('tieneAusenciaAprobada')->willReturn(false);
        $auditoria = $this->createMock(RegistrarAuditoria::class);

        $servicio = new RegistrarEventoFichaje($repo, $horario, $ausencia, $auditoria, new ValidadorTransicionFichaje());
        $servicio->ejecutar('T1', 'E1', 'clock-out', new DateTimeImmutable('2026-05-08T18:00:00+00:00'));
    }
}
