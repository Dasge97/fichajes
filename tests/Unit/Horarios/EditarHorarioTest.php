<?php

namespace App\Tests\Unit\Horarios;

use App\Modulo\Horarios\Application\Servicio\EditarHorario;
use App\Modulo\Horarios\Domain\Entity\HorarioTrabajo;
use App\Modulo\Horarios\Infrastructure\Repository\HorarioTrabajoRepository;
use PHPUnit\Framework\TestCase;

class EditarHorarioTest extends TestCase
{
    public function testEditaHorarioEnTenantActivo(): void
    {
        $repo = $this->createMock(HorarioTrabajoRepository::class);
        $servicio = new EditarHorario($repo);

        $horario = new HorarioTrabajo('H1', 'T1', 'Turno A', [['dia' => 1, 'inicio' => '09:00', 'fin' => '17:00']]);

        $repo->method('buscarPorIdYTenant')->with('H1', 'T1')->willReturn($horario);
        $repo->expects($this->once())->method('guardar')->with($horario);

        $actualizado = $servicio->ejecutar('T1', 'H1', 'Turno B', [['dia' => 1, 'inicio' => '08:00', 'fin' => '16:00']]);

        self::assertSame('Turno B', $actualizado->getNombre());
    }

    public function testRechazaEdicionDeTenantDistinto(): void
    {
        $repo = $this->createMock(HorarioTrabajoRepository::class);
        $servicio = new EditarHorario($repo);

        $repo->method('buscarPorIdYTenant')->with('H1', 'T2')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('HORARIO_NO_ENCONTRADO');
        $servicio->ejecutar('T2', 'H1', 'Turno B', [['dia' => 1, 'inicio' => '08:00', 'fin' => '16:00']]);
    }
}
