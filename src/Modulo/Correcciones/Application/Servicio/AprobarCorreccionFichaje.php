<?php

namespace App\Modulo\Correcciones\Application\Servicio;

use App\Modulo\Acceso\Application\Servicio\ResolverPermisoRol;
use App\Modulo\Acceso\Domain\Entity\Usuario;
use App\Modulo\Auditoria\Application\Servicio\RegistrarAuditoria;
use App\Modulo\Correcciones\Infrastructure\Repository\CorreccionFichajeRepository;
use App\Modulo\Fichajes\Domain\Entity\EventoFichaje;
use App\Modulo\Fichajes\Infrastructure\Repository\EventoFichajeRepository;
use Doctrine\ORM\EntityManagerInterface;

class AprobarCorreccionFichaje
{
    public function __construct(
        private readonly CorreccionFichajeRepository $correcciones,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventoFichajeRepository $eventos,
        private readonly RegistrarAuditoria $auditoria,
        private readonly ResolverPermisoRol $resolverPermisoRol
    ) {}

    public function ejecutar(string $tenantId, string $correccionId, ?Usuario $usuario = null): void
    {
        if ($usuario instanceof Usuario && !$this->resolverPermisoRol->puede('correcciones.aprobar', $usuario->getCodigosRolTenant())) {
            throw new \DomainException('ACCESO_DENEGADO_ROL');
        }

        $correccion = $this->correcciones->buscarPorIdYTenant($correccionId, $tenantId);
        if ($correccion === null) {
            throw new \DomainException('CORRECCION_NO_ENCONTRADA');
        }

        $original = $this->entityManager->getRepository(EventoFichaje::class)->findOneBy([
            'id' => $correccion->getEventoFichajeId(),
            'tenantId' => $tenantId,
        ]);
        if ($original === null) {
            throw new \DomainException('EVENTO_ORIGINAL_NO_ENCONTRADO');
        }

        $eventoAplicado = new EventoFichaje(
            bin2hex(random_bytes(16)),
            $tenantId,
            $original->getEmpleadoId(),
            $correccion->getTipoCorregido() ?? $original->getTipo(),
            $correccion->getOcurridoEnCorregido() ?? $original->getOcurridoEn(),
            'corregido',
            'CORRECCION_APROBADA'
        );
        $this->eventos->guardar($eventoAplicado);

        $correccion->aprobar($eventoAplicado->getId());
        $this->correcciones->guardar($correccion);

        $this->auditoria->registrar($tenantId, 'correccion.aprobada', ['correccionId' => $correccionId], ['eventoAplicadoId' => $eventoAplicado->getId()]);
    }
}
