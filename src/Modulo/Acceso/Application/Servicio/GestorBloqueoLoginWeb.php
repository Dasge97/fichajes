<?php

namespace App\Modulo\Acceso\Application\Servicio;

use Doctrine\DBAL\Connection;

class GestorBloqueoLoginWeb
{
    public function __construct(private readonly Connection $connection) {}

    public function registrarFallo(string $tenantId, string $email, string $ip, int $maxIntentos = 5, int $segundosBloqueo = 900): void
    {
        $fila = $this->connection->fetchAssociative(
            'SELECT intentos, bloqueado_hasta FROM login_bloqueo_web WHERE tenant_id = :tenant AND email = :email AND ip = :ip',
            ['tenant' => $tenantId, 'email' => $email, 'ip' => $ip]
        );

        $ahora = new \DateTimeImmutable();
        $intentos = ((int) ($fila['intentos'] ?? 0)) + 1;
        $bloqueadoHasta = $fila['bloqueado_hasta'] ?? null;
        if ($bloqueadoHasta !== null && new \DateTimeImmutable($bloqueadoHasta) > $ahora) {
            $intentos = $maxIntentos;
        }

        $nuevoBloqueo = null;
        if ($intentos >= $maxIntentos) {
            $nuevoBloqueo = $ahora->modify(sprintf('+%d seconds', $segundosBloqueo))->format('Y-m-d H:i:s');
            $intentos = 0;
        }

        if ($fila !== false) {
            $this->connection->update('login_bloqueo_web', [
                'intentos' => $intentos,
                'bloqueado_hasta' => $nuevoBloqueo,
                'actualizado_en' => $ahora->format('Y-m-d H:i:s'),
            ], [
                'tenant_id' => $tenantId,
                'email' => $email,
                'ip' => $ip,
            ]);

            return;
        }

        $this->connection->insert('login_bloqueo_web', [
            'id' => bin2hex(random_bytes(16)),
            'tenant_id' => $tenantId,
            'email' => $email,
            'ip' => $ip,
            'intentos' => $intentos,
            'bloqueado_hasta' => $nuevoBloqueo,
            'actualizado_en' => $ahora->format('Y-m-d H:i:s'),
        ]);
    }

    public function limpiar(string $tenantId, string $email, string $ip): void
    {
        $this->connection->delete('login_bloqueo_web', [
            'tenant_id' => $tenantId,
            'email' => $email,
            'ip' => $ip,
        ]);
    }

    public function estaBloqueado(string $tenantId, string $email, string $ip): bool
    {
        $bloqueadoHasta = $this->connection->fetchOne(
            'SELECT bloqueado_hasta FROM login_bloqueo_web WHERE tenant_id = :tenant AND email = :email AND ip = :ip',
            ['tenant' => $tenantId, 'email' => $email, 'ip' => $ip]
        );
        if ($bloqueadoHasta === false || $bloqueadoHasta === null || $bloqueadoHasta === '') {
            return false;
        }

        return new \DateTimeImmutable((string) $bloqueadoHasta) > new \DateTimeImmutable();
    }
}
