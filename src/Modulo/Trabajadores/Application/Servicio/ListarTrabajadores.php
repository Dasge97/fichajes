<?php

namespace App\Modulo\Trabajadores\Application\Servicio;

use App\Modulo\Trabajadores\Infrastructure\Repository\TrabajadorRepository;

class ListarTrabajadores
{
    public const ESTADO_TODOS = 'todos';
    public const ESTADO_ACTIVOS = 'activos';
    public const ESTADO_INACTIVOS = 'inactivos';

    /** @var int[] */
    public const TAMANOS_PERMITIDOS = [10, 20, 50];

    public function __construct(private readonly TrabajadorRepository $repository) {}

    public function ejecutar(string $tenantId, bool $soloActivos = false): array
    {
        if ($soloActivos) {
            return $this->repository->listarActivosPorTenant($tenantId);
        }

        return $this->repository->listarPorTenant($tenantId);
    }

    /**
     * @return array{
     *     items: array,
     *     total: int,
     *     pagina: int,
     *     tamano: int,
     *     totalPaginas: int,
     *     q: string,
     *     estado: string
     * }
     */
    public function ejecutarPaginado(string $tenantId, ?string $q, string $estado, int $pagina, int $tamano): array
    {
        $estadoNormalizado = $this->normalizarEstado($estado);
        $tamanoNormalizado = $this->normalizarTamano($tamano);
        $paginaNormalizada = max(1, $pagina);
        $qNormalizada = trim((string) $q);

        $resultado = $this->repository->buscarPaginadoPorTenant(
            $tenantId,
            $estadoNormalizado,
            $qNormalizada !== '' ? $qNormalizada : null,
            $paginaNormalizada,
            $tamanoNormalizado
        );

        $total = $resultado['total'];
        $totalPaginas = max(1, (int) ceil($total / $tamanoNormalizado));

        if ($paginaNormalizada > $totalPaginas) {
            $paginaNormalizada = $totalPaginas;
            $resultado = $this->repository->buscarPaginadoPorTenant(
                $tenantId,
                $estadoNormalizado,
                $qNormalizada !== '' ? $qNormalizada : null,
                $paginaNormalizada,
                $tamanoNormalizado
            );
        }

        return [
            'items' => $resultado['items'],
            'total' => $total,
            'pagina' => $paginaNormalizada,
            'tamano' => $tamanoNormalizado,
            'totalPaginas' => $totalPaginas,
            'q' => $qNormalizada,
            'estado' => $estadoNormalizado,
        ];
    }

    private function normalizarEstado(string $estado): string
    {
        return in_array($estado, [self::ESTADO_TODOS, self::ESTADO_ACTIVOS, self::ESTADO_INACTIVOS], true)
            ? $estado
            : self::ESTADO_TODOS;
    }

    private function normalizarTamano(int $tamano): int
    {
        return in_array($tamano, self::TAMANOS_PERMITIDOS, true)
            ? $tamano
            : self::TAMANOS_PERMITIDOS[0];
    }
}
