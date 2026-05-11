<?php

namespace App\Modulo\Ausencias\Infrastructure\Repository;

use App\Modulo\Ausencias\Domain\Entity\SolicitudAusencia;
use App\Modulo\Fichajes\Application\Contract\ProveedorAusenciaAprobada;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class SolicitudAusenciaRepository implements ProveedorAusenciaAprobada
{
    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function guardar(SolicitudAusencia $ausencia): void
    {
        $this->entityManager->persist($ausencia);
        $this->entityManager->flush();
    }

    public function buscarPorId(string $id): ?SolicitudAusencia
    {
        return $this->entityManager->getRepository(SolicitudAusencia::class)->find($id);
    }

    public function buscarPorIdempotencia(string $tenantId, string $idempotencyKey): ?SolicitudAusencia
    {
        return $this->entityManager->getRepository(SolicitudAusencia::class)->findOneBy([
            'tenantId' => $tenantId,
            'idempotencyKey' => $idempotencyKey,
        ]);
    }

    /** @return SolicitudAusencia[] */
    public function buscarAprobadasEnPeriodo(string $tenantId, string $empleadoId, DateTimeImmutable $desde, DateTimeImmutable $hasta): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(SolicitudAusencia::class, 's')
            ->andWhere('s.tenantId = :tenantId')
            ->andWhere('s.empleadoId = :empleadoId')
            ->andWhere('s.estado = :estado')
            ->andWhere('s.fechaInicio <= :hasta')
            ->andWhere('s.fechaFin >= :desde')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('empleadoId', $empleadoId)
            ->setParameter('estado', SolicitudAusencia::ESTADO_APROBADA)
            ->setParameter('desde', $desde)
            ->setParameter('hasta', $hasta)
            ->orderBy('s.fechaInicio', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function tieneAusenciaAprobada(string $tenantId, string $empleadoId, DateTimeImmutable $fecha): bool
    {
        $items = $this->entityManager->getRepository(SolicitudAusencia::class)->findBy([
            'tenantId' => $tenantId,
            'empleadoId' => $empleadoId,
            'estado' => SolicitudAusencia::ESTADO_APROBADA,
        ]);

        foreach ($items as $item) {
            if ($fecha >= $item->getFechaInicio() && $fecha <= $item->getFechaFin()) {
                return true;
            }
        }

        return false;
    }
}
