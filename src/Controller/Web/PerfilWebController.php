<?php

namespace App\Controller\Web;

use App\Modulo\Acceso\Domain\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/app/perfil')]
class PerfilWebController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {}

    #[Route('', name: 'app_perfil', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EMPLEADO');

        return $this->render('web/perfil/index.html.twig');
    }

    #[Route('/email', name: 'app_perfil_email', methods: ['POST'])]
    public function actualizarEmail(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_EMPLEADO');
        if (!$this->isCsrfTokenValid('perfil_email', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF inválido.');

            return $this->redirectToRoute('app_perfil');
        }

        try {
            $usuario = $this->getUser();
            if (!$usuario instanceof Usuario) {
                throw new \DomainException('Usuario no autenticado.');
            }
            $email = trim((string) $request->request->get('email', ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \DomainException('Email no válido.');
            }
            $usuario->setEmail($email);
            $this->entityManager->flush();
            $this->addFlash('success', 'Email actualizado correctamente.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'No se pudo actualizar el email: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_perfil');
    }

    #[Route('/password', name: 'app_perfil_password', methods: ['POST'])]
    public function actualizarPassword(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_EMPLEADO');
        if (!$this->isCsrfTokenValid('perfil_password', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF inválido.');

            return $this->redirectToRoute('app_perfil');
        }

        try {
            $usuario = $this->getUser();
            if (!$usuario instanceof Usuario) {
                throw new \DomainException('Usuario no autenticado.');
            }
            $actual = (string) $request->request->get('password_actual', '');
            $nuevo = (string) $request->request->get('password_nuevo', '');
            $confirmar = (string) $request->request->get('password_confirmar', '');

            if (!$this->passwordHasher->isPasswordValid($usuario, $actual)) {
                throw new \DomainException('La contraseña actual no es correcta.');
            }
            if (mb_strlen(trim($nuevo)) < 12) {
                throw new \DomainException('La nueva contraseña debe tener al menos 12 caracteres.');
            }
            if ($nuevo !== $confirmar) {
                throw new \DomainException('Las contraseñas no coinciden.');
            }
            $usuario->setPassword($this->passwordHasher->hashPassword($usuario, $nuevo));
            $this->entityManager->flush();
            $this->addFlash('success', 'Contraseña actualizada correctamente.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'No se pudo cambiar la contraseña: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_perfil');
    }
}
