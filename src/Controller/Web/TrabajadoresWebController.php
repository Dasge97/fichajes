<?php

namespace App\Controller\Web;

use App\Modulo\Plataforma\Application\Tenant\TenantContexto;
use App\Modulo\Acceso\Application\Servicio\AsignarRolUsuario;
use App\Modulo\Acceso\Application\Servicio\ConfirmarResetContrasenaUsuario;
use App\Modulo\Acceso\Application\Servicio\ResolverPermisoRol;
use App\Modulo\Acceso\Application\Servicio\RevocarRolUsuario;
use App\Modulo\Acceso\Application\Servicio\SolicitarResetContrasenaUsuario;
use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Trabajadores\Application\Servicio\CambiarEstadoTrabajador;
use App\Modulo\Trabajadores\Application\Servicio\CrearTrabajador;
use App\Modulo\Trabajadores\Application\Servicio\EditarTrabajador;
use App\Modulo\Trabajadores\Application\Servicio\ListarTrabajadores;
use App\Modulo\Trabajadores\Domain\Entity\Trabajador;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/app/trabajadores')]
class TrabajadoresWebController extends AbstractController
{
    public function __construct(
        private readonly TenantContexto $tenantContexto,
        private readonly EntityManagerInterface $entityManager,
        private readonly ResolverPermisoRol $resolverPermisoRol
    ) {}

    #[Route('', name: 'web_trabajadores_index', methods: ['GET'])]
    public function index(Request $request, ListarTrabajadores $servicio): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EMPLEADO');

        $filtros = $this->extraerFiltros($request);
        $resultado = $servicio->ejecutarPaginado(
            $this->tenantContexto->obtenerTenantId(),
            $filtros['q'],
            $filtros['estado'],
            $filtros['pagina'],
            $filtros['tamano']
        );

