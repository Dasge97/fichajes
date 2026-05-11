<?php

namespace App\Tests\Unit\Horarios;

use App\Modulo\Horarios\Domain\Entity\AsignacionHorarioEmpleado;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class AsignacionHorarioEmpleadoTest extends TestCase
{
    public function testNoPermiteSolapeInverso(): void
    {
        $this->expectException(\DomainException::class);
        new AsignacionHorarioEmpleado('1', 'T1', 'E1', 'H1', new DateTimeImmutable('2026-05-10'), new DateTimeImmutable('2026-05-01'));
    }
}
