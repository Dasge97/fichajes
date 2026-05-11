<?php

namespace App\Controller\Web;

use App\Modulo\Fichajes\Application\Servicio\RegistrarEventoFichaje;
use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Plataforma\Application\Tenant\TenantContexto;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/app/fichajes')]
class FichajesWebController extends AbstractController
{
    public function __construct(private readonly TenantContexto $tenantContexto, private readonly Connection $connection) {}

    #[Route('', name: 'web_fichajes_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EMPLEADO');
        $tenantId = $this->tenantContexto->obtenerTenantId();

        $q = trim((string) $request->query->get('q', ''));
        $tamano = max(10, min(50, (int) $request->query->get('tamano', 20)));
        $pagina = max(1, (int) $request->query->get('pagina', 1));
        $where = ' WHERE tenantId = :tenant';
        $params = ['tenant' => $tenantId];
        if ($q !== '') {
            $where .= ' AND (empleadoId LIKE :q OR tipo LIKE :q OR estadoCumplimiento LIKE :q)';
            $params['q'] = '%'.$q.'%';
        }
        $total = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM evento_fichaje'.$where, $params);
        $totalPaginas = max(1, (int) ceil($total / $tamano));
        $pagina = min($pagina, $totalPaginas);
        $offset = ($pagina - 1) * $tamano;
        $eventos = $this->connection->fetchAllAssociative(
            'SELECT empleadoId, tipo, ocurridoEn, estadoCumplimiento, motivoDesvio FROM evento_fichaje'.$where.' ORDER BY ocurridoEn DESC LIMIT '.$tamano.' OFFSET '.$offset,
            $params
        );
        $trabajadores = $this->connection->fetchAllAssociative(
            'SELECT trabajadorId, nombre FROM trabajador WHERE tenantId = :tenant AND activo = 1 ORDER BY trabajadorId',
            ['tenant' => $tenantId]
        );

        return $this->render('web/fichajes/index.html.twig', [
            'eventos' => $eventos,
            'trabajadores' => $trabajadores,
            'filtros' => ['q' => $q, 'tamano' => $tamano],
            'paginacion' => ['total' => $total, 'pagina' => $pagina, 'totalPaginas' => $totalPaginas],
        ]);
    }

    #[Route('/nuevo', name: 'web_fichajes_nuevo', methods: ['GET'])]
    public function nuevo(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EMPLEADO');
        $tenantId = $this->tenantContexto->obtenerTenantId();
        $trabajadores = $this->connection->fetchAllAssociative(
            'SELECT trabajadorId, nombre FROM trabajador WHERE tenantId = :tenant AND activo = 1 ORDER BY trabajadorId',
            ['tenant' => $tenantId]
        );

        return $this->render('web/fichajes/form.html.twig', ['trabajadores' => $trabajadores]);
    }

    #[Route('/registrar', name: 'web_fichajes_registrar', methods: ['POST'])]
    public function registrar(Request $request, RegistrarEventoFichaje $servicio): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_EMPLEADO');

        if (!$this->isCsrfTokenValid('fichaje_registrar', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectToRoute('web_fichajes_index');
        }

        $empleadoId = trim((string) $request->request->get('trabajadorId'));
        $tipo = trim((string) $request->request->get('tipo'));
        $ocurridoEn = trim((string) $request->request->get('ocurridoEn'));

        if ($empleadoId === '' || $tipo === '' || $ocurridoEn === '') {
            $this->addFlash('error', 'Completa empleado, tipo y fecha.');

            return $this->redirectToRoute('web_fichajes_index');
        }

        try {
            $usuario = $this->getUser();
            $servicio->ejecutar(
                $this->tenantContexto->obtenerTenantId(),
                $empleadoId,
                $tipo,
                new DateTimeImmutable($ocurridoEn),
                'bloquear',
                uniqid('web-fichaje-', true),
                $usuario instanceof Usuario ? $usuario : null
            );
            $this->addFlash('success', 'Fichaje registrado.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'No se pudo registrar: '.$e->getMessage());
        }

        return $this->redirectToRoute('web_fichajes_index');
    }
}
