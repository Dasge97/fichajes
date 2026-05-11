<?php

namespace App\Controller\Web;

use App\Modulo\Acceso\Application\Servicio\ResolverPermisoRol;
use App\Modulo\Acceso\Application\Servicio\ValidarOwnershipTrabajador;
use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Ausencias\Application\Servicio\AprobarAusencia;
use App\Modulo\Ausencias\Application\Servicio\RechazarAusencia;
use App\Modulo\Ausencias\Application\Servicio\SolicitarAusencia;
use App\Modulo\Plataforma\Application\Tenant\TenantContexto;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/app/ausencias')]
class AusenciasWebController extends AbstractController
{
    public function __construct(
        private readonly TenantContexto $tenantContexto,
        private readonly Connection $connection,
        private readonly ResolverPermisoRol $resolverPermisoRol
    ) {}

    #[Route('', name: 'web_ausencias_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EMPLEADO');
        $tenantId = $this->tenantContexto->obtenerTenantId();
        $empleadoId = trim((string) $request->query->get('empleadoId'));
        $estado = trim((string) $request->query->get('estado'));
        $tamano = max(10, min(50, (int) $request->query->get('tamano', 20)));
        $pagina = max(1, (int) $request->query->get('pagina', 1));

        $sqlBase = ' FROM solicitud_ausencia sa
            LEFT JOIN trabajador t ON t.id = sa.empleadoId AND t.tenantId = sa.tenantId
            WHERE sa.tenantId = :tenant';
        $params = ['tenant' => $tenantId];
        if ($empleadoId !== '') {
            $sqlBase .= ' AND (t.nombre LIKE :empleado OR t.trabajadorId LIKE :empleado)';
            $params['empleado'] = '%'.$empleadoId.'%';
        }
        if ($estado !== '') {
            $sqlBase .= ' AND sa.estado = :estado';
            $params['estado'] = $estado;
        }
        $total = (int) $this->connection->fetchOne('SELECT COUNT(*)'.$sqlBase, $params);
        $totalPaginas = max(1, (int) ceil($total / $tamano));
        $pagina = min($pagina, $totalPaginas);
        $offset = ($pagina - 1) * $tamano;

        $items = $this->connection->fetchAllAssociative(
            'SELECT sa.id, sa.tipo, sa.fechaInicio, sa.fechaFin, sa.estado,
                    COALESCE(t.nombre, sa.empleadoId) AS empleadoNombre,
                    t.trabajadorId AS empleadoCodigo'
            .$sqlBase.' ORDER BY sa.fechaInicio DESC LIMIT '.$tamano.' OFFSET '.$offset,
            $params
        );
        $trabajadores = $this->connection->fetchAllAssociative(
            'SELECT trabajadorId, nombre FROM trabajador WHERE tenantId = :tenant AND activo = 1 ORDER BY trabajadorId',
            ['tenant' => $tenantId]
        );
        $calendarItems = $this->connection->fetchAllAssociative(
            'SELECT sa.tipo, sa.fechaInicio, sa.fechaFin, sa.estado,
                    COALESCE(t.nombre, sa.empleadoId) AS empleadoNombre
             FROM solicitud_ausencia sa
             LEFT JOIN trabajador t ON t.id = sa.empleadoId AND t.tenantId = sa.tenantId
             WHERE sa.tenantId = :tenant ORDER BY sa.fechaInicio DESC LIMIT 500',
            ['tenant' => $tenantId]
        );

        return $this->render('web/ausencias/index.html.twig', [
            'items' => $items,
            'filtroEmpleado' => $empleadoId,
            'trabajadores' => $trabajadores,
            'filtros' => ['estado' => $estado, 'tamano' => $tamano],
            'paginacion' => ['total' => $total, 'pagina' => $pagina, 'totalPaginas' => $totalPaginas],
            'calendarItems' => $calendarItems,
        ]);
    }

    #[Route('/solicitar', name: 'web_ausencias_solicitar', methods: ['POST'])]
    public function solicitar(Request $request, SolicitarAusencia $servicio, ValidarOwnershipTrabajador $ownership): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_EMPLEADO');
        if (!$this->isCsrfTokenValid('ausencia_solicitar', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectToRoute('web_ausencias_index');
        }

        try {
            $tenantId = $this->tenantContexto->obtenerTenantId();
            $trabajadorId = trim((string) $request->request->get('trabajadorId'));
            $usuario = $this->getUser();
            if ($usuario instanceof Usuario) {
                $ownership->validar($usuario, $tenantId, $trabajadorId, 'fichajes.registrar.propio');
            }
            $servicio->ejecutar(
                $tenantId,
                $trabajadorId,
                trim((string) $request->request->get('tipo')),
                new DateTimeImmutable((string) $request->request->get('fechaInicio')),
                new DateTimeImmutable((string) $request->request->get('fechaFin')),
                uniqid('web-aus-', true)
            );
            $this->addFlash('success', 'Ausencia solicitada.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'No se pudo solicitar: '.$e->getMessage());
        }

        return $this->redirectToRoute('web_ausencias_index');
    }

    #[Route('/nueva', name: 'web_ausencias_nueva', methods: ['GET'])]
    public function nueva(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EMPLEADO');
        $tenantId = $this->tenantContexto->obtenerTenantId();
        $trabajadores = $this->connection->fetchAllAssociative(
            'SELECT trabajadorId, nombre FROM trabajador WHERE tenantId = :tenant AND activo = 1 ORDER BY trabajadorId',
            ['tenant' => $tenantId]
        );

        return $this->render('web/ausencias/form.html.twig', ['trabajadores' => $trabajadores]);
    }

    #[Route('/{id}/aprobar', name: 'web_ausencias_aprobar', methods: ['POST'])]
    public function aprobar(string $id, Request $request, AprobarAusencia $servicio): RedirectResponse
    {
        return $this->resolver($id, $request, $servicio, 'aprobar');
    }

    #[Route('/{id}/rechazar', name: 'web_ausencias_rechazar', methods: ['POST'])]
    public function rechazar(string $id, Request $request, RechazarAusencia $servicio): RedirectResponse
    {
        return $this->resolver($id, $request, $servicio, 'rechazar');
    }

    private function resolver(string $id, Request $request, object $servicio, string $accion): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERVISOR');
        $this->asegurarPermiso('correcciones.aprobar');
        if (!$this->isCsrfTokenValid('ausencia_'.$accion.'_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectToRoute('web_ausencias_index');
        }

        try {
            $servicio->ejecutar($this->tenantContexto->obtenerTenantId(), $id);
            $this->addFlash('success', 'Solicitud '.$accion.'da.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'No se pudo '.$accion.': '.$e->getMessage());
        }

        return $this->redirectToRoute('web_ausencias_index');
    }

    private function asegurarPermiso(string $permiso): void
    {
        $usuario = $this->getUser();
        if (!$usuario instanceof Usuario) {
            throw $this->createAccessDeniedException('Usuario no autenticado.');
        }
        if (!$this->resolverPermisoRol->puede($permiso, $usuario->getCodigosRolTenant())) {
            throw $this->createAccessDeniedException('Permiso insuficiente.');
        }
    }
}
