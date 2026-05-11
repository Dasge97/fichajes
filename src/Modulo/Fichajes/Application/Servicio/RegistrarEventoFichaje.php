<?php

namespace App\Modulo\Fichajes\Application\Servicio;

use App\Modulo\Auditoria\Application\Servicio\RegistrarAuditoria;
use App\Modulo\Acceso\Application\Servicio\ValidarOwnershipTrabajador;
use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Fichajes\Application\Contract\ProveedorAusenciaAprobada;
use App\Modulo\Fichajes\Application\Contract\ProveedorHorarioVigente;
use App\Modulo\Fichajes\Domain\Entity\EventoFichaje;
use App\Modulo\Fichajes\Infrastructure\Repository\EventoFichajeRepository;
use DateTimeImmutable;

class RegistrarEventoFichaje
{
    public function __construct(
        private readonly EventoFichajeRepository $eventos,
        private readonly ProveedorHorarioVigente $proveedorHorario,
        private readonly ProveedorAusenciaAprobada $proveedorAusencia,
        private readonly RegistrarAuditoria $auditoria,
        private readonly ValidadorTransicionFichaje $validadorTransicion,
        private readonly ValidarOwnershipTrabajador $validarOwnershipTrabajador
    ) {}

    public function ejecutar(string $tenantId, string $empleadoId, string $tipo, DateTimeImmutable $ocurridoEn, string $politicaTenant = 'bloquear', ?string $idempotencyKey = null, ?Usuario $usuario = null): EventoFichaje
    {
        if ($usuario instanceof Usuario) {
            $this->validarOwnershipTrabajador->validar($usuario, $tenantId, $empleadoId, 'fichajes.registrar.propio');
        }

        $hash = hash('sha256', json_encode([
            'empleadoId' => $empleadoId,
            'tipo' => $tipo,
            'ocurridoEn' => $ocurridoEn->format(DATE_ATOM),
            'politica' => $politicaTenant,
        ], JSON_THROW_ON_ERROR));

        if ($idempotencyKey !== null) {
            $existente = $this->eventos->buscarPorIdempotencia($tenantId, $idempotencyKey);
            if ($existente !== null) {
                if ($existente->getPayloadHash() !== $hash) {
                    throw new \DomainException('IDEMPOTENCY_CONFLICT');
                }

                return $existente;
            }
        }

        $ultimo = $this->eventos->ultimoEventoDelDia($tenantId, $empleadoId, $ocurridoEn);
        $this->validadorTransicion->validar($ultimo?->getTipo(), $tipo);

        $estado = 'dentro_horario';
        $motivo = null;

        if (!$this->proveedorHorario->estaDentroDeHorario($tenantId, $empleadoId, $ocurridoEn)) {
            if ($politicaTenant === 'bloquear') {
                throw new \DomainException('FUERA_DE_HORARIO');
            }
            $estado = 'fuera_horario';
            $motivo = 'FUERA_DE_HORARIO';
        }

        if ($this->proveedorAusencia->tieneAusenciaAprobada($tenantId, $empleadoId, $ocurridoEn)) {
            if ($politicaTenant === 'bloquear') {
                throw new \DomainException('EN_AUSENCIA');
            }
            $estado = 'en_ausencia';
            $motivo = 'EN_AUSENCIA';
        }

        $evento = new EventoFichaje(bin2hex(random_bytes(16)), $tenantId, $empleadoId, $tipo, $ocurridoEn, $estado, $motivo, $idempotencyKey, $hash);
        $this->eventos->guardar($evento);

        $this->auditoria->registrar($tenantId, 'fichaje.registrado', null, ['tipo' => $tipo, 'estado' => $estado]);

        return $evento;
    }
}
