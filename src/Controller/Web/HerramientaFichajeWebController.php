<?php

namespace App\Controller\Web;

use App\Modulo\Fichajes\Application\Servicio\RegistrarEventoFichaje;
use App\Modulo\Fichajes\Application\Servicio\ControlAccesoHerramientaFichaje;
use App\Modulo\Fichajes\Infrastructure\Repository\EventoFichajeRepository;
use App\Modulo\Trabajadores\Infrastructure\Repository\TrabajadorRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/herramienta-fichaje')]
class HerramientaFichajeWebController extends AbstractController
{
    private const SESION_KEY = 'herramienta_fichaje';
    private const MODO_NORMAL = 'normal';
    private const MODO_KIOSKO = 'kiosko';

    public function __construct(
        private readonly TrabajadorRepository $trabajadores,
        private readonly EventoFichajeRepository $eventos,
        private readonly RegistrarEventoFichaje $registrador,
        private readonly ControlAccesoHerramientaFichaje $controlAcceso,
        private readonly int $timeoutInactividadSegundos,
        private readonly string $tenantKioskoPorDefecto
    ) {}

    #[Route('', name: 'web_herramienta_fichaje', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->renderPantalla($request, self::MODO_NORMAL);
    }

    #[Route('/kiosko', name: 'web_herramienta_fichaje_kiosko', methods: ['GET'])]
    public function kiosko(Request $request): Response
    {
        return $this->renderPantalla($request, self::MODO_KIOSKO);
    }

    #[Route('/kiosko/rapida', name: 'web_herramienta_fichaje_kiosko_rapida', methods: ['POST'])]
    public function kioskoRapida(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('herramienta_fichaje_kiosko_rapida', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectToRoute('web_herramienta_fichaje_kiosko');
        }

        $accion = (string) $request->request->get('accion');
        $clave = trim((string) $request->request->get('claveAcceso'));
        $trabajadorId = trim((string) $request->request->get('trabajadorId'));
        $ip = (string) $request->getClientIp();
        $tenantId = $this->resolverTenantKiosko($request);

        if ($clave === '') {
            $this->addFlash('error', 'Debes indicar el codigo de fichaje.');

            return $this->redirectToRoute('web_herramienta_fichaje_kiosko');
        }

        $trabajador = $trabajadorId !== ''
            ? $this->trabajadores->buscarActivoPorCredenciales($trabajadorId, $clave)
            : $this->trabajadores->buscarActivoPorClave($tenantId, $clave);

        if ($trabajador === null) {
            $idControl = $trabajadorId !== '' ? $trabajadorId : 'codigo-directo';
            $nuevoBloqueo = $this->controlAcceso->registrarFallo($idControl, $ip);
            if ($nuevoBloqueo !== null) {
                $this->addFlash('error', sprintf('Has superado el limite de intentos. Bloqueado hasta %s.', $nuevoBloqueo->format('H:i:s')));
            } else {
                $this->addFlash('error', 'Codigo invalido o trabajador inactivo.');
            }

            return $this->redirectToRoute('web_herramienta_fichaje_kiosko');
        }

        $this->controlAcceso->limpiarIntentos($trabajador->getTrabajadorId(), $ip);

        $tipo = $this->resolverTipoDesdeAccion($trabajador->getTenantId(), $trabajador->getTrabajadorId(), $accion);
        if ($tipo === null) {
            $this->addFlash('error', 'Accion no valida para el estado actual del trabajador.');

            return $this->redirectToRoute('web_herramienta_fichaje_kiosko');
        }

        try {
            $this->registrador->ejecutar(
                $trabajador->getTenantId(),
                $trabajador->getTrabajadorId(),
                $tipo,
                new DateTimeImmutable(),
                'marcar',
                uniqid('kiosko-evt-', true)
            );
            $this->addFlash('success', sprintf('Accion registrada para %s.', $trabajador->getNombre()));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'No se pudo registrar la accion: '.$e->getMessage());
        }

        return $this->redirectToRoute('web_herramienta_fichaje_kiosko');
    }

    #[Route('/kiosko/validar-pin', name: 'web_herramienta_fichaje_kiosko_validar_pin', methods: ['POST'])]
    public function kioskoValidarPin(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('herramienta_fichaje_kiosko_validar_pin', (string) $request->request->get('_token'))) {
            return new JsonResponse(['ok' => false, 'mensaje' => 'Token CSRF invalido.'], 400);
        }

        $clave = trim((string) $request->request->get('claveAcceso'));
        $ip = (string) $request->getClientIp();
        $tenantId = $this->resolverTenantKiosko($request);

        if ($clave === '') {
            return new JsonResponse(['ok' => false, 'mensaje' => 'Debes introducir el codigo.'], 422);
        }

        $bloqueadoHasta = $this->controlAcceso->estaBloqueado('codigo-directo', $ip);
        if ($bloqueadoHasta !== null) {
            return new JsonResponse(['ok' => false, 'mensaje' => sprintf('Acceso bloqueado hasta %s.', $bloqueadoHasta->format('H:i:s'))], 429);
        }

        $trabajador = $this->trabajadores->buscarActivoPorClave($tenantId, $clave);
        if ($trabajador === null) {
            $nuevoBloqueo = $this->controlAcceso->registrarFallo('codigo-directo', $ip);
            if ($nuevoBloqueo !== null) {
                return new JsonResponse(['ok' => false, 'mensaje' => sprintf('Has superado el limite de intentos. Bloqueado hasta %s.', $nuevoBloqueo->format('H:i:s'))], 429);
            }

            return new JsonResponse(['ok' => false, 'mensaje' => 'Codigo invalido.'], 401);
        }

        $estado = $this->resolverEstado($tenantId, $trabajador->getTrabajadorId());
        $acciones = $this->accionesPorEstado($estado);

        return new JsonResponse([
            'ok' => true,
            'trabajadorId' => $trabajador->getTrabajadorId(),
            'trabajadorNombre' => $trabajador->getNombre(),
            'estado' => $estado,
            'acciones' => $acciones,
        ]);
    }

    #[Route('/iniciar', name: 'web_herramienta_fichaje_iniciar', methods: ['POST'])]
    public function iniciar(Request $request): RedirectResponse
    {
        $modo = $this->resolverModo((string) $request->request->get('modo'));

        if (!$this->isCsrfTokenValid('herramienta_fichaje_iniciar', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirigirSegunModo($modo);
        }

        $trabajadorId = trim((string) $request->request->get('trabajadorId'));
        $clave = trim((string) $request->request->get('claveAcceso'));
        $ip = (string) $request->getClientIp();

        $bloqueadoHasta = $this->controlAcceso->estaBloqueado($trabajadorId, $ip);
        if ($bloqueadoHasta !== null) {
            $this->addFlash('error', sprintf('Acceso temporalmente bloqueado por seguridad. Reintenta despues de %s.', $bloqueadoHasta->format('H:i:s')));

            return $this->redirigirSegunModo($modo);
        }

        $trabajador = $this->trabajadores->buscarActivoPorCredenciales($trabajadorId, $clave);
        if ($trabajador === null) {
            $nuevoBloqueo = $this->controlAcceso->registrarFallo($trabajadorId, $ip);
            if ($nuevoBloqueo !== null) {
                $this->addFlash('error', sprintf('Has superado el limite de intentos. Bloqueado hasta %s.', $nuevoBloqueo->format('H:i:s')));

                return $this->redirigirSegunModo($modo);
            }
            $this->addFlash('error', 'Credenciales invalidas o trabajador inactivo.');

            return $this->redirigirSegunModo($modo);
        }

        $this->controlAcceso->limpiarIntentos($trabajadorId, $ip);

        $estadoActual = $this->resolverEstado($trabajador->getTenantId(), $trabajador->getTrabajadorId());
        if (in_array($estadoActual, ['en_jornada', 'en_pausa'], true)) {
            $this->guardarContextoSesion($request, $trabajador->getTenantId(), $trabajador->getTrabajadorId());
            $this->addFlash('success', 'Sesion de fichaje recuperada.');

            return $this->redirigirSegunModo($modo);
        }

        try {
            $this->registrador->ejecutar(
                $trabajador->getTenantId(),
                $trabajador->getTrabajadorId(),
                'clock-in',
                new DateTimeImmutable(),
                'marcar',
                uniqid('tool-in-', true)
            );
        } catch (\Throwable $e) {
            $this->addFlash('error', 'No se pudo iniciar fichaje: '.$e->getMessage());

            return $this->redirigirSegunModo($modo);
        }

        $this->guardarContextoSesion($request, $trabajador->getTenantId(), $trabajador->getTrabajadorId());
        $this->addFlash('success', 'Jornada iniciada.');

        return $this->redirigirSegunModo($modo);
    }

    #[Route('/accion', name: 'web_herramienta_fichaje_accion', methods: ['POST'])]
    public function accion(Request $request): RedirectResponse
    {
        $modo = $this->resolverModo((string) $request->request->get('modo'));

        if (!$this->isCsrfTokenValid('herramienta_fichaje_accion', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirigirSegunModo($modo);
        }

        $contexto = $this->obtenerContextoActivo($request);
        if (!is_array($contexto)) {
            $this->addFlash('error', 'Primero debes identificarte e iniciar fichaje.');

            return $this->redirigirSegunModo($modo);
        }

        $estado = $this->resolverEstado($contexto['tenantId'], $contexto['trabajadorId']);
        $accion = (string) $request->request->get('accion');
        $tipo = match ([$estado, $accion]) {
            ['en_jornada', 'pausa'] => 'pause-start',
            ['en_jornada', 'finalizar'] => 'clock-out',
            ['en_pausa', 'reanudar'] => 'pause-end',
            ['en_pausa', 'finalizar'] => 'clock-out',
            ['finalizada', 'iniciar_nuevo'] => 'clock-in',
            default => null,
        };

        if ($tipo === null) {
            $this->addFlash('error', 'Accion no valida para el estado actual.');

            return $this->redirigirSegunModo($modo);
        }

        try {
            $this->registrador->ejecutar(
                $contexto['tenantId'],
                $contexto['trabajadorId'],
                $tipo,
                new DateTimeImmutable(),
                'marcar',
                uniqid('tool-evt-', true)
            );
            $this->addFlash('success', 'Accion registrada.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'No se pudo registrar la accion: '.$e->getMessage());
        }

        return $this->redirigirSegunModo($modo);
    }

    #[Route('/cerrar', name: 'web_herramienta_fichaje_cerrar', methods: ['POST'])]
    public function cerrar(Request $request): RedirectResponse
    {
        $modo = $this->resolverModo((string) $request->request->get('modo'));

        if ($this->isCsrfTokenValid('herramienta_fichaje_cerrar', (string) $request->request->get('_token'))) {
            $request->getSession()->remove(self::SESION_KEY);
            $this->addFlash('success', 'Sesion de fichaje cerrada.');
        }

        return $this->redirigirSegunModo($modo);
    }

    private function renderPantalla(Request $request, string $modo): Response
    {
        $contexto = $this->obtenerContextoActivo($request);

        $trabajador = null;
        $estado = 'sin_iniciar';
        if (is_array($contexto)) {
            $trabajador = $this->trabajadores->buscarPorTenantYTrabajadorId($contexto['tenantId'], $contexto['trabajadorId']);
            if ($trabajador !== null) {
                $estado = $this->resolverEstado($contexto['tenantId'], $contexto['trabajadorId']);
            } else {
                $request->getSession()->remove(self::SESION_KEY);
            }
        }

        $template = $modo === self::MODO_KIOSKO
            ? 'web/herramienta-fichaje/kiosko.html.twig'
            : 'web/herramienta-fichaje/index.html.twig';

        return $this->render($template, [
            'estado' => $estado,
            'trabajador' => $trabajador,
            'modo' => $modo,
            'q' => trim((string) $request->query->get('q', '')),
            'trabajadoresKiosko' => $modo === self::MODO_KIOSKO ? $this->listarTrabajadoresKiosko($request) : [],
        ]);
    }

    /**
     * @return array<int, array{trabajador: object, estado: string, acciones: array<int, string>}>
     */
    private function listarTrabajadoresKiosko(Request $request): array
    {
        $tenantId = $this->resolverTenantKiosko($request);
        $q = mb_strtolower(trim((string) $request->query->get('q', '')));
        $lista = [];

        foreach ($this->trabajadores->listarActivosPorTenant($tenantId) as $trabajador) {
            $texto = mb_strtolower($trabajador->getTrabajadorId().' '.$trabajador->getNombre().' '.($trabajador->getEmail() ?? ''));
            if ($q !== '' && !str_contains($texto, $q)) {
                continue;
            }
            $estadoTrabajador = $this->resolverEstado($tenantId, $trabajador->getTrabajadorId());
            $lista[] = [
                'trabajador' => $trabajador,
                'estado' => $estadoTrabajador,
                'acciones' => $this->accionesPorEstado($estadoTrabajador),
            ];
        }

        return $lista;
    }

    private function obtenerContextoActivo(Request $request): ?array
    {
        $sesion = $request->getSession();
        $contexto = $sesion->get(self::SESION_KEY);
        if (!is_array($contexto)) {
            return null;
        }

        $ultimoAcceso = (int) ($contexto['ultimoAcceso'] ?? 0);
        $ahora = time();
        if ($ultimoAcceso > 0 && ($ahora - $ultimoAcceso) > $this->timeoutInactividadSegundos) {
            $sesion->remove(self::SESION_KEY);
            $this->addFlash('info', 'La sesion de la herramienta expiro por inactividad. Debes identificarte de nuevo.');

            return null;
        }

        $contexto['ultimoAcceso'] = $ahora;
        $sesion->set(self::SESION_KEY, $contexto);

        return $contexto;
    }

    private function guardarContextoSesion(Request $request, string $tenantId, string $trabajadorId): void
    {
        $request->getSession()->set(self::SESION_KEY, [
            'tenantId' => $tenantId,
            'trabajadorId' => $trabajadorId,
            'ultimoAcceso' => time(),
        ]);
    }

    private function resolverModo(string $modo): string
    {
        return $modo === self::MODO_KIOSKO ? self::MODO_KIOSKO : self::MODO_NORMAL;
    }

    private function redirigirSegunModo(string $modo): RedirectResponse
    {
        return $this->redirectToRoute($modo === self::MODO_KIOSKO ? 'web_herramienta_fichaje_kiosko' : 'web_herramienta_fichaje');
    }

    private function resolverEstado(string $tenantId, string $trabajadorId): string
    {
        $ultimo = $this->eventos->ultimoEventoDelDia($tenantId, $trabajadorId, new DateTimeImmutable());
        if ($ultimo === null) {
            return 'sin_iniciar';
        }

        return match ($ultimo->getTipo()) {
            'clock-in', 'pause-end' => 'en_jornada',
            'pause-start' => 'en_pausa',
            'clock-out' => 'finalizada',
            default => 'sin_iniciar',
        };
    }

    /** @return array<int, string> */
    private function accionesPorEstado(string $estado): array
    {
        return match ($estado) {
            'sin_iniciar', 'finalizada' => ['iniciar_nuevo'],
            'en_jornada' => ['pausa', 'finalizar'],
            'en_pausa' => ['reanudar', 'finalizar'],
            default => [],
        };
    }

    private function resolverTipoDesdeAccion(string $tenantId, string $trabajadorId, string $accion): ?string
    {
        $estado = $this->resolverEstado($tenantId, $trabajadorId);

        return match ([$estado, $accion]) {
            ['sin_iniciar', 'iniciar_nuevo'], ['finalizada', 'iniciar_nuevo'] => 'clock-in',
            ['en_jornada', 'pausa'] => 'pause-start',
            ['en_jornada', 'finalizar'] => 'clock-out',
            ['en_pausa', 'reanudar'] => 'pause-end',
            ['en_pausa', 'finalizar'] => 'clock-out',
            default => null,
        };
    }

    private function resolverTenantKiosko(Request $request): string
    {
        $contexto = $request->getSession()->get(self::SESION_KEY);
        if (is_array($contexto) && isset($contexto['tenantId']) && is_string($contexto['tenantId']) && trim($contexto['tenantId']) !== '') {
            return $contexto['tenantId'];
        }

        return $this->tenantKioskoPorDefecto;
    }
}
