<?php

namespace App\Tests\Web;

use App\Modulo\Acceso\Domain\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class OperativoFrontendTest extends WebTestCase
{
    private $client;

    private const ADMIN_EMAIL = 'admin@test.local';
    private const ADMIN_PASSWORD = 'admin123';
    private const EMPLEADO_EMAIL = 'empleado@test.local';
    private const EMPLEADO_PASSWORD = 'empleado123';

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        putenv('HERRAMIENTA_FICHAJE_TIMEOUT_INACTIVIDAD_SEGUNDOS=1');
        $_ENV['HERRAMIENTA_FICHAJE_TIMEOUT_INACTIVIDAD_SEGUNDOS'] = '1';
        $_SERVER['HERRAMIENTA_FICHAJE_TIMEOUT_INACTIVIDAD_SEGUNDOS'] = '1';
        $this->client = static::createClient();
        $container = static::getContainer();

        $em = $container->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        $em->getConnection()->executeStatement('CREATE TABLE IF NOT EXISTS login_bloqueo_web (id VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, email VARCHAR(180) NOT NULL, ip VARCHAR(64) NOT NULL, intentos INTEGER NOT NULL, bloqueado_hasta DATETIME DEFAULT NULL, actualizado_en DATETIME NOT NULL, PRIMARY KEY(id))');
        $em->getConnection()->executeStatement('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_LOGIN_BLOQUEO_SCOPE ON login_bloqueo_web (tenant_id, email, ip)');

        $this->crearUsuario($em, $container->get(UserPasswordHasherInterface::class), self::ADMIN_EMAIL, self::ADMIN_PASSWORD, ['ROLE_SUPERVISOR', 'ROLE_EMPLEADO']);
        $this->crearUsuario($em, $container->get(UserPasswordHasherInterface::class), self::EMPLEADO_EMAIL, self::EMPLEADO_PASSWORD, ['ROLE_EMPLEADO']);

        $this->client->disableReboot();
    }

    public function testLoginNavegaAlDashboard(): void
    {
        $client = $this->createLoggedClient(self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

        self::assertSelectorTextContains('h1', 'Panel operativo');
        self::assertSelectorTextContains('main', 'Fichajes hoy');
        self::assertSelectorTextContains('main', 'Agenda proxima');
    }

    public function testFlujoBaseHorarioYFichaje(): void
    {
        $client = $this->createLoggedClient(self::ADMIN_EMAIL, self::ADMIN_PASSWORD);
        $this->crearTrabajador($client, 'EMP-1', 'Empleado Demo');

        $crawler = $client->request('GET', '/app/horarios/nuevo');
        $tokenCrear = $crawler->filter('form[action="/app/horarios/crear"] input[name="_token"]')->attr('value');

        $client->request('POST', '/app/horarios/crear', [
            '_token' => $tokenCrear,
            'nombre' => 'Turno Manana',
            'tramos' => "1,09:00,18:00\n2,09:00,18:00",
        ]);
        self::assertResponseRedirects('/app/horarios');

        $crawler = $client->request('GET', '/app/horarios');
        self::assertSelectorTextContains('table', 'Turno Manana');

        $tokenFichaje = $client->request('GET', '/app/fichajes/nuevo')->filter('form[action="/app/fichajes/registrar"] input[name="_token"]')->attr('value');
        $client->request('POST', '/app/fichajes/registrar', [
            '_token' => $tokenFichaje,
            'trabajadorId' => 'EMP-1',
            'tipo' => 'clock-in',
            'ocurridoEn' => '2026-05-08T09:00',
        ]);
        self::assertResponseRedirects('/app/fichajes');
        $client->followRedirect();
        self::assertSelectorTextContains('table', 'EMP-1');
    }

    public function testFlujoBaseAusenciaAprobadaPorSupervisor(): void
    {
        $admin = $this->createLoggedClient(self::ADMIN_EMAIL, self::ADMIN_PASSWORD);
        $this->crearTrabajador($admin, 'EMP-1', 'Empleado Uno');

        $empleado = $this->createLoggedClient(self::EMPLEADO_EMAIL, self::EMPLEADO_PASSWORD);
        $crawler = $empleado->request('GET', '/app/ausencias/nueva');
        $token = $crawler->filter('form[action="/app/ausencias/solicitar"] input[name="_token"]')->attr('value');

        $empleado->request('POST', '/app/ausencias/solicitar', [
            '_token' => $token,
            'trabajadorId' => 'EMP-1',
            'tipo' => 'vacaciones',
            'fechaInicio' => '2026-05-10',
            'fechaFin' => '2026-05-12',
        ]);
        self::assertResponseRedirects('/app/ausencias');

        $supervisor = $this->createLoggedClient(self::ADMIN_EMAIL, self::ADMIN_PASSWORD);
        $crawler = $supervisor->request('GET', '/app/ausencias');
        $form = $crawler->filter('form[action*="/aprobar"]')->first();
        $action = $form->attr('action');
        $tokenAprobar = $form->filter('input[name="_token"]')->attr('value');

        $supervisor->request('POST', $action, ['_token' => $tokenAprobar]);
        self::assertResponseRedirects('/app/ausencias');
        $supervisor->followRedirect();
        self::assertSelectorTextContains('table', 'aprobada');
    }

    public function testModuloTrabajadoresYSelectoresOperativos(): void
    {
        $client = $this->createLoggedClient(self::ADMIN_EMAIL, self::ADMIN_PASSWORD);
        $crawler = $client->request('GET', '/app/trabajadores/nuevo');
        $tokenCrear = $crawler->filter('form[action="/app/trabajadores/crear"] input[name="_token"]')->attr('value');

        $client->request('POST', '/app/trabajadores/crear', [
            '_token' => $tokenCrear,
            'trabajadorId' => 'EMP-900',
            'nombre' => 'Trabajador QA',
            'email' => 'qa@test.local',
        ]);
        self::assertResponseRedirects('/app/trabajadores?q=&estado=todos&pagina=1&tamano=10');

        $client->request('GET', '/app/trabajadores');
        self::assertSelectorTextContains('table', 'EMP-900');
        self::assertSelectorTextContains('table', 'Trabajador QA');

        $client->request('GET', '/app/fichajes/nuevo');
        self::assertSelectorExists('select[name="trabajadorId"] option[value="EMP-900"]');

        $client->request('GET', '/app/horarios/asignaciones/nueva');
        self::assertSelectorExists('select[name="trabajadorId"] option[value="EMP-900"]');

        $client->request('GET', '/app/ausencias/nueva');
        self::assertSelectorExists('select[name="trabajadorId"] option[value="EMP-900"]');
    }

    public function testTrabajadoresFiltrosYPaginacionMantienenContextoTrasPost(): void
    {
        $client = $this->createLoggedClient(self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

        for ($i = 1; $i <= 12; ++$i) {
            $id = sprintf('EMP-%03d', $i);
            $this->crearTrabajador($client, $id, 'Trabajador '.$i);
        }

        $crawler = $client->request('GET', '/app/trabajadores/nuevo');
        $tokenCrear = $crawler->filter('form[action="/app/trabajadores/crear"] input[name="_token"]')->attr('value');
        $client->request('POST', '/app/trabajadores/crear', [
            '_token' => $tokenCrear,
            'trabajadorId' => 'EMP-QA',
            'nombre' => 'Nombre Buscable',
            'email' => 'buscable@test.local',
            'q' => '',
            'estado' => 'todos',
            'pagina' => '1',
            'tamano' => '10',
        ]);
        self::assertResponseRedirects('/app/trabajadores?q=&estado=todos&pagina=1&tamano=10');

        $client->request('GET', '/app/trabajadores?tamano=10&pagina=1');
        self::assertSelectorTextContains('.pagination', 'Pagina 1 / 2');
        self::assertSelectorTextContains('table', 'EMP-001');
        self::assertStringNotContainsString('EMP-011', (string) $client->getResponse()->getContent());

        $client->request('GET', '/app/trabajadores?tamano=10&pagina=2');
        self::assertSelectorTextContains('table', 'EMP-011');
        self::assertStringNotContainsString('EMP-001', (string) $client->getResponse()->getContent());

        $client->request('GET', '/app/trabajadores?q=EMP-003');
        self::assertSelectorTextContains('table', 'EMP-003');
        self::assertStringNotContainsString('EMP-004', (string) $client->getResponse()->getContent());

        $client->request('GET', '/app/trabajadores?q=Nombre+Buscable');
        self::assertSelectorTextContains('table', 'EMP-QA');

        $client->request('GET', '/app/trabajadores?q=buscable%40test.local');
        self::assertSelectorTextContains('table', 'EMP-QA');

        $crawler = $client->request('GET', '/app/trabajadores?q=EMP-003&estado=todos&pagina=1&tamano=10');
        $tokenEstado = $crawler->filter('form[action="/app/trabajadores/EMP-003/estado"] input[name="_token"]')->attr('value');

        $client->request('POST', '/app/trabajadores/EMP-003/estado', [
            '_token' => $tokenEstado,
            'activo' => '0',
            'q' => 'EMP-003',
            'estado' => 'todos',
            'pagina' => '1',
            'tamano' => '10',
        ]);
        self::assertResponseRedirects('/app/trabajadores?q=EMP-003&estado=todos&pagina=1&tamano=10');

        $client->request('GET', '/app/trabajadores?estado=inactivos');
        self::assertSelectorTextContains('table', 'EMP-003');
        self::assertStringNotContainsString('EMP-001', (string) $client->getResponse()->getContent());
    }

    public function testEmpleadoNoPuedeGestionarTrabajadoresEnWeb(): void
    {
        $client = $this->createLoggedClient(self::EMPLEADO_EMAIL, self::EMPLEADO_PASSWORD);
        $client->request('POST', '/app/trabajadores/crear', [
            '_token' => 'token-invalido',
            'trabajadorId' => 'EMP-DENY',
            'nombre' => 'Sin permiso',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAnonimoEsRedirigidoALoginEnRutasProtegidas(): void
    {
        $client = $this->client;
        $client->restart();
        $client->request('GET', '/app/trabajadores');

        self::assertResponseRedirects('/login');
    }

    public function testHerramientaBloqueaTrasIntentosFallidos(): void
    {
        $admin = $this->createLoggedClient(self::ADMIN_EMAIL, self::ADMIN_PASSWORD);
        $this->crearTrabajador($admin, 'EMP-BRUTE', 'Brute Worker', '1234');

        $client = $this->client;
        $client->restart();
        for ($i = 0; $i < 5; ++$i) {
            $crawler = $client->request('GET', '/herramienta-fichaje');
            $token = $crawler->filter('form[action="/herramienta-fichaje/iniciar"] input[name="_token"]')->attr('value');

            $client->request('POST', '/herramienta-fichaje/iniciar', [
                '_token' => $token,
                'trabajadorId' => 'EMP-BRUTE',
                'claveAcceso' => 'incorrecta',
            ]);
            self::assertResponseRedirects('/herramienta-fichaje');
            $client->followRedirect();
        }

        self::assertSelectorTextContains('.alert', 'bloqueado');

        $crawler = $client->request('GET', '/herramienta-fichaje');
        $token = $crawler->filter('form[action="/herramienta-fichaje/iniciar"] input[name="_token"]')->attr('value');
        $client->request('POST', '/herramienta-fichaje/iniciar', [
            '_token' => $token,
            'trabajadorId' => 'EMP-BRUTE',
            'claveAcceso' => '1234',
        ]);
        self::assertResponseRedirects('/herramienta-fichaje');
        $client->followRedirect();
        self::assertSelectorTextContains('.alert', 'Acceso temporalmente bloqueado por seguridad.');
    }

    public function testHerramientaExpiraSesionPorInactividad(): void
    {
        $admin = $this->createLoggedClient(self::ADMIN_EMAIL, self::ADMIN_PASSWORD);
        $this->crearTrabajador($admin, 'EMP-TIME', 'Timeout Worker', '7890');

        $client = $this->client;
        $client->restart();
        $crawler = $client->request('GET', '/herramienta-fichaje');
        $token = $crawler->filter('form[action="/herramienta-fichaje/iniciar"] input[name="_token"]')->attr('value');
        $client->request('POST', '/herramienta-fichaje/iniciar', [
            '_token' => $token,
            'trabajadorId' => 'EMP-TIME',
            'claveAcceso' => '7890',
        ]);
        self::assertResponseRedirects('/herramienta-fichaje');
        $client->followRedirect();

        sleep(2);
        $client->request('GET', '/herramienta-fichaje');
        self::assertSelectorTextContains('.alert', 'La sesion de la herramienta expiro por inactividad.');
        self::assertSelectorTextContains('main', 'Identificacion del trabajador');
    }

    public function testFlujoBasicoModoKiosko(): void
    {
        $admin = $this->createLoggedClient(self::ADMIN_EMAIL, self::ADMIN_PASSWORD);
        $this->crearTrabajador($admin, 'EMP-KIOSKO', 'Kiosko Worker', '4567');

        $client = $this->client;
        $client->restart();
        $crawler = $client->request('GET', '/herramienta-fichaje/kiosko');
        self::assertSelectorTextContains('h1', 'Kiosko de fichaje');

        $token = $client->request('GET', '/herramienta-fichaje')->filter('form[action="/herramienta-fichaje/iniciar"] input[name="_token"]')->attr('value');
        $client->request('POST', '/herramienta-fichaje/iniciar', [
            '_token' => $token,
            'trabajadorId' => 'EMP-KIOSKO',
            'claveAcceso' => '4567',
            'modo' => 'kiosko',
        ]);
        self::assertResponseRedirects('/herramienta-fichaje/kiosko');
        $client->followRedirect();

        self::assertSelectorTextContains('main', 'EMP-KIOSKO - Kiosko Worker');
        self::assertSelectorExists('.btn-accion-rapida[data-trabajador-id="EMP-KIOSKO"][data-accion="pausa"]');
        self::assertSelectorExists('.btn-accion-rapida[data-trabajador-id="EMP-KIOSKO"][data-accion="finalizar"]');
    }

    private function createLoggedClient(string $email, string $password)
    {
        $client = $this->client;
        $client->restart();
        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->selectButton('Entrar')->form([
            '_username' => $email,
            '_password' => $password,
        ]));

        self::assertResponseRedirects('/');
        $client->followRedirect();

        return $client;
    }

    private function crearUsuario(EntityManagerInterface $em, UserPasswordHasherInterface $hasher, string $email, string $password, array $roles): void
    {
        $usuario = new Usuario(bin2hex(random_bytes(16)), 'TENANT-1', $email, $roles);
        $usuario->setPassword($hasher->hashPassword($usuario, $password));
        $em->persist($usuario);
        $em->flush();
    }

    private function crearTrabajador($client, string $trabajadorId, string $nombre, string $claveAcceso = ''): void
    {
        $crawler = $client->request('GET', '/app/trabajadores/nuevo');
        $tokenCrear = $crawler->filter('form[action="/app/trabajadores/crear"] input[name="_token"]')->attr('value');
        $client->request('POST', '/app/trabajadores/crear', [
            '_token' => $tokenCrear,
            'trabajadorId' => $trabajadorId,
            'nombre' => $nombre,
            'email' => '',
            'pinKiosko' => $claveAcceso,
        ]);
        self::assertResponseRedirects('/app/trabajadores?q=&estado=todos&pagina=1&tamano=10');
    }
}
