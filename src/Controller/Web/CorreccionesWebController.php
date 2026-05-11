<?php

namespace App\Controller\Web;

use App\Modulo\Acceso\Application\Servicio\ResolverPermisoRol;
use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Correcciones\Application\Servicio\AprobarCorreccionFichaje;
use App\Modulo\Correcciones\Application\Servicio\SolicitarCorreccionFichaje;
use App\Modulo\Fichajes\Domain\Entity\EventoFichaje;
use App\Modulo\Plataforma\Application\Tenant\TenantContexto;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/app/correcciones')]
class CorreccionesWebController extends AbstractController
{
    public function __construct(
        private readonly TenantContexto $tenantContexto,
        private readonly Connection $connection,
        private readonly ResolverPermisoRol $resolverPermisoRol,
        private readonly EntityManagerInterface $entityManager
    ) {}

    #[Route('', name: 'web_correcciones_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EMPLEADO');
        $tenantId = $this->tenantContexto->obtenerTenantId();
        $q = trim((string) $request->query->get('q', ''));
        $estado = trim((string) $request->query->get('estado', ''));
        $tamano = max(10, min(50, (int) $request->query->get('tamano', 20)));
        $pagina = max(1, (int) $request->query->get('pagina', 1));
        $sqlBase = ' FROM correccion_fichaje WHERE tenantId = :tenant';
        $params = ['tenant' => $tenantId];
        if ($q !== '') {
            $sqlBase .= ' AND (eventoFichajeId LIKE :q OR motivo LIKE :q)';
            $params['q'] = '%'.$q.'%';
        }
        if ($estado !== '') {
            $sqlBase .= ' AND estado = :estado';
            $params['estado'] = $estado;
        }
        $total = (int) $this->connection->fetchOne('SELECT COUNT(*)'.$sqlBase, $params);
        $totalPaginas = max(1, (int) ceil($total / $tamano));
        $pagina = min($pagina, $totalPaginas);
        $offset = ($pagina - 1) * $tamano;
        $items = $this->connection->fetchAllAssociative(
            'SELECT id, eventoFichajeId, estado, motivo, ocurridoEnCorregido, tipoCorregido'.$sqlBase.' ORDER BY id DESC LIMIT '.$tamano.' OFFSET '.$offset,
            $params
        );

        return $this->render('web/correcciones/index.html.twig', [
            'items' => $items,
            'filtros' => ['q' => $q, 'estado' => $estado, 'tamano' => $tamano],
            'paginacion' => ['total' => $total, 'pagina' => $pagina, 'totalPaginas' => $totalPaginas],
        ]);
    }

    #[Route('/crear', name: 'web_correcciones_crear', methods: ['POST'])]
    public function crear(Request $request, SolicitarCorreccionFichaje $servicio): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_EMPLEADO');
        if (!$this->isCsrfTokenValid('correccion_crear', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectToRoute('web_correcciones_index');
        }

        try {
            $tenantId = $this->tenantContexto->obtenerTenantId();
            $eventoId = trim((string) $request->request->get('eventoFichajeId'));
            $evento = $this->entityManager->getRepository(EventoFichaje::class)->findOneBy(['id' => $eventoId, 'tenantId' => $tenantId]);
            if (!$evento instanceof EventoFichaje) {
                throw new \DomainException('EVENTO_ORIGINAL_NO_ENCONTRADO');
            }
            $usuario = $this->getUser();
            $servicio->ejecutar(
                $tenantId,
                $eventoId,
                trim((string) $request->request->get('motivo')),
                trim((string) $request->request->get('evidencia')) ?: null,
                $request->request->get('ocurridoEnCorregido') ? new DateTimeImmutable((string) $request->request->get('ocurridoEnCorregido')) : null,
                trim((string) $request->request->get('tipoCorregido')) ?: null,
                $usuario instanceof Usuario ? $usuario : null,
                $evento->getEmpleadoId()
            );
            $this->addFlash('success', 'Correccion solicitada.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'No se pudo crear: '.$e->getMessage());
        }

        return $this->redirectToRoute('web_correcciones_index');
    }

    #[Route('/nueva', name: 'web_correcciones_nueva', methods: ['GET'])]
    public function nueva(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EMPLEADO');

        return $this->render('web/correcciones/form.html.twig');
    }

    #[Route('/{id}/resolver', name: 'web_correcciones_resolver', methods: ['POST'])]
    public function resolver(string $id, Request $request, AprobarCorreccionFichaje $servicio): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERVISOR');
        $this->asegurarPermiso('correcciones.aprobar');
        if (!$this->isCsrfTokenValid('correccion_resolver_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectToRoute('web_correcciones_index');
        }

        try {
            $usuario = $this->getUser();
            $servicio->ejecutar($this->tenantContexto->obtenerTenantId(), $id, $usuario instanceof Usuario ? $usuario : null);
            $this->addFlash('success', 'Correccion aprobada y aplicada.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'No se pudo resolver: '.$e->getMessage());
        }

        return $this->redirectToRoute('web_correcciones_index');
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
