<?php

namespace App\DataFixtures;

use App\Modulo\Acceso\Domain\Entity\Rol;
use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Acceso\Domain\Entity\UsuarioRol;
use App\Modulo\Auditoria\Domain\Entity\RegistroAuditoria;
use App\Modulo\Ausencias\Domain\Entity\SolicitudAusencia;
use App\Modulo\Correcciones\Domain\Entity\CorreccionFichaje;
use App\Modulo\Fichajes\Domain\Entity\EventoFichaje;
use App\Modulo\Horarios\Domain\Entity\AsignacionHorarioEmpleado;
use App\Modulo\Horarios\Domain\Entity\HorarioTrabajo;
use App\Modulo\Trabajadores\Domain\Entity\Trabajador;
use DateInterval;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private const TENANT = 'T1';
    private Generator $faker;

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
        $this->faker = Factory::create('es_ES');
    }

    public function load(ObjectManager $manager): void
    {
        $roles       = $this->crearRoles($manager);
        $this->crearUsuarios($manager, $roles);
        $trabajadores = $this->crearTrabajadores($manager);
        $horarios    = $this->crearHorarios($manager);
        $asignaciones = $this->crearAsignaciones($manager, $trabajadores, $horarios);
        $fichajes    = $this->crearFichajes($manager, $trabajadores, $asignaciones);
        $this->crearAusencias($manager, $trabajadores);
        $this->crearCorrecciones($manager, $fichajes);
        $this->crearAuditoria($manager, $horarios);

        $manager->flush();
    }

    /** @return array<string, Rol> indexado por código */
    private function crearRoles(ObjectManager $manager): array
    {
        $definiciones = [
            'owner_tenant'       => ['Propietario del tenant',  'Acceso total a la empresa'],
            'gestor_rrhh'        => ['Gestor de RRHH',          'Gestión de trabajadores, horarios y ausencias'],
            'responsable_equipo' => ['Responsable de equipo',   'Supervisión de equipos de trabajo'],
            'trabajador'         => ['Trabajador',               'Acceso al kiosco de fichaje'],
        ];

        $roles = [];
        foreach ($definiciones as $codigo => [$nombre, $desc]) {
            $rol = new Rol($this->uuid(), $codigo, $nombre, $desc, true);
            $manager->persist($rol);
            $roles[$codigo] = $rol;
        }

        return $roles;
    }

    /** @param array<string, Rol> $roles */
    private function crearUsuarios(ObjectManager $manager, array $roles): void
    {
        $admin = new Usuario($this->uuid(), self::TENANT, 'admin@empresa.local', ['ROLE_ADMIN']);
        $admin->setPassword($this->hasher->hashPassword($admin, 'Admin123!'));
        $manager->persist($admin);
        $manager->persist(new UsuarioRol($this->uuid(), self::TENANT, $admin, $roles['owner_tenant'], null));

        $supervisor = new Usuario($this->uuid(), self::TENANT, 'supervisor@empresa.local', []);
        $supervisor->setPassword($this->hasher->hashPassword($supervisor, 'Super123!'));
        $manager->persist($supervisor);
        $manager->persist(new UsuarioRol($this->uuid(), self::TENANT, $supervisor, $roles['gestor_rrhh'], $admin->getId()));

        $empleado = new Usuario($this->uuid(), self::TENANT, 'maria.garcia@empresa.local', []);
        $empleado->setPassword($this->hasher->hashPassword($empleado, 'Empleado123!'));
        $manager->persist($empleado);
        $manager->persist(new UsuarioRol($this->uuid(), self::TENANT, $empleado, $roles['trabajador'], $admin->getId()));
    }

    /** @return array<string, Trabajador> indexado por trabajadorId */
    private function crearTrabajadores(ObjectManager $manager): array
    {
        $ahora = new DateTimeImmutable();

        $datos = [
            ['EMP-001', 'María García López',      'maria.garcia@empresa.local',     '1234', 365],
            ['EMP-002', 'Carlos Martínez Ruiz',    'carlos.martinez@empresa.local',  '5678', 300],
            ['EMP-003', 'Laura Sánchez Pérez',     'laura.sanchez@empresa.local',    '9012', 250],
            ['EMP-004', 'Antonio López García',    'antonio.lopez@empresa.local',    '3456', 200],
            ['EMP-005', 'Isabel Torres Fernández', 'isabel.torres@empresa.local',    '7890', 150],
            ['EMP-006', 'Javier Rodríguez Díaz',  'javier.rodriguez@empresa.local', '2468',  90],
        ];

        $trabajadores = [];
        foreach ($datos as [$empId, $nombre, $email, $pin, $diasAlta]) {
            $t = new Trabajador(
                $this->uuid(), self::TENANT, $empId, $nombre, $email,
                $ahora->sub(new DateInterval("P{$diasAlta}D"))
            );
            $t->actualizarClaveAcceso($pin);
            $manager->persist($t);
            $trabajadores[$empId] = $t;
        }

        return $trabajadores;
    }

    /** @return array<string, HorarioTrabajo> indexado por nombre */
    private function crearHorarios(ObjectManager $manager): array
    {
        $lv = [1, 2, 3, 4, 5];

        $definiciones = [
            'Turno mañana' => array_map(
                static fn($d) => ['dia' => $d, 'inicio' => '09:00', 'fin' => '17:00'],
                $lv
            ),
            'Turno tarde' => array_map(
                static fn($d) => ['dia' => $d, 'inicio' => '13:00', 'fin' => '21:00'],
                $lv
            ),
            'Turno partido' => array_merge(
                array_map(static fn($d) => ['dia' => $d, 'inicio' => '08:00', 'fin' => '13:00'], $lv),
                array_map(static fn($d) => ['dia' => $d, 'inicio' => '15:00', 'fin' => '19:00'], $lv),
            ),
        ];

        $horarios = [];
        foreach ($definiciones as $nombre => $tramos) {
            $h = new HorarioTrabajo($this->uuid(), self::TENANT, $nombre, $tramos);
            $manager->persist($h);
            $horarios[$nombre] = $h;
        }

        return $horarios;
    }

    /**
     * @param array<string, Trabajador>    $trabajadores
     * @param array<string, HorarioTrabajo> $horarios
     * @return array<string, array{horario: HorarioTrabajo}>
     */
    private function crearAsignaciones(ObjectManager $manager, array $trabajadores, array $horarios): array
    {
        $ahora   = new DateTimeImmutable();
        $nombres = array_keys($horarios);
        $i       = 0;
        $asignaciones = [];

        foreach ($trabajadores as $trabajador) {
            $horario = $horarios[$nombres[$i % count($nombres)]];
            $manager->persist(new AsignacionHorarioEmpleado(
                $this->uuid(), self::TENANT,
                $trabajador->getId(),
                $horario->getId(),
                $ahora->sub(new DateInterval('P90D')),
                null
            ));
            $asignaciones[$trabajador->getId()] = ['horario' => $horario];
            $i++;
        }

        return $asignaciones;
    }

    /**
     * @param array<string, Trabajador>                             $trabajadores
     * @param array<string, array{horario: HorarioTrabajo}>         $asignaciones
     * @return EventoFichaje[]
     */
    private function crearFichajes(ObjectManager $manager, array $trabajadores, array $asignaciones): array
    {
        $todos = [];
        $ahora = new DateTimeImmutable();

        foreach ($trabajadores as $trabajador) {
            $info = $asignaciones[$trabajador->getId()] ?? null;
            if ($info === null) {
                continue;
            }

            $tramosPorDia = $this->agruparTramosPorDia($info['horario']->getTramos());

            for ($d = 60; $d >= 1; $d--) {
                $dia       = $ahora->sub(new DateInterval("P{$d}D"));
                $diaSemana = (int) $dia->format('N'); // 1=Lunes … 7=Domingo

                if ($diaSemana > 5 || !isset($tramosPorDia[$diaSemana])) {
                    continue;
                }
                if ($this->faker->boolean(5)) {
                    continue; // falta espontánea (5 %)
                }

                $tramos = $tramosPorDia[$diaSemana];
                usort($tramos, static fn($a, $b) => strcmp($a['inicio'], $b['inicio']));
                $esPartido = count($tramos) > 1;

                // clock-in
                $offsetIn = $this->faker->numberBetween(-5, 20);
                $horaIn   = $this->sumarMinutos($tramos[0]['inicio'], $offsetIn);
                $estadoIn = $offsetIn > 10 ? 'fuera_horario' : 'dentro_horario';
                $motivoIn = $offsetIn > 10 ? 'llegada_tardia' : null;

                $fichIn = new EventoFichaje(
                    $this->uuid(), self::TENANT, $trabajador->getId(), 'clock-in',
                    $dia->setTime($horaIn[0], $horaIn[1]),
                    $estadoIn, $motivoIn, $this->uuid()
                );
                $manager->persist($fichIn);
                $todos[] = $fichIn;

                if ($esPartido) {
                    // Pausa al mediodía para turno partido
                    $hPs = $this->sumarMinutos($tramos[0]['fin'], $this->faker->numberBetween(-5, 5));
                    $ps  = new EventoFichaje(
                        $this->uuid(), self::TENANT, $trabajador->getId(), 'pause-start',
                        $dia->setTime($hPs[0], $hPs[1]),
                        'dentro_horario', null, $this->uuid()
                    );
                    $manager->persist($ps);
                    $todos[] = $ps;

                    $hPe = $this->sumarMinutos($tramos[1]['inicio'], $this->faker->numberBetween(-5, 10));
                    $pe  = new EventoFichaje(
                        $this->uuid(), self::TENANT, $trabajador->getId(), 'pause-end',
                        $dia->setTime($hPe[0], $hPe[1]),
                        'dentro_horario', null, $this->uuid()
                    );
                    $manager->persist($pe);
                    $todos[] = $pe;
                } elseif ($this->faker->boolean(30)) {
                    // Pausa espontánea en turno corrido (30 % de días)
                    $minBase = $horaIn[0] * 60 + $horaIn[1];
                    $minPs   = min(1380, $minBase + $this->faker->numberBetween(120, 240));
                    $ps      = new EventoFichaje(
                        $this->uuid(), self::TENANT, $trabajador->getId(), 'pause-start',
                        $dia->setTime((int) ($minPs / 60), $minPs % 60),
                        'dentro_horario', null, $this->uuid()
                    );
                    $manager->persist($ps);
                    $todos[] = $ps;

                    $minPe = min(1410, $minPs + $this->faker->numberBetween(15, 45));
                    $pe    = new EventoFichaje(
                        $this->uuid(), self::TENANT, $trabajador->getId(), 'pause-end',
                        $dia->setTime((int) ($minPe / 60), $minPe % 60),
                        'dentro_horario', null, $this->uuid()
                    );
                    $manager->persist($pe);
                    $todos[] = $pe;
                }

                // clock-out
                $lastIdx   = count($tramos) - 1;
                $offsetOut = $this->faker->numberBetween(-10, 30);
                $horaOut   = $this->sumarMinutos($tramos[$lastIdx]['fin'], $offsetOut);
                $estadoOut = $offsetOut < -10 ? 'fuera_horario' : 'dentro_horario';
                $motivoOut = $estadoOut === 'fuera_horario' ? 'salida_anticipada' : null;

                $fichOut = new EventoFichaje(
                    $this->uuid(), self::TENANT, $trabajador->getId(), 'clock-out',
                    $dia->setTime($horaOut[0], $horaOut[1]),
                    $estadoOut, $motivoOut, $this->uuid()
                );
                $manager->persist($fichOut);
                $todos[] = $fichOut;
            }
        }

        return $todos;
    }

    /** @param array<string, Trabajador> $trabajadores */
    private function crearAusencias(ObjectManager $manager, array $trabajadores): void
    {
        $ahora = new DateTimeImmutable();
        $ts    = array_values($trabajadores);

        $datos = [
            [0, 'vacaciones', '-50 days', '-47 days', 'aprobada'],
            [1, 'vacaciones', '-30 days', '-28 days', 'aprobada'],
            [2, 'permiso',    '-15 days', '-14 days', 'aprobada'],
            [0, 'baja',       '-5 days',  '-3 days',  'aprobada'],
            [3, 'vacaciones', '+7 days',  '+14 days', 'pendiente'],
            [4, 'permiso',    '+3 days',  '+3 days',  'pendiente'],
            [1, 'baja',       '-2 days',  '+1 day',   'rechazada'],
            [5, 'vacaciones', '-20 days', '-18 days', 'aprobada'],
            [2, 'vacaciones', '+20 days', '+27 days', 'pendiente'],
            [4, 'baja',       '-8 days',  '-7 days',  'aprobada'],
        ];

        foreach ($datos as [$idx, $tipo, $inicioStr, $finStr, $estado]) {
            $id = $this->uuid();
            $a  = new SolicitudAusencia(
                $id, self::TENANT, $ts[$idx]->getId(), $tipo,
                new DateTimeImmutable($ahora->modify($inicioStr)->format('Y-m-d')),
                new DateTimeImmutable($ahora->modify($finStr)->format('Y-m-d')),
                $id
            );
            if ($estado === 'aprobada') {
                $a->aprobar();
            } elseif ($estado === 'rechazada') {
                $a->rechazar();
            }
            $manager->persist($a);
        }
    }

    /** @param EventoFichaje[] $fichajes */
    private function crearCorrecciones(ObjectManager $manager, array $fichajes): void
    {
        $fueraHorario = array_filter(
            $fichajes,
            static fn(EventoFichaje $f) => $f->getEstadoCumplimiento() === 'fuera_horario'
        );
        $seleccionados = array_slice(array_values($fueraHorario), 0, 8);

        $motivos = [
            'Retraso por huelga de transporte público',
            'Reunión imprevista con cliente que se extendió',
            'Tráfico intenso por accidente en la autovía',
            'Fallo técnico en el kiosco de fichaje',
            'Cambio de turno acordado verbalmente con supervisor',
            'Formación externa no registrada en el calendario',
            'Atención a incidencia urgente fuera de horario',
            'Error de configuración en el horario asignado',
        ];

        foreach ($seleccionados as $i => $fichaje) {
            $id = $this->uuid();
            $c  = new CorreccionFichaje(
                $id, self::TENANT, $fichaje->getId(),
                $this->faker->randomElement($motivos),
                null,
                $fichaje->getOcurridoEn(),
                $fichaje->getTipo()
            );
            if ($i < 3) {
                $c->aprobar($this->uuid());
            }
            $manager->persist($c);
        }
    }

    /** @param array<string, HorarioTrabajo> $horarios */
    private function crearAuditoria(ObjectManager $manager, array $horarios): void
    {
        $ahora = new DateTimeImmutable();
        $hs    = array_values($horarios);

        $entradas = [
            ['horario.creado',        '-60 days',  null,                                  ['nombre' => $hs[0]->getNombre()]],
            ['horario.creado',        '-60 days',  null,                                  ['nombre' => $hs[1]->getNombre()]],
            ['trabajador.creado',     '-59 days',  null,                                  ['trabajadorId' => 'EMP-001']],
            ['trabajador.creado',     '-58 days',  null,                                  ['trabajadorId' => 'EMP-002']],
            ['trabajador.creado',     '-55 days',  null,                                  ['trabajadorId' => 'EMP-003']],
            ['asignacion.creada',     '-54 days',  null,                                  ['horario' => $hs[0]->getNombre()]],
            ['ausencia.solicitada',   '-30 days',  null,                                  ['tipo' => 'vacaciones', 'estado' => 'pendiente']],
            ['ausencia.aprobada',     '-29 days',  ['estado' => 'pendiente'],             ['estado' => 'aprobada']],
            ['fichaje.registrado',    '-20 days',  null,                                  ['tipo' => 'clock-in',  'cumplimiento' => 'dentro_horario']],
            ['fichaje.registrado',    '-20 days',  null,                                  ['tipo' => 'clock-out', 'cumplimiento' => 'fuera_horario']],
            ['correccion.solicitada', '-19 days',  null,                                  ['motivo' => 'Tráfico imprevisto']],
            ['correccion.aprobada',   '-18 days',  ['estado' => 'pendiente'],             ['estado' => 'aprobada']],
            ['trabajador.editado',    '-15 days',  ['nombre' => 'Carlos M.'],             ['nombre' => 'Carlos Martínez Ruiz']],
            ['ausencia.solicitada',   '-10 days',  null,                                  ['tipo' => 'permiso', 'estado' => 'pendiente']],
            ['ausencia.rechazada',    '-9 days',   ['estado' => 'pendiente'],             ['estado' => 'rechazada']],
            ['horario.editado',       '-8 days',   ['nombre' => 'Turno A'],               ['nombre' => 'Turno partido']],
            ['usuario.login',         '-5 days',   null,                                  ['email' => 'admin@empresa.local']],
            ['fichaje.registrado',    '-3 days',   null,                                  ['tipo' => 'clock-in', 'cumplimiento' => 'fuera_horario']],
            ['trabajador.creado',     '-2 days',   null,                                  ['trabajadorId' => 'EMP-006']],
            ['ausencia.solicitada',   '-1 day',    null,                                  ['tipo' => 'vacaciones', 'estado' => 'pendiente']],
            ['horario.editado',       '-12 hours', ['tramos' => 'v1'],                    ['tramos' => 'v2']],
            ['usuario.clave_cambiada','-6 hours',  null,                                  null],
        ];

        foreach ($entradas as [$accion, $delta, $antes, $despues]) {
            $registro = new RegistroAuditoria($this->uuid(), self::TENANT, $accion, $antes, $despues);
            // Backdateamos creadoEn ya que el constructor lo fija a "ahora"
            $ref = new \ReflectionProperty(RegistroAuditoria::class, 'creadoEn');
            $ref->setValue($registro, $ahora->modify($delta));
            $manager->persist($registro);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /** @return array<int, list<array{dia: int, inicio: string, fin: string}>> */
    private function agruparTramosPorDia(array $tramos): array
    {
        $result = [];
        foreach ($tramos as $tramo) {
            $result[(int) $tramo['dia']][] = $tramo;
        }

        return $result;
    }

    /** @return array{0: int, 1: int} [hora, minuto] */
    private function sumarMinutos(string $hora, int $offset): array
    {
        [$h, $m] = explode(':', $hora);
        $total   = max(0, min(1439, (int) $h * 60 + (int) $m + $offset));

        return [(int) ($total / 60), $total % 60];
    }
}
