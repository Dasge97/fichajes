<?php

namespace App\Modulo\Fichajes\Infrastructure\Repository;

use App\Modulo\Fichajes\Domain\Entity\EventoFichaje;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class EventoFichajeRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function guardar(EventoFichaje $evento): void
    {
        $this->entityManager->persist($evento);
        $this->entityManager->flush();
    }

    public function buscarPorIdempotencia(string $tenantId, string $idempotencyKey): ?EventoFichaje
    {
        return $this->entityManager->getRepository(EventoFichaje::class)->findOneBy([
            'tenantId' => $tenantId,
            'idempotencyKey' => $idempotencyKey,
        ]);
    }

    public function ultimoEventoDelDia(string $tenantId, string $empleadoId, DateTimeImmutable $fecha): ?EventoFichaje
    {
        $inicio = $fecha->setTime(0, 0, 0);
        $fin = $fecha->setTime(23, 59, 59);

        return $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(EventoFichaje::class, 'e')
            ->andWhere('e.tenantId = :tenantId')
            ->andWhere('e.empleadoId = :empleadoId')
            ->andWhere('e.ocurridoEn BETWEEN :inicio AND :fin')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('empleadoId', $empleadoId)
            ->setParameter('inicio', $inicio)
            ->setParameter('fin', $fin)
            ->orderBy('e.ocurridoEn', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
