<?php

namespace App\Controller\Web;

use App\Modulo\Plataforma\Application\Tenant\TenantContexto;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/app/auditoria')]
class AuditoriaWebController extends AbstractController
{
    public function __construct(private readonly TenantContexto $tenantContexto, private readonly Connection $connection) {}

    #[Route('', name: 'web_auditoria_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERVISOR');
        $tenantId = $this->tenantContexto->obtenerTenantId();

        $accion = trim((string) $request->query->get('accion'));
        $desde = trim((string) $request->query->get('desde'));
        $hasta = trim((string) $request->query->get('hasta'));
        $tamano = max(10, min(50, (int) $request->query->get('tamano', 20)));
        $pagina = max(1, (int) $request->query->get('pagina', 1));

        $sqlBase = ' FROM registro_auditoria WHERE tenantId = :tenant';
        $params = ['tenant' => $tenantId];
        if ($accion !== '') {
            $sqlBase .= ' AND accion LIKE :accion';
            $params['accion'] = '%'.$accion.'%';
        }
        if ($desde !== '') {
            $sqlBase .= ' AND creadoEn >= :desde';
            $params['desde'] = $desde.' 00:00:00';
        }
        if ($hasta !== '') {
            $sqlBase .= ' AND creadoEn <= :hasta';
            $params['hasta'] = $hasta.' 23:59:59';
        }
        $total = (int) $this->connection->fetchOne('SELECT COUNT(*)'.$sqlBase, $params);
        $totalPaginas = max(1, (int) ceil($total / $tamano));
        $pagina = min($pagina, $totalPaginas);
        $offset = ($pagina - 1) * $tamano;

        $items = $this->connection->fetchAllAssociative('SELECT accion, antes, despues, creadoEn'.$sqlBase.' ORDER BY creadoEn DESC LIMIT '.$tamano.' OFFSET '.$offset, $params);

        return $this->render('web/auditoria/index.html.twig', [
            'items' => $items,
            'filtros' => ['accion' => $accion, 'desde' => $desde, 'hasta' => $hasta, 'tamano' => $tamano],
            'paginacion' => ['total' => $total, 'pagina' => $pagina, 'totalPaginas' => $totalPaginas],
        ]);
    }
}
