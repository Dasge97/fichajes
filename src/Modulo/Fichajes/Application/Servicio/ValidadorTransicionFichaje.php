<?php

namespace App\Modulo\Fichajes\Application\Servicio;

class ValidadorTransicionFichaje
{
    public function validar(?string $ultimoTipo, string $nuevoTipo): void
    {
        $transiciones = [
            null => ['clock-in'],
            'clock-in' => ['pause-start', 'clock-out'],
            'pause-start' => ['pause-end'],
            'pause-end' => ['pause-start', 'clock-out'],
            'clock-out' => ['clock-in'],
        ];

        $permitidos = $transiciones[$ultimoTipo] ?? [];
        if (!in_array($nuevoTipo, $permitidos, true)) {
            throw new \DomainException('TRANSICION_INVALIDA');
        }
    }
}
