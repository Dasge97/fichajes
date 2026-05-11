<?php

namespace App\Tests\Api;

use App\Modulo\Acceso\Domain\Entity\Rol;
use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Trabajadores\Domain\Entity\Trabajador;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TrabajadoresApiTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $container = static::getContainer();

        $em = $container->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        $em->getConnection()->executeStatement('CREATE TABLE IF NOT EXISTS login_bloqueo_web (id VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, email VARCHAR(180) NOT NULL, ip VARCHAR(64) NOT NULL, intentos INTEGER NOT NULL, bloqueado_hasta DATETIME DEFAULT NULL, actualizado_en DATETIME NOT NULL, PRIMARY KEY(id))');
        $em->getConnection()->executeStatement('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_LOGIN_BLOQUEO_SCOPE ON login_bloqueo_web (tenant_id, email, ip)');
        $em->getConnection()->executeStatement('CREATE TABLE IF NOT EXISTS usuario_reset_token (id VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, usuario_id VARCHAR(36) NOT NULL, token_hash VARCHAR(128) NOT NULL, expira_en DATETIME NOT NULL, creado_en DATETIME NOT NULL, usado_en DATETIME DEFAULT NULL, PRIMARY KEY(id))');
        $this->crearRolesSistema($em);

        $hasher = $container->get(UserPasswordHasherInterface::class);
        $this->crearUsuario($em, $hasher, 'rrhh@test.local', 'rrhh123', ['ROLE_SUPERVISOR']);
        $this->crearUsuario($em, $hasher, 'empleado@test.local', 'empleado123', ['ROLE_EMPLEADO']);

        $this->client->disableReboot();
    }

    public function testEmpleadoNoPuedeCrearTrabajadorPorApi(): void
    {
        $this->login($this->client, 'empleado@test.local', 'empleado123');

        $this->client->request(
            'POST',
            '/api/v1/trabajadores',
            server: ['HTTP_X-TENANT-ID' => 'TENANT-1', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'trabajadorId' => 'EMP-NO',
                'nombre' => 'No Permitido',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testSupervisorPuedeCrearTrabajadorPorApi(): void
    {
        $this->login($this->client, 'rrhh@test.local', 'rrhh123');

        $this->client->request(
            'POST',
            '/api/v1/trabajadores',
            server: ['HTTP_X-TENANT-ID' => 'TENANT-1', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'trabajadorId' => 'EMP-OK',
                'nombre' => 'Permitido',
                'email' => 'permitido@test.local',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(201);
        self::assertStringContainsString('EMP-OK', (string) $this->client->getResponse()->getContent());
    }

    public function testUserAccountsAltaYActivacionEfectivaCuentaWeb(): void
    {
        $this->login($this->client, 'rrhh@test.local', 'rrhh123');
        $this->crearTrabajadorApi('EMP-WEB-ACT', 'Cuenta Web Activa', 'web-act@test.local', '1234');

        $this->client->request(
            'POST',
            '/api/v1/trabajadores/EMP-WEB-ACT/cuenta/crear',
            server: ['HTTP_X-TENANT-ID' => 'TENANT-1', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email' => 'web-act@test.local',
                'password' => 'CuentaWeb123A',
            ], JSON_THROW_ON_ERROR)
        );
        self::assertResponseStatusCodeSame(201);

        $this->client->request(
            'POST',
            '/api/v1/trabajadores/EMP-WEB-ACT/cuenta/desactivar',
            server: ['HTTP_X-TENANT-ID' => 'TENANT-1']
        );
        self::assertResponseStatusCodeSame(200);

        $this->client->request(
            'POST',
            '/api/v1/trabajadores/EMP-WEB-ACT/cuenta/activar',
            server: ['HTTP_X-TENANT-ID' => 'TENANT-1']
        );
        self::assertResponseStatusCodeSame(200);

        $this->client->restart();
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Entrar')->form([
            '_username' => 'web-act@test.local',
            '_password' => 'CuentaWeb123A',
        ]));

        self::assertResponseRedirects('/');
    }

    public function testUserAccountsDesactivacionBloqueaLoginWeb(): void
    {
        $this->login($this->client, 'rrhh@test.local', 'rrhh123');
        $this->crearTrabajadorApi('EMP-WEB-OFF', 'Cuenta Web Off', 'web-off@test.local', '2345');

        $this->client->request(
            'POST',
            '/api/v1/trabajadores/EMP-WEB-OFF/cuenta/crear',
            server: ['HTTP_X-TENANT-ID' => 'TENANT-1', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email' => 'web-off@test.local',
                'password' => 'CuentaWeb123A',
            ], JSON_THROW_ON_ERROR)
        );
        self::assertResponseStatusCodeSame(201);

        $this->client->request(
            'POST',
            '/api/v1/trabajadores/EMP-WEB-OFF/cuenta/desactivar',
            server: ['HTTP_X-TENANT-ID' => 'TENANT-1']
        );
        self::assertResponseStatusCodeSame(200);

        $this->client->restart();
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Entrar')->form([
            '_username' => 'web-off@test.local',
            '_password' => 'CuentaWeb123A',
        ]));

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert', 'CUENTA_DESACTIVADA');
    }

    public function testUserAccountsResetPasswordInvalidaAnteriorSinAlterarPinKiosko(): void
    {
        $this->login($this->client, 'rrhh@test.local', 'rrhh123');
        $this->crearTrabajadorApi('EMP-WEB-RESET', 'Cuenta Reset', 'web-reset@test.local', '7890');

        $this->client->request(
            'POST',
            '/api/v1/trabajadores/EMP-WEB-RESET/cuenta/crear',
            server: ['HTTP_X-TENANT-ID' => 'TENANT-1', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email' => 'web-reset@test.local',
                'password' => 'CuentaWeb123A',
            ], JSON_THROW_ON_ERROR)
        );
        self::assertResponseStatusCodeSame(201);

        $this->client->request('POST', '/api/v1/trabajadores/EMP-WEB-RESET/cuenta/reset-password', server: ['HTTP_X-TENANT-ID' => 'TENANT-1']);
        self::assertResponseStatusCodeSame(200);
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $token = (string) ($data['resetToken'] ?? '');
        self::assertNotSame('', $token);

        $this->client->request(
            'POST',
            '/api/v1/trabajadores/EMP-WEB-RESET/cuenta/reset-password/confirmar',
            server: ['HTTP_X-TENANT-ID' => 'TENANT-1', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'token' => $token,
                'password' => 'NuevaWeb123A',
            ], JSON_THROW_ON_ERROR)
        );
        self::assertResponseStatusCodeSame(200);

        $this->client->restart();
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Entrar')->form([
            '_username' => 'web-reset@test.local',
            '_password' => 'CuentaWeb123A',
        ]));
        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert', 'Invalid credentials.');

        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Entrar')->form([
            '_username' => 'web-reset@test.local',
            '_password' => 'NuevaWeb123A',
        ]));
        self::assertResponseRedirects('/');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $trabajador = $em->getRepository(Trabajador::class)->findOneBy([
            'tenantId' => 'TENANT-1',
            'trabajadorId' => 'EMP-WEB-RESET',
        ]);
        self::assertInstanceOf(Trabajador::class, $trabajador);
        self::assertTrue($trabajador->validarClaveAcceso('7890'));
    }

    public function testAccessControlApiRechazaPinEnCanalWeb(): void
    {
        $this->login($this->client, 'rrhh@test.local', 'rrhh123');
        $this->crearTrabajadorApi('EMP-WEB-CHAN', 'Cuenta Canal', 'web-chan@test.local', '4567');

        $this->client->request(
            'POST',
            '/api/v1/trabajadores/EMP-WEB-CHAN/cuenta/crear',
            server: ['HTTP_X-TENANT-ID' => 'TENANT-1', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email' => 'web-chan@test.local',
                'password' => 'CuentaWeb123A',
            ], JSON_THROW_ON_ERROR)
        );
        self::assertResponseStatusCodeSame(201);

        $this->client->restart();
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Entrar')->form([
            '_username' => 'web-chan@test.local',
            '_password' => '4567',
        ]));

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert', 'Invalid credentials.');
    }

    private function login($client, string $email, string $password): void
    {
        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->selectButton('Entrar')->form([
            '_username' => $email,
            '_password' => $password,
        ]));
        $client->followRedirect();
    }

    private function crearUsuario(EntityManagerInterface $em, UserPasswordHasherInterface $hasher, string $email, string $password, array $roles): void
    {
        $usuario = new Usuario(bin2hex(random_bytes(16)), 'TENANT-1', $email, $roles);
        $usuario->setPassword($hasher->hashPassword($usuario, $password));
        $em->persist($usuario);
        $em->flush();
    }

    private function crearTrabajadorApi(string $trabajadorId, string $nombre, string $email, string $pinKiosko): void
    {
        $this->client->request(
            'POST',
            '/api/v1/trabajadores',
            server: ['HTTP_X-TENANT-ID' => 'TENANT-1', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'trabajadorId' => $trabajadorId,
                'nombre' => $nombre,
                'email' => $email,
                'pinKiosko' => $pinKiosko,
            ], JSON_THROW_ON_ERROR)
        );
        self::assertResponseStatusCodeSame(201);
    }

    private function crearRolesSistema(EntityManagerInterface $em): void
    {
        $roles = [
            new Rol(bin2hex(random_bytes(16)), 'owner_tenant', 'Owner tenant'),
            new Rol(bin2hex(random_bytes(16)), 'gestor_rrhh', 'Gestor RRHH'),
            new Rol(bin2hex(random_bytes(16)), 'responsable_equipo', 'Responsable equipo'),
            new Rol(bin2hex(random_bytes(16)), 'trabajador', 'Trabajador'),
        ];

        foreach ($roles as $rol) {
            $em->persist($rol);
        }
        $em->flush();
    }
}
