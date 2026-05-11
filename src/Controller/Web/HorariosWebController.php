<?php

namespace App\Controller\Web;

use App\Modulo\Horarios\Application\Servicio\AsignarHorarioEmpleado;
use App\Modulo\Horarios\Application\Servicio\CrearHorario;
use App\Modulo\Horarios\Application\Servicio\EditarHorario;
use App\Modulo\Plataforma\Application\Tenant\TenantContexto;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/app/horarios')]
class HorariosWebController extends AbstractController
{
    public function __construct(private readonly TenantContexto $tenantContexto, private readonly Connection $connection) {}

    #[Route('', name: 'web_horarios_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EMPLEADO');
        $tenantId = $this->tenantContexto->obtenerTenantId();
        $q = trim((string) $request->query->get('q', ''));
        $tamano = max(10, min(50, (int) $request->query->get('tamano', 20)));
        $pagina = max(1, (int) $request->query->get('pagina', 1));

        $qAsig = trim((string) $request->query->get('qAsig', ''));
        $tamanoAsig = max(10, min(50, (int) $request->query->get('tamanoAsig', 20)));
        $paginaAsig = max(1, (int) $request->query->get('paginaAsig', 1));

        $sqlHorariosBase = ' FROM horario_trabajo WHERE tenantId = :tenant';
        $paramsHor = ['tenant' => $tenantId];
        if ($q !== '') {
            $sqlHorariosBase .= ' AND (id LIKE :q OR nombre LIKE :q)';
            $paramsHor['q'] = '%'.$q.'%';
        }
        $totalHorarios = (int) $this->connection->fetchOne('SELECT COUNT(*)'.$sqlHorariosBase, $paramsHor);
        $totalPaginasHorarios = max(1, (int) ceil($totalHorarios / $tamano));
        $pagina = min($pagina, $totalPaginasHorarios);
        $offsetHor = ($pagina - 1) * $tamano;
        $horarios = $this->connection->fetchAllAssociative('SELECT id, nombre, tramos'.$sqlHorariosBase.' ORDER BY nombre LIMIT '.$tamano.' OFFSET '.$offsetHor, $paramsHor);

        $sqlAsigBase = ' FROM asignacion_horario_empleado a
            LEFT JOIN trabajador t ON t.id = a.empleadoId AND t.tenantId = a.tenantId
            LEFT JOIN horario_trabajo h ON h.id = a.horarioId AND h.tenantId = a.tenantId
            WHERE a.tenantId = :tenant';
        $paramsAsig = ['tenant' => $tenantId];
        if ($qAsig !== '') {
            $sqlAsigBase .= ' AND (t.nombre LIKE :qAsig OR t.trabajadorId LIKE :qAsig OR h.nombre LIKE :qAsig)';
            $paramsAsig['qAsig'] = '%'.$qAsig.'%';
        }
        $totalAsig = (int) $this->connection->fetchOne('SELECT COUNT(*)'.$sqlAsigBase, $paramsAsig);
        $totalPaginasAsig = max(1, (int) ceil($totalAsig / $tamanoAsig));
        $paginaAsig = min($paginaAsig, $totalPaginasAsig);
        $offsetAsig = ($paginaAsig - 1) * $tamanoAsig;
        $asignaciones = $this->connection->fetchAllAssociative(
            'SELECT a.empleadoId, a.horarioId, a.vigenteDesde, a.vigenteHasta,
                    COALESCE(t.nombre, a.empleadoId) AS empleadoNombre,
                    t.trabajadorId AS empleadoCodigo,
                    COALESCE(h.nombre, a.horarioId) AS horarioNombre'
            .$sqlAsigBase.' ORDER BY a.vigenteDesde DESC LIMIT '.$tamanoAsig.' OFFSET '.$offsetAsig,
            $paramsAsig
        );
        $trabajadores = $this->connection->fetchAllAssociative('SELECT trabajadorId, nombre FROM trabajador WHERE tenantId = :tenant AND activo = 1 ORDER BY trabajadorId', ['tenant' => $tenantId]);
        $calendarAsignaciones = $this->connection->fetchAllAssociative(
            'SELECT a.empleadoId, a.horarioId, a.vigenteDesde, a.vigenteHasta,
                    COALESCE(t.nombre, a.empleadoId) AS empleadoNombre,
                    t.trabajadorId AS empleadoCodigo,
                    COALESCE(h.nombre, a.horarioId) AS horarioNombre
             FROM asignacion_horario_empleado a
             LEFT JOIN trabajador t ON t.id = a.empleadoId AND t.tenantId = a.tenantId
             LEFT JOIN horario_trabajo h ON h.id = a.horarioId AND h.tenantId = a.tenantId
             WHERE a.tenantId = :tenant ORDER BY a.vigenteDesde DESC LIMIT 200',
            ['tenant' => $tenantId]
        );

