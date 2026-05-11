<?php

namespace App\Modulo\Fichajes\Application\Servicio;

use App\Modulo\Auditoria\Application\Servicio\RegistrarAuditoria;
use App\Modulo\Fichajes\Infrastructure\Repository\IntentoAccesoHerramientaRepository;
use DateTimeImmutable;

class ControlAccesoHerramientaFichaje
{
    public function __construct(
        private readonly IntentoAccesoHerramientaRepository $intentos,
        private readonly RegistrarAuditoria $auditoria,
        private readonly int $limiteIntentos,
        private readonly int $ventanaSegundos,
        private readonly int $bloqueoSegundos
    ) {}

    public function estaBloqueado(string $trabajadorId, string $ip): ?DateTimeImmutable
    {
        $registro = $this->intentos->buscarPorTrabajadorEIp($trabajadorId, $this->resolverIpHash($ip));
        if ($registro === null) {
            return null;
        }

        $ahora = new DateTimeImmutable();

        return $registro->estaBloqueado($ahora) ? $registro->getBloqueadoHasta() : null;
    }

    public function registrarFallo(string $trabajadorId, string $ip): ?DateTimeImmutable
    {
        $ahora = new DateTimeImmutable();
        $registro = $this->intentos->obtenerOCrear($trabajadorId, $this->resolverIpHash($ip), $ahora);
        $bloqueadoAntes = $registro->estaBloqueado($ahora);
        $registro->registrarFallo($ahora, $this->limiteIntentos, $this->ventanaSegundos, $this->bloqueoSegundos);
        $this->intentos->guardar();

        $bloqueadoHasta = $registro->getBloqueadoHasta();
        $this->auditoria->registrar('PUBLIC', 'herramienta.identificacion_fallida', null, [
            'trabajadorId' => $trabajadorId,
            'ipHash' => $this->resolverIpHash($ip),
            'fallosAcumulados' => $registro->getFallosAcumulados(),
        ]);

        if (!$bloqueadoAntes && $bloqueadoHasta !== null && $bloqueadoHasta > $ahora) {
            $this->auditoria->registrar('PUBLIC', 'herramienta.bloqueo_activado', null, [
                'trabajadorId' => $trabajadorId,
                'ipHash' => $this->resolverIpHash($ip),
                'bloqueadoHasta' => $bloqueadoHasta->format(DATE_ATOM),
            ]);
        }

        return $bloqueadoHasta !== null && $bloqueadoHasta > $ahora ? $bloqueadoHasta : null;
    }

    public function limpiarIntentos(string $trabajadorId, string $ip): void
    {
        $registro = $this->intentos->buscarPorTrabajadorEIp($trabajadorId, $this->resolverIpHash($ip));
        if ($registro === null) {
            return;
        }

        $this->intentos->eliminar($registro);
    }

    private function resolverIpHash(string $ip): string
    {
        return hash('sha256', trim($ip) !== '' ? $ip : 'ip-desconocida');
    }
}