        return $this->render('web/trabajadores/index.html.twig', [
            'trabajadores' => $resultado['items'],
            'cuentas' => $this->resolverEstadoCuentas($this->tenantContexto->obtenerTenantId(), $resultado['items']),
            'filtros' => [
                'q' => $resultado['q'],
                'estado' => $resultado['estado'],
                'pagina' => $resultado['pagina'],
                'tamano' => $resultado['tamano'],
            ],
            'paginacion' => [
                'total' => $resultado['total'],
                'pagina' => $resultado['pagina'],
                'tamano' => $resultado['tamano'],
                'totalPaginas' => $resultado['totalPaginas'],
            ],
            'puedeGestionarCuentas' => $this->puedeGestionarCuentas(),
        ]);
    }

    #[Route('/{trabajadorId}/cuenta/crear', name: 'web_trabajadores_cuenta_crear', methods: ['POST'])]
    public function crearCuenta(string $trabajadorId, Request $request, UserPasswordHasherInterface $passwordHasher, AsignarRolUsuario $asignarRol): RedirectResponse
    {
        $this->asegurarGestionCuentas();
        if (!$this->isCsrfTokenValid('trabajador_cuenta_crear_'.$trabajadorId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectConFiltros($request);
        }

        try {
            $tenantId = $this->tenantContexto->obtenerTenantId();
            $trabajador = $this->obtenerTrabajador($tenantId, $trabajadorId);
            if ($trabajador === null) {
                throw new \DomainException('TRABAJADOR_NO_ENCONTRADO');
            }
            if ($trabajador->getUsuarioId() !== null) {
                throw new \DomainException('CUENTA_YA_EXISTE');
            }

            $email = trim((string) $request->request->get('email', ''));
            if ($email === '') {
                $email = (string) $trabajador->getEmail();
            }
            if ($email === '') {
                throw new \DomainException('EMAIL_REQUERIDO');
            }
            $password = (string) $request->request->get('password', '');
            if (mb_strlen(trim($password)) < 12) {
                throw new \DomainException('PASSWORD_DEMASIADO_CORTA');
            }

            $usuario = new Usuario(bin2hex(random_bytes(16)), $tenantId, $email, ['ROLE_EMPLEADO']);
            $usuario->setPassword($passwordHasher->hashPassword($usuario, $password));
            $this->entityManager->persist($usuario);
            $trabajador->vincularUsuario($usuario->getId());
            $this->entityManager->flush();

            $asignarRol->ejecutar($tenantId, $usuario->getId(), 'trabajador', $this->actorUsuarioId());
            $this->addFlash('success', 'Cuenta creada y vinculada.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'No se pudo crear la cuenta: '.$e->getMessage());
        }

        return $this->redirectConFiltros($request);
    }

    #[Route('/{trabajadorId}/cuenta/estado', name: 'web_trabajadores_cuenta_estado', methods: ['POST'])]
    public function cambiarEstadoCuenta(string $trabajadorId, Request $request): RedirectResponse
    {
        $this->asegurarGestionCuentas();
        if (!$this->isCsrfTokenValid('trabajador_cuenta_estado_'.$trabajadorId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectConFiltros($request);
        }

        try {
            $tenantId = $this->tenantContexto->obtenerTenantId();
            $usuario = $this->resolverUsuarioTrabajador($tenantId, $trabajadorId);
            if (!$usuario instanceof Usuario) {
                throw new \DomainException('CUENTA_NO_ENCONTRADA');
            }
            $activo = ((string) $request->request->get('activo', '1')) === '1';
            $activo ? $usuario->activar() : $usuario->desactivar();
            $this->entityManager->flush();
            $this->addFlash('success', 'Estado de cuenta actualizado.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'No se pudo cambiar estado de la cuenta: '.$e->getMessage());
        }

        return $this->redirectConFiltros($request);
    }

    #[Route('/{trabajadorId}/cuenta/reset', name: 'web_trabajadores_cuenta_reset', methods: ['POST'])]
    public function resetCuenta(string $trabajadorId, Request $request, SolicitarResetContrasenaUsuario $solicitarReset, ConfirmarResetContrasenaUsuario $confirmarReset): RedirectResponse
    {
        $this->asegurarGestionCuentas();
        if (!$this->isCsrfTokenValid('trabajador_cuenta_reset_'.$trabajadorId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectConFiltros($request);
        }

        try {
            $tenantId = $this->tenantContexto->obtenerTenantId();
            $usuario = $this->resolverUsuarioTrabajador($tenantId, $trabajadorId);
            if (!$usuario instanceof Usuario) {
                throw new \DomainException('CUENTA_NO_ENCONTRADA');
            }

            $token = trim((string) $request->request->get('token', ''));
            $password = (string) $request->request->get('password', '');
            if ($token === '' || trim($password) === '') {
                $emitido = $solicitarReset->ejecutar($tenantId, $usuario->getId(), $this->actorUsuarioId());
                $this->addFlash('success', 'Token de reset emitido (15 min): '.$emitido);
            } else {
                $confirmarReset->ejecutar($tenantId, $token, $password, $this->actorUsuarioId());
                $this->addFlash('success', 'Password web reseteada con token.');
            }
        } catch (\Throwable $e) {
            $this->addFlash('error', 'No se pudo resetear password: '.$e->getMessage());
        }

        return $this->redirectConFiltros($request);
    }

    #[Route('/{trabajadorId}/cuenta/rol', name: 'web_trabajadores_cuenta_rol', methods: ['POST'])]
    public function gestionarRolCuenta(string $trabajadorId, Request $request, AsignarRolUsuario $asignarRol, RevocarRolUsuario $revocarRol): RedirectResponse
    {
        $this->asegurarGestionCuentas();
        if (!$this->isCsrfTokenValid('trabajador_cuenta_rol_'.$trabajadorId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectConFiltros($request);
        }

        try {
            $tenantId = $this->tenantContexto->obtenerTenantId();
            $usuario = $this->resolverUsuarioTrabajador($tenantId, $trabajadorId);
            if (!$usuario instanceof Usuario) {
                throw new \DomainException('CUENTA_NO_ENCONTRADA');
            }
            $rol = trim((string) $request->request->get('rol', ''));
            $accion = (string) $request->request->get('accionRol', 'asignar');
            if ($accion === 'revocar') {
                $revocarRol->ejecutar($tenantId, $usuario->getId(), $rol, $this->actorUsuarioId());
                $this->addFlash('success', 'Rol revocado.');
            } else {
                $asignarRol->ejecutar($tenantId, $usuario->getId(), $rol, $this->actorUsuarioId());
                $this->addFlash('success', 'Rol asignado.');
            }
        } catch (\Throwable $e) {
            $this->addFlash('error', 'No se pudo gestionar rol: '.$e->getMessage());
        }

        return $this->redirectConFiltros($request);
    }

    #[Route('/crear', name: 'web_trabajadores_crear', methods: ['POST'])]
    public function crear(Request $request, CrearTrabajador $servicio): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERVISOR');
        if (!$this->isCsrfTokenValid('trabajador_crear', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectConFiltros($request);
        }

        try {
            $servicio->ejecutar(
                $this->tenantContexto->obtenerTenantId(),
                trim((string) $request->request->get('trabajadorId')),
                trim((string) $request->request->get('nombre')),
                trim((string) $request->request->get('email')),
                trim((string) $request->request->get('pinKiosko'))
            );

            if (((string) $request->request->get('crearCuentaWeb', '0')) === '1') {
                $this->crearCuentaWebDesdeFormulario($request, trim((string) $request->request->get('trabajadorId')));
            }
            $this->addFlash('success', 'Trabajador creado.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'No se pudo crear: '.$e->getMessage());
        }

        return $this->redirectConFiltros($request);
    }

    #[Route('/nuevo', name: 'web_trabajadores_nuevo', methods: ['GET'])]
    public function nuevo(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERVISOR');

        return $this->render('web/trabajadores/form.html.twig', [
            'modo' => 'crear',
            'trabajador' => null,
            'filtros' => $this->extraerFiltros($request),
        ]);
    }

    #[Route('/{trabajadorId}/editar', name: 'web_trabajadores_editar', methods: ['POST'])]
    public function editar(string $trabajadorId, Request $request, EditarTrabajador $servicio): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERVISOR');
        if (!$this->isCsrfTokenValid('trabajador_editar_'.$trabajadorId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectConFiltros($request);
        }

        try {
            $servicio->ejecutar(
                $this->tenantContexto->obtenerTenantId(),
                $trabajadorId,
                trim((string) $request->request->get('nombre')),
                trim((string) $request->request->get('email')),
                trim((string) $request->request->get('pinKiosko'))
            );
            $this->addFlash('success', 'Trabajador actualizado.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'No se pudo actualizar: '.$e->getMessage());
        }

        return $this->redirectConFiltros($request);
    }

    #[Route('/{trabajadorId}/editar', name: 'web_trabajadores_editar_form', methods: ['GET'])]
    public function editarForm(string $trabajadorId, Request $request, ListarTrabajadores $servicio): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERVISOR');
        $tenantId = $this->tenantContexto->obtenerTenantId();
        $trabajador = null;
        foreach ($servicio->ejecutar($tenantId) as $item) {
            if ($item->getTrabajadorId() === $trabajadorId) {
                $trabajador = $item;
                break;
            }
        }
        if ($trabajador === null) {
            throw $this->createNotFoundException('Trabajador no encontrado');
        }

        return $this->render('web/trabajadores/form.html.twig', [
            'modo' => 'editar',
            'trabajador' => $trabajador,
            'filtros' => $this->extraerFiltros($request),
        ]);
    }

    #[Route('/{trabajadorId}/estado', name: 'web_trabajadores_estado', methods: ['POST'])]
    public function estado(string $trabajadorId, Request $request, CambiarEstadoTrabajador $servicio): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERVISOR');
        if (!$this->isCsrfTokenValid('trabajador_estado_'.$trabajadorId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectConFiltros($request);
        }

        try {
            $servicio->ejecutar(
                $this->tenantContexto->obtenerTenantId(),
                $trabajadorId,
                ((string) $request->request->get('activo')) === '1'
            );
            $this->addFlash('success', 'Estado actualizado.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'No se pudo cambiar estado: '.$e->getMessage());
        }

        return $this->redirectConFiltros($request);
    }

    /**
     * @return array{q: string, estado: string, pagina: int, tamano: int}
     */
    private function extraerFiltros(Request $request): array
    {
        return [
            'q' => trim((string) $request->query->get('q', '')),
            'estado' => (string) $request->query->get('estado', ListarTrabajadores::ESTADO_TODOS),
            'pagina' => max(1, (int) $request->query->get('pagina', 1)),
            'tamano' => (int) $request->query->get('tamano', ListarTrabajadores::TAMANOS_PERMITIDOS[0]),
        ];
    }

    private function redirectConFiltros(Request $request): RedirectResponse
    {
        return $this->redirectToRoute('web_trabajadores_index', [
            'q' => trim((string) $request->request->get('q', '')),
            'estado' => (string) $request->request->get('estado', ListarTrabajadores::ESTADO_TODOS),
            'pagina' => max(1, (int) $request->request->get('pagina', 1)),
            'tamano' => (int) $request->request->get('tamano', ListarTrabajadores::TAMANOS_PERMITIDOS[0]),
        ]);
    }

    private function puedeGestionarCuentas(): bool
    {
        $this->denyAccessUnlessGranted('ROLE_SUPERVISOR');
        $usuario = $this->getUser();
        if (!$usuario instanceof Usuario) {
            return false;
        }

        return $this->resolverPermisoRol->puede('trabajadores.roles.gestionar', $usuario->getCodigosRolTenant());
    }

    private function asegurarGestionCuentas(): void
    {
        if (!$this->puedeGestionarCuentas()) {
            throw $this->createAccessDeniedException('Permiso insuficiente para gestionar cuentas.');
        }
    }

    private function resolverUsuarioTrabajador(string $tenantId, string $trabajadorId): ?Usuario
    {
        $trabajador = $this->obtenerTrabajador($tenantId, $trabajadorId);
        if ($trabajador === null || $trabajador->getUsuarioId() === null) {
            return null;
        }

        $usuario = $this->entityManager->getRepository(Usuario::class)->find($trabajador->getUsuarioId());

        return $usuario instanceof Usuario ? $usuario : null;
    }

    private function obtenerTrabajador(string $tenantId, string $trabajadorId): ?Trabajador
    {
        $trabajador = $this->entityManager->createQueryBuilder()->select('t')->from(Trabajador::class, 't')
            ->andWhere('t.tenantId = :tenant')->andWhere('t.trabajadorId = :trabajadorId')
            ->setParameter('tenant', $tenantId)->setParameter('trabajadorId', $trabajadorId)->getQuery()->getOneOrNullResult();

        return $trabajador instanceof Trabajador ? $trabajador : null;
    }

    /** @param object[] $trabajadores */
    private function resolverEstadoCuentas(string $tenantId, array $trabajadores): array
    {
        $estado = [];
        foreach ($trabajadores as $trabajador) {
            $trabajadorId = $trabajador->getTrabajadorId();
            $estado[$trabajadorId] = ['tieneCuenta' => false, 'activo' => null, 'roles' => []];
            $usuario = $this->resolverUsuarioTrabajador($tenantId, $trabajadorId);
            if (!$usuario instanceof Usuario) {
                continue;
            }
            $estado[$trabajadorId] = [
                'tieneCuenta' => true,
                'activo' => $usuario->estaActivo(),
                'roles' => $usuario->getCodigosRolTenant(),
            ];
        }

        return $estado;
    }

    private function actorUsuarioId(): ?string
    {
        $actor = $this->getUser();

        return $actor instanceof Usuario ? $actor->getId() : null;
    }

    private function crearCuentaWebDesdeFormulario(Request $request, string $trabajadorId): void
    {
        $tenantId = $this->tenantContexto->obtenerTenantId();
        $trabajador = $this->obtenerTrabajador($tenantId, $trabajadorId);
        if ($trabajador === null) {
            throw new \DomainException('TRABAJADOR_NO_ENCONTRADO');
        }
        if ($trabajador->getUsuarioId() !== null) {
            throw new \DomainException('CUENTA_YA_EXISTE');
        }

        $email = trim((string) $request->request->get('emailCuentaWeb', ''));
        if ($email === '') {
            $email = (string) $trabajador->getEmail();
        }
        if ($email === '') {
            throw new \DomainException('EMAIL_REQUERIDO');
        }

        $password = (string) $request->request->get('passwordCuentaWeb', '');
        if (mb_strlen(trim($password)) < 12) {
            throw new \DomainException('PASSWORD_DEMASIADO_CORTA');
        }
        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
            throw new \DomainException('PASSWORD_COMPLEJIDAD_INSUFICIENTE');
        }

        $hasher = $this->container->get(UserPasswordHasherInterface::class);
        $asignarRol = $this->container->get(AsignarRolUsuario::class);

        $usuario = new Usuario(bin2hex(random_bytes(16)), $tenantId, $email, ['ROLE_EMPLEADO']);
        $usuario->setPassword($hasher->hashPassword($usuario, $password));
        $this->entityManager->persist($usuario);
        $trabajador->vincularUsuario($usuario->getId());
        $this->entityManager->flush();
        $asignarRol->ejecutar($tenantId, $usuario->getId(), 'trabajador', $this->actorUsuarioId());
    }
}
