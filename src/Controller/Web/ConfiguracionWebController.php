<?php

namespace App\Controller\Web;

use App\Modulo\Plataforma\Application\Tenant\TenantContexto;
use App\Modulo\Plataforma\Domain\Entity\AjusteTenant;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/app/configuracion')]
class ConfiguracionWebController extends AbstractController
{
    public function __construct(
        private readonly TenantContexto $tenantContexto,
        private readonly Connection $connection,
        private readonly EntityManagerInterface $entityManager
    ) {}

    #[Route('', name: 'app_configuracion', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $tenantId = $this->tenantContexto->obtenerTenantId();

        $usuarios = $this->connection->fetchAllAssociative(
            'SELECT u.id, u.email, u.activo, GROUP_CONCAT(r.codigo) AS roles
             FROM usuario u
             LEFT JOIN usuario_rol ur ON ur.usuario_id = u.id
             LEFT JOIN rol r ON r.id = ur.rol_id
             WHERE u.tenantId = :tenant
             GROUP BY u.id, u.email, u.activo
             ORDER BY u.email',
            ['tenant' => $tenantId]
        );

        $roles = $this->connection->fetchAllAssociative(
            'SELECT codigo, nombre, descripcion FROM rol WHERE esSistema = 1 ORDER BY codigo'
        );

        $ajuste = $this->entityManager->find(AjusteTenant::class, $tenantId);
        $ajustes = $ajuste instanceof AjusteTenant ? $ajuste->getDatos() : [];

        return $this->render('web/configuracion/index.html.twig', [
            'tenantId' => $tenantId,
            'usuarios' => $usuarios,
            'roles' => $roles,
            'ajustes' => $ajustes,
        ]);
    }

    #[Route('/guardar', name: 'app_configuracion_guardar', methods: ['POST'])]
    public function guardar(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('configuracion_guardar', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF inválido.');

            return $this->redirectToRoute('app_configuracion');
        }

        $tenantId = $this->tenantContexto->obtenerTenantId();
        $ajuste = $this->entityManager->find(AjusteTenant::class, $tenantId);
        if (!$ajuste instanceof AjusteTenant) {
            $ajuste = new AjusteTenant($tenantId);
            $this->entityManager->persist($ajuste);
        }

        $datos = $ajuste->getDatos();
        $campos = ['empresa_nombre', 'empresa_logo', 'color_primario', 'color_sidebar', 'zona_horaria'];
        foreach ($campos as $campo) {
            $valor = trim((string) $request->request->get($campo, ''));
            if ($valor !== '') {
                $datos[$campo] = $valor;
            } else {
                unset($datos[$campo]);
            }
        }
        $ajuste->setDatos($datos);
        $this->entityManager->flush();

        $this->addFlash('success', 'Configuración guardada.');

        return $this->redirectToRoute('app_configuracion');
    }
}
