<?php

namespace App\Modulo\Trabajadores\Infrastructure\Repository;

use App\Modulo\Trabajadores\Domain\Entity\Trabajador;
use Doctrine\ORM\EntityManagerInterface;

class TrabajadorRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function guardar(Trabajador $trabajador): void
    {
        $this->entityManager->persist($trabajador);
        $this->entityManager->flush();
    }

    public function buscarPorTenantYTrabajadorId(string $tenantId, string $trabajadorId): ?Trabajador
    {
        return $this->entityManager->getRepository(Trabajador::class)->findOneBy([
            'tenantId' => $tenantId,
            'trabajadorId' => $trabajadorId,
        ]);
    }

    public function buscarActivoPorCredenciales(string $trabajadorId, string $claveAcceso): ?Trabajador
    {
        $trabajador = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Trabajador::class, 't')
            ->andWhere('t.trabajadorId = :trabajadorId')
            ->andWhere('t.activo = true')
            ->setParameter('trabajadorId', $trabajadorId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($trabajador === null) {
            return null;
        }

        return $trabajador->validarClaveAcceso($claveAcceso) ? $trabajador : null;
    }

    public function buscarActivoPorClave(string $tenantId, string $claveAcceso): ?Trabajador
    {
        $activos = $this->listarActivosPorTenant($tenantId);
        foreach ($activos as $trabajador) {
            if ($trabajador->validarClaveAcceso($claveAcceso)) {
                return $trabajador;
            }
        }

        return null;
    }

    /** @return Trabajador[] */
    public function listarPorTenant(string $tenantId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Trabajador::class, 't')
            ->andWhere('t.tenantId = :tenantId')
            ->setParameter('tenantId', $tenantId)
            ->orderBy('t.trabajadorId', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Trabajador[] */
    public function listarActivosPorTenant(string $tenantId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Trabajador::class, 't')
            ->andWhere('t.tenantId = :tenantId')
            ->andWhere('t.activo = true')
            ->setParameter('tenantId', $tenantId)
            ->orderBy('t.trabajadorId', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{items: Trabajador[], total: int}
     */
    public function buscarPaginadoPorTenant(string $tenantId, string $estado, ?string $q, int $pagina, int $tamano): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Trabajador::class, 't')
            ->andWhere('t.tenantId = :tenantId')
            ->setParameter('tenantId', $tenantId);

        if ('activos' === $estado) {
            $qb->andWhere('t.activo = true');
        } elseif ('inactivos' === $estado) {
            $qb->andWhere('t.activo = false');
        }

        $qNormalizada = $q !== null ? trim($q) : '';
        if ('' !== $qNormalizada) {
            $qb->andWhere('LOWER(t.trabajadorId) LIKE :q OR LOWER(t.nombre) LIKE :q OR LOWER(COALESCE(t.email, \'\')) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($qNormalizada).'%');
        }

        $total = (int) (clone $qb)
            ->select('COUNT(t.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->orderBy('t.trabajadorId', 'ASC')
            ->setFirstResult(($pagina - 1) * $tamano)
            ->setMaxResults($tamano)
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }
}