        return $this->render('web/horarios/index.html.twig', [
            'horarios' => $horarios,
            'asignaciones' => $asignaciones,
            'trabajadores' => $trabajadores,
            'filtros' => ['q' => $q, 'tamano' => $tamano],
            'paginacion' => ['total' => $totalHorarios, 'pagina' => $pagina, 'totalPaginas' => $totalPaginasHorarios],
            'filtrosAsig' => ['qAsig' => $qAsig, 'tamanoAsig' => $tamanoAsig],
            'paginacionAsig' => ['total' => $totalAsig, 'paginaAsig' => $paginaAsig, 'totalPaginasAsig' => $totalPaginasAsig],
            'calendarAsignaciones' => $calendarAsignaciones,
        ]);
    }

    #[Route('/nuevo', name: 'web_horarios_nuevo', methods: ['GET'])]
    public function nuevo(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERVISOR');

        return $this->render('web/horarios/form.html.twig', ['modo' => 'crear', 'horario' => null]);
    }

    #[Route('/{id}/editar-form', name: 'web_horarios_editar_form', methods: ['GET'])]
    public function editarForm(string $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERVISOR');
        $tenantId = $this->tenantContexto->obtenerTenantId();
        $horario = $this->connection->fetchAssociative('SELECT id, nombre, tramos FROM horario_trabajo WHERE tenantId = :tenant AND id = :id', ['tenant' => $tenantId, 'id' => $id]);
        if (!$horario) {
            throw $this->createNotFoundException('Horario no encontrado');
        }

        return $this->render('web/horarios/form.html.twig', ['modo' => 'editar', 'horario' => $horario]);
    }

    #[Route('/asignaciones/nueva', name: 'web_horarios_asignacion_nueva', methods: ['GET'])]
    public function nuevaAsignacion(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERVISOR');
        $tenantId = $this->tenantContexto->obtenerTenantId();
        $horarios = $this->connection->fetchAllAssociative('SELECT id, nombre FROM horario_trabajo WHERE tenantId = :tenant ORDER BY nombre', ['tenant' => $tenantId]);
        $trabajadores = $this->connection->fetchAllAssociative('SELECT trabajadorId, nombre FROM trabajador WHERE tenantId = :tenant AND activo = 1 ORDER BY trabajadorId', ['tenant' => $tenantId]);

        return $this->render('web/horarios/asignacion_form.html.twig', ['horarios' => $horarios, 'trabajadores' => $trabajadores]);
    }

    #[Route('/crear', name: 'web_horarios_crear', methods: ['POST'])]
    public function crear(Request $request, CrearHorario $servicio): RedirectResponse
    {
        return $this->guardarHorario($request, $servicio);
    }

    #[Route('/{id}/editar', name: 'web_horarios_editar', methods: ['POST'])]
    public function editar(string $id, Request $request, EditarHorario $servicio): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERVISOR');
        if (!$this->isCsrfTokenValid('horario_editar_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectToRoute('web_horarios_index');
        }

        try {
            $servicio->ejecutar(
                $this->tenantContexto->obtenerTenantId(),
                $id,
                trim((string) $request->request->get('nombre')),
                $this->parsearTramos((string) $request->request->get('tramos'))
            );
            $this->addFlash('success', 'Horario actualizado.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'No se pudo editar: '.$e->getMessage());
        }

        return $this->redirectToRoute('web_horarios_index');
    }

    #[Route('/asignar', name: 'web_horarios_asignar', methods: ['POST'])]
    public function asignar(Request $request, AsignarHorarioEmpleado $servicio): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERVISOR');
        if (!$this->isCsrfTokenValid('horario_asignar', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectToRoute('web_horarios_index');
        }

        try {
            $servicio->ejecutar(
                $this->tenantContexto->obtenerTenantId(),
                trim((string) $request->request->get('trabajadorId')),
                trim((string) $request->request->get('horarioId')),
                new DateTimeImmutable((string) $request->request->get('vigenteDesde')),
                $request->request->get('vigenteHasta') ? new DateTimeImmutable((string) $request->request->get('vigenteHasta')) : null
            );
            $this->addFlash('success', 'Horario asignado.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'No se pudo asignar: '.$e->getMessage());
        }

        return $this->redirectToRoute('web_horarios_index');
    }

    private function guardarHorario(Request $request, CrearHorario $servicio): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERVISOR');
        if (!$this->isCsrfTokenValid('horario_crear', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectToRoute('web_horarios_index');
        }

        try {
            $servicio->ejecutar(
                $this->tenantContexto->obtenerTenantId(),
                trim((string) $request->request->get('nombre')),
                $this->parsearTramos((string) $request->request->get('tramos'))
            );
            $this->addFlash('success', 'Horario creado.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'No se pudo crear: '.$e->getMessage());
        }

        return $this->redirectToRoute('web_horarios_index');
    }

    private function parsearTramos(string $texto): array
    {
        $tramos = [];
        foreach (array_filter(array_map('trim', explode("\n", $texto))) as $linea) {
            [$dia, $inicio, $fin] = array_pad(array_map('trim', explode(',', $linea)), 3, null);
            if ($dia === null || $inicio === null || $fin === null) {
                continue;
            }
            $tramos[] = ['dia' => (int) $dia, 'inicio' => $inicio, 'fin' => $fin];
        }

        if ($tramos === []) {
            throw new \DomainException('HORARIO_TRAMOS_REQUERIDOS');
        }

        return $tramos;
    }
}
