<?php

namespace App\Tests\Api;

use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Fichajes\Domain\Entity\EventoFichaje;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class FichajesApiTest extends WebTestCase
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

        $this->crearUsuario($em, $container->get(UserPasswordHasherInterface::class), 'api-owner@test.local', 'owner123', ['ROLE_ADMIN']);
        $this->client->disableReboot();
    }

    public function testDevuelve422EnConflictoIdempotencia(): void
    {
        $this->login($this->client, 'api-owner@test.local', 'owner123');

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        $eventoExistente = new EventoFichaje(
            bin2hex(random_bytes(16)),
            'TENANT-1',
            'E1',
            'clock-in',
            new \DateTimeImmutable('2026-05-08T09:00:00+00:00'),
            'dentro_horario',
            null,
            'K1',
            str_repeat('a', 64)
        );
        $em->persist($eventoExistente);
        $em->flush();

        $this->client->request(
            'POST',
            '/api/v1/fichajes/eventos',
            server: ['HTTP_X-TENANT-ID' => 'TENANT-1', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'empleadoId' => 'E1',
                'tipo' => 'clock-in',
                'ocurridoEn' => '2026-05-08T09:00:00+00:00',
                'idempotencyKey' => 'K1',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('IDEMPOTENCY_CONFLICT', (string) $this->client->getResponse()->getContent());
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
}
