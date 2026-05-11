<?php

namespace App\Controller;

use App\Modulo\Plataforma\Application\Tenant\TenantContexto;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly TenantContexto $tenantContexto,
        private readonly Connection $connection
    ) {}

    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function __invoke(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EMPLEADO');

        $tenantId = $this->tenantContexto->obtenerTenantId();
        $hoyInicio = (new \DateTimeImmutable('today'))->format('Y-m-d 00:00:00');
        $hoyFin = (new \DateTimeImmutable('tomorrow'))->format('Y-m-d 00:00:00');
        $ahora = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $proximos14 = (new \DateTimeImmutable('+14 days'))->format('Y-m-d H:i:s');

        $kpis = [
            'fichajesHoy' => (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM evento_fichaje WHERE tenantId = :tenant AND ocurridoEn >= :inicio AND ocurridoEn < :fin',
                ['tenant' => $tenantId, 'inicio' => $hoyInicio, 'fin' => $hoyFin]
            ),
            'fueraHorarioHoy' => (int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM evento_fichaje WHERE tenantId = :tenant AND ocurridoEn >= :inicio AND ocurridoEn < :fin AND estadoCumplimiento <> 'dentro_horario'",
                ['tenant' => $tenantId, 'inicio' => $hoyInicio, 'fin' => $hoyFin]
            ),
            'ausenciasActivas' => (int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM solicitud_ausencia WHERE tenantId = :tenant AND estado = 'aprobada' AND fechaInicio <= :ahora AND fechaFin >= :ahora",
                ['tenant' => $tenantId, 'ahora' => $ahora]
            ),
            'correccionesPendientes' => (int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM correccion_fichaje WHERE tenantId = :tenant AND estado = 'pendiente'",
                ['tenant' => $tenantId]
            ),
            'horariosAsignados' => (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM asignacion_horario_empleado WHERE tenantId = :tenant AND (vigenteHasta IS NULL OR vigenteHasta >= :ahora)',
                ['tenant' => $tenantId, 'ahora' => $ahora]
            ),
            'trabajadoresActivos' => (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM trabajador WHERE tenantId = :tenant AND activo = 1',
                ['tenant' => $tenantId]
            ),
        ];

        $actividadReciente = $this->connection->fetchAllAssociative(
            'SELECT accion, creadoEn FROM registro_auditoria WHERE tenantId = :tenant ORDER BY creadoEn DESC LIMIT 8',
            ['tenant' => $tenantId]
        );

        $agendaProxima = $this->connection->fetchAllAssociative(
            "SELECT 'ausencia' AS tipo, empleadoId, fechaInicio AS inicio, fechaFin AS fin, estado FROM solicitud_ausencia WHERE tenantId = :tenant AND fechaInicio >= :ahora AND fechaInicio <= :proximos ORDER BY fechaInicio ASC LIMIT 8",
            ['tenant' => $tenantId, 'ahora' => $ahora, 'proximos' => $proximos14]
        );

        return $this->render('dashboard/index.html.twig', [
            'kpis' => $kpis,
            'actividadReciente' => $actividadReciente,
            'agendaProxima' => $agendaProxima,
        ]);
    }
}
