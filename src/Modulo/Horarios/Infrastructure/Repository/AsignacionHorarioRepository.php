<?php

namespace App\Modulo\Horarios\Infrastructure\Repository;

use App\Modulo\Fichajes\Application\Contract\ProveedorHorarioVigente;
use App\Modulo\Horarios\Domain\Entity\AsignacionHorarioEmpleado;
use App\Modulo\Horarios\Domain\Entity\HorarioTrabajo;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class AsignacionHorarioRepository implements ProveedorHorarioVigente
{
    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function guardar(AsignacionHorarioEmpleado $asignacion): void
    {
        $this->entityManager->persist($asignacion);
        $this->entityManager->flush();
    }

    public function existeSolape(string $tenantId, string $empleadoId, DateTimeImmutable $desde, ?DateTimeImmutable $hasta): bool
    {
        $hastaEvaluado = $hasta ?? new DateTimeImmutable('9999-12-31 23:59:59');

        $coincidencias = $this->entityManager->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(AsignacionHorarioEmpleado::class, 'a')
            ->andWhere('a.tenantId = :tenantId')
            ->andWhere('a.empleadoId = :empleadoId')
            ->andWhere('a.vigenteDesde <= :hasta')
            ->andWhere('(a.vigenteHasta IS NULL OR a.vigenteHasta >= :desde)')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('empleadoId', $empleadoId)
            ->setParameter('desde', $desde)
            ->setParameter('hasta', $hastaEvaluado)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $coincidencias > 0;
    }

    /** @return AsignacionHorarioEmpleado[] */
    public function buscarPorEmpleado(string $tenantId, string $empleadoId): array
    {
        return $this->entityManager->getRepository(AsignacionHorarioEmpleado::class)->findBy([
            'tenantId' => $tenantId,
            'empleadoId' => $empleadoId,
        ], ['vigenteDesde' => 'DESC']);
    }

    public function estaDentroDeHorario(string $tenantId, string $empleadoId, DateTimeImmutable $fecha): bool
    {
        $asignacion = $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(AsignacionHorarioEmpleado::class, 'a')
            ->andWhere('a.tenantId = :tenantId')
            ->andWhere('a.empleadoId = :empleadoId')
            ->andWhere('a.vigenteDesde <= :fecha')
            ->andWhere('(a.vigenteHasta IS NULL OR a.vigenteHasta >= :fecha)')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('empleadoId', $empleadoId)
            ->setParameter('fecha', $fecha)
            ->orderBy('a.vigenteDesde', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($asignacion === null) {
            return true;
        }

        $horario = $this->entityManager->getRepository(HorarioTrabajo::class)->findOneBy([
            'id' => $asignacion->getHorarioId(),
            'tenantId' => $tenantId,
        ]);
        if ($horario === null) {
            return false;
        }

        $diaSemana = (int) $fecha->format('N');
        $horaActual = (int) $fecha->format('Hi');
        foreach ($horario->getTramos() as $tramo) {
            if (($tramo['dia'] ?? null) !== $diaSemana) {
                continue;
            }
            $inicio = (int) str_replace(':', '', (string) ($tramo['inicio'] ?? '00:00'));
            $fin = (int) str_replace(':', '', (string) ($tramo['fin'] ?? '00:00'));
            if ($horaActual >= $inicio && $horaActual <= $fin) {
                return true;
            }
        }

        return false;
    }
}
