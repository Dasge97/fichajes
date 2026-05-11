<?php

namespace App\Command;

use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:cargar-demo', description: 'Carga datos demo pasados y futuros para dashboard y modulos')]
class CargarDatosDemoCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenantId = 'T1';
        $empleadoA = 'EMP-001';
        $empleadoB = 'EMP-002';
        $ahora = new DateTimeImmutable();

        $this->connection->beginTransaction();
        try {
            $this->limpiarDemo($tenantId);

            $this->insertarTrabajadores($tenantId, $empleadoA, $empleadoB, $ahora);
            $this->insertarHorarios($tenantId);
            $this->insertarAsignaciones($tenantId, $empleadoA, $empleadoB, $ahora);
            $this->insertarAusencias($tenantId, $empleadoA, $empleadoB, $ahora);
            $this->insertarFichajes($tenantId, $empleadoA, $empleadoB, $ahora);
            $this->insertarCorrecciones($tenantId, $ahora);
            $this->insertarAuditoria($tenantId, $ahora);

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            $output->writeln('Error cargando demo: '.$e->getMessage());

            return Command::FAILURE;
        }

        $output->writeln('Datos demo cargados correctamente.');

        return Command::SUCCESS;
    }

    private function limpiarDemo(string $tenantId): void
    {
        foreach (['asignacion_horario_empleado', 'correccion_fichaje', 'evento_fichaje', 'horario_trabajo', 'solicitud_ausencia', 'trabajador', 'registro_auditoria'] as $tabla) {
            $this->connection->executeStatement("DELETE FROM {$tabla} WHERE tenantId = :tenant AND id LIKE 'demo-%'", ['tenant' => $tenantId]);
        }
    }

    private function insertarTrabajadores(string $tenantId, string $empleadoA, string $empleadoB, DateTimeImmutable $ahora): void
    {
        $this->connection->insert('trabajador', [
            'id' => 'demo-trab-1',
            'tenantId' => $tenantId,
            'trabajadorId' => $empleadoA,
            'nombre' => 'Ana Torres',
            'email' => 'ana.torres@demo.local',
            'activo' => 1,
            'fechaAlta' => $ahora->sub(new DateInterval('P180D'))->format('Y-m-d H:i:s'),
            'claveAccesoHash' => password_hash('1111', PASSWORD_DEFAULT),
        ]);

        $this->connection->insert('trabajador', [
            'id' => 'demo-trab-2',
            'tenantId' => $tenantId,
            'trabajadorId' => $empleadoB,
            'nombre' => 'Bruno Garcia',
            'email' => 'bruno.garcia@demo.local',
            'activo' => 1,
            'fechaAlta' => $ahora->sub(new DateInterval('P120D'))->format('Y-m-d H:i:s'),
            'claveAccesoHash' => password_hash('2222', PASSWORD_DEFAULT),
        ]);
    }

    private function insertarHorarios(string $tenantId): void
    {
        $this->connection->insert('horario_trabajo', [
            'id' => 'demo-horario-mañana',
            'tenantId' => $tenantId,
            'nombre' => 'Turno mañana',
            'tramos' => json_encode([
                ['dia' => 1, 'inicio' => '09:00', 'fin' => '17:00'],
                ['dia' => 2, 'inicio' => '09:00', 'fin' => '17:00'],
                ['dia' => 3, 'inicio' => '09:00', 'fin' => '17:00'],
                ['dia' => 4, 'inicio' => '09:00', 'fin' => '17:00'],
                ['dia' => 5, 'inicio' => '09:00', 'fin' => '17:00'],
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->connection->insert('horario_trabajo', [
            'id' => 'demo-horario-tarde',
            'tenantId' => $tenantId,
            'nombre' => 'Turno tarde',
            'tramos' => json_encode([
                ['dia' => 1, 'inicio' => '13:00', 'fin' => '21:00'],
                ['dia' => 2, 'inicio' => '13:00', 'fin' => '21:00'],
                ['dia' => 3, 'inicio' => '13:00', 'fin' => '21:00'],
                ['dia' => 4, 'inicio' => '13:00', 'fin' => '21:00'],
                ['dia' => 5, 'inicio' => '13:00', 'fin' => '21:00'],
            ], JSON_THROW_ON_ERROR),
        ]);
    }

    private function insertarAsignaciones(string $tenantId, string $empleadoA, string $empleadoB, DateTimeImmutable $ahora): void
    {
        $this->connection->insert('asignacion_horario_empleado', [
            'id' => 'demo-asig-1',
            'tenantId' => $tenantId,
            'empleadoId' => $empleadoA,
            'horarioId' => 'demo-horario-mañana',
            'vigenteDesde' => $ahora->sub(new DateInterval('P30D'))->format('Y-m-d H:i:s'),
            'vigenteHasta' => null,
        ]);

        $this->connection->insert('asignacion_horario_empleado', [
            'id' => 'demo-asig-2',
            'tenantId' => $tenantId,
            'empleadoId' => $empleadoB,
            'horarioId' => 'demo-horario-tarde',
            'vigenteDesde' => $ahora->sub(new DateInterval('P15D'))->format('Y-m-d H:i:s'),
            'vigenteHasta' => $ahora->add(new DateInterval('P60D'))->format('Y-m-d H:i:s'),
        ]);
    }

    private function insertarAusencias(string $tenantId, string $empleadoA, string $empleadoB, DateTimeImmutable $ahora): void
    {
        $this->connection->insert('solicitud_ausencia', [
            'id' => 'demo-aus-1',
            'tenantId' => $tenantId,
            'empleadoId' => $empleadoA,
            'tipo' => 'vacaciones',
            'fechaInicio' => $ahora->sub(new DateInterval('P10D'))->format('Y-m-d'),
            'fechaFin' => $ahora->sub(new DateInterval('P8D'))->format('Y-m-d'),
            'estado' => 'aprobada',
            'idempotencyKey' => 'demo-aus-1',
            'payloadHash' => 'demo-hash',
        ]);

        $this->connection->insert('solicitud_ausencia', [
            'id' => 'demo-aus-2',
            'tenantId' => $tenantId,
            'empleadoId' => $empleadoB,
            'tipo' => 'permiso',
            'fechaInicio' => $ahora->add(new DateInterval('P3D'))->format('Y-m-d'),
            'fechaFin' => $ahora->add(new DateInterval('P4D'))->format('Y-m-d'),
            'estado' => 'pendiente',
            'idempotencyKey' => 'demo-aus-2',
            'payloadHash' => 'demo-hash',
        ]);

        $this->connection->insert('solicitud_ausencia', [
            'id' => 'demo-aus-3',
            'tenantId' => $tenantId,
            'empleadoId' => $empleadoA,
            'tipo' => 'baja',
            'fechaInicio' => $ahora->add(new DateInterval('P9D'))->format('Y-m-d'),
            'fechaFin' => $ahora->add(new DateInterval('P10D'))->format('Y-m-d'),
            'estado' => 'aprobada',
            'idempotencyKey' => 'demo-aus-3',
            'payloadHash' => 'demo-hash',
        ]);
    }

    private function insertarFichajes(string $tenantId, string $empleadoA, string $empleadoB, DateTimeImmutable $ahora): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $base = $ahora->sub(new DateInterval('P'.$i.'D'));

            $this->insertarEvento($tenantId, "demo-ev-a-in-{$i}", $empleadoA, 'clock-in', $base->setTime(9, 2), 'dentro_horario', null);
            $this->insertarEvento($tenantId, "demo-ev-a-out-{$i}", $empleadoA, 'clock-out', $base->setTime(17, 6), 'dentro_horario', null);

            $estado = $i % 4 === 0 ? 'fuera_horario' : 'dentro_horario';
            $motivo = $estado === 'fuera_horario' ? 'llegada_tardia' : null;
            $this->insertarEvento($tenantId, "demo-ev-b-in-{$i}", $empleadoB, 'clock-in', $base->setTime(13, $i % 2 === 0 ? 20 : 2), $estado, $motivo);
            $this->insertarEvento($tenantId, "demo-ev-b-out-{$i}", $empleadoB, 'clock-out', $base->setTime(21, 0), 'dentro_horario', null);
        }

        $this->insertarEvento($tenantId, 'demo-ev-hoy-1', $empleadoA, 'clock-in', $ahora->setTime(9, 1), 'dentro_horario', null);
        $this->insertarEvento($tenantId, 'demo-ev-hoy-2', $empleadoB, 'clock-in', $ahora->setTime(13, 22), 'fuera_horario', 'llegada_tardia');
    }

    private function insertarEvento(string $tenantId, string $id, string $empleadoId, string $tipo, DateTimeImmutable $ocurridoEn, string $estado, ?string $motivo): void
    {
        $this->connection->insert('evento_fichaje', [
            'id' => $id,
            'tenantId' => $tenantId,
            'empleadoId' => $empleadoId,
            'tipo' => $tipo,
            'ocurridoEn' => $ocurridoEn->format('Y-m-d H:i:s'),
            'estadoCumplimiento' => $estado,
            'motivoDesvio' => $motivo,
            'idempotencyKey' => $id,
            'payloadHash' => 'demo-hash',
        ]);
    }

    private function insertarCorrecciones(string $tenantId, DateTimeImmutable $ahora): void
    {
        $this->connection->insert('correccion_fichaje', [
            'id' => 'demo-cor-1',
            'tenantId' => $tenantId,
            'eventoFichajeId' => 'demo-ev-b-in-4',
            'estado' => 'pendiente',
            'ocurridoEnCorregido' => $ahora->sub(new DateInterval('P4D'))->setTime(13, 0)->format('Y-m-d H:i:s'),
            'tipoCorregido' => 'clock-in',
            'motivo' => 'Error de fichaje en entrada',
            'evidencia' => null,
            'eventoAplicadoId' => null,
        ]);

        $this->connection->insert('correccion_fichaje', [
            'id' => 'demo-cor-2',
            'tenantId' => $tenantId,
            'eventoFichajeId' => 'demo-ev-a-out-6',
            'estado' => 'aprobada',
            'ocurridoEnCorregido' => $ahora->sub(new DateInterval('P6D'))->setTime(17, 0)->format('Y-m-d H:i:s'),
            'tipoCorregido' => 'clock-out',
            'motivo' => 'Ajuste aprobado por supervisor',
            'evidencia' => null,
            'eventoAplicadoId' => 'demo-ev-ajuste-1',
        ]);
    }

    private function insertarAuditoria(string $tenantId, DateTimeImmutable $ahora): void
    {
        $registros = [
            ['demo-aud-1', 'horario.creado', '-1 day'],
            ['demo-aud-2', 'fichaje.registrado', '-8 hours'],
            ['demo-aud-3', 'ausencia.solicitada', '-3 hours'],
            ['demo-aud-4', 'correccion.solicitada', '-2 hours'],
            ['demo-aud-5', 'ausencia.aprobada', '+2 days'],
        ];

        foreach ($registros as [$id, $accion, $delta]) {
            $this->connection->insert('registro_auditoria', [
                'id' => $id,
                'tenantId' => $tenantId,
                'accion' => $accion,
                'antes' => null,
                'despues' => '{"demo":true}',
                'creadoEn' => $ahora->modify($delta)->format('Y-m-d H:i:s'),
            ]);
        }
    }
}
