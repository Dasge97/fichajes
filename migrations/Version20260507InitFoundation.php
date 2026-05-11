<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507InitFoundation extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Foundation fichajes + horarios + ausencias + auditoria + seguridad';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE usuario (id VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL --(DC2Type:json)
        , password VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USUARIO_EMAIL ON usuario (email)');

        $this->addSql('CREATE TABLE horario_trabajo (id VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, nombre VARCHAR(120) NOT NULL, tramos CLOB NOT NULL --(DC2Type:json)
        , PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_HORARIO_TENANT ON horario_trabajo (tenant_id)');

        $this->addSql('CREATE TABLE asignacion_horario_empleado (id VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, empleado_id VARCHAR(36) NOT NULL, horario_id VARCHAR(36) NOT NULL, vigente_desde DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , vigente_hasta DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_ASIGNACION_TENANT ON asignacion_horario_empleado (tenant_id)');

        $this->addSql('CREATE TABLE solicitud_ausencia (id VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, empleado_id VARCHAR(36) NOT NULL, tipo VARCHAR(40) NOT NULL, fecha_inicio DATE NOT NULL --(DC2Type:date_immutable)
        , fecha_fin DATE NOT NULL --(DC2Type:date_immutable)
        , estado VARCHAR(20) NOT NULL, idempotency_key VARCHAR(80) DEFAULT NULL, payload_hash VARCHAR(64) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_AUSENCIA_TENANT ON solicitud_ausencia (tenant_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AUSENCIA_IDEMPOTENCY ON solicitud_ausencia (tenant_id, idempotency_key)');

        $this->addSql('CREATE TABLE trabajador (id VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, trabajador_id VARCHAR(50) NOT NULL, nombre VARCHAR(120) NOT NULL, email VARCHAR(180) DEFAULT NULL, activo BOOLEAN NOT NULL, fecha_alta DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_TRABAJADOR_TENANT ON trabajador (tenant_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_TRABAJADOR_TENANT_ID ON trabajador (tenant_id, trabajador_id)');

        $this->addSql('CREATE TABLE evento_fichaje (id VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, empleado_id VARCHAR(36) NOT NULL, tipo VARCHAR(30) NOT NULL, ocurrido_en DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , estado_cumplimiento VARCHAR(40) NOT NULL, motivo_desvio VARCHAR(100) DEFAULT NULL, idempotency_key VARCHAR(80) DEFAULT NULL, payload_hash VARCHAR(64) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_FICHAJE_TENANT ON evento_fichaje (tenant_id, empleado_id, ocurrido_en)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FICHAJE_IDEMPOTENCY ON evento_fichaje (tenant_id, idempotency_key)');

        $this->addSql('CREATE TABLE correccion_fichaje (id VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, evento_fichaje_id VARCHAR(36) NOT NULL, estado VARCHAR(20) NOT NULL, ocurrido_en_corregido DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , tipo_corregido VARCHAR(30) DEFAULT NULL, motivo VARCHAR(255) NOT NULL, evidencia VARCHAR(500) DEFAULT NULL, evento_aplicado_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CORRECCION_TENANT ON correccion_fichaje (tenant_id)');

        $this->addSql('CREATE TABLE registro_auditoria (id VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, accion VARCHAR(120) NOT NULL, antes CLOB DEFAULT NULL --(DC2Type:json)
        , despues CLOB DEFAULT NULL --(DC2Type:json)
        , creado_en DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_AUDITORIA_TENANT ON registro_auditoria (tenant_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE registro_auditoria');
        $this->addSql('DROP TABLE correccion_fichaje');
        $this->addSql('DROP TABLE evento_fichaje');
        $this->addSql('DROP TABLE trabajador');
        $this->addSql('DROP TABLE solicitud_ausencia');
        $this->addSql('DROP TABLE asignacion_horario_empleado');
        $this->addSql('DROP TABLE horario_trabajo');
        $this->addSql('DROP TABLE usuario');
    }
}
