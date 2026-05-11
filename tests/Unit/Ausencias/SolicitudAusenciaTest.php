<?php

namespace App\Tests\Unit\Ausencias;

use App\Modulo\Ausencias\Domain\Entity\SolicitudAusencia;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class SolicitudAusenciaTest extends TestCase
{
    public function testNoPermitePeriodoInvalido(): void
    {
        $this->expectException(\DomainException::class);
        new SolicitudAusencia('1', 'T1', 'E1', 'vacaciones', new DateTimeImmutable('2026-05-10'), new DateTimeImmutable('2026-05-01'), null, null);
    }
}
