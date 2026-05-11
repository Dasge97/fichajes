<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508213000UsuarioRolOwnership extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Modelo usuario/rol/ownership con convivencia legacy';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $trabajadorColumns = $schemaManager->tablesExist(['trabajador']) ? $schemaManager->listTableColumns('trabajador') : [];
        if (
            $schemaManager->tablesExist(['rol'])
            && $schemaManager->tablesExist(['usuario_rol'])
            && array_key_exists('usuario_id', $trabajadorColumns)
        ) {
            return;
        }

        $usuarioTenantColumn = $schema->getTable('usuario')->hasColumn('tenant_id') ? 'tenant_id' : 'tenantId';
        $trabajadorTenantColumn = $schema->getTable('trabajador')->hasColumn('tenant_id') ? 'tenant_id' : 'tenantId';

        if ($this->connection->getDatabasePlatform()->getName() === 'sqlite') {
            $this->addSql('DROP INDEX IF EXISTS UNIQ_USUARIO_EMAIL');
        } else {
            $this->addSql('DROP INDEX UNIQ_USUARIO_EMAIL ON usuario');
        }
        $this->addSql(sprintf('CREATE UNIQUE INDEX UNIQ_USUARIO_TENANT_EMAIL ON usuario (%s, email)', $usuarioTenantColumn));
        $this->addSql('CREATE TABLE IF NOT EXISTS rol (id VARCHAR(36) NOT NULL, codigo VARCHAR(60) NOT NULL, nombre VARCHAR(120) NOT NULL, descripcion VARCHAR(255) DEFAULT NULL, es_sistema BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_ROL_CODIGO ON rol (codigo)');

        if ($this->connection->getDatabasePlatform()->getName() === 'sqlite') {
            $this->addSql(sprintf('CREATE TABLE IF NOT EXISTS usuario_rol (id VARCHAR(36) NOT NULL, %s VARCHAR(36) NOT NULL, usuario_id VARCHAR(36) NOT NULL, rol_id VARCHAR(36) NOT NULL, asignado_por_usuario_id VARCHAR(36) DEFAULT NULL, asignado_en DATETIME NOT NULL, PRIMARY KEY(id), FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE, FOREIGN KEY (rol_id) REFERENCES rol (id) ON DELETE CASCADE)', $usuarioTenantColumn));
        } else {
            $this->addSql(sprintf('CREATE TABLE IF NOT EXISTS usuario_rol (id VARCHAR(36) NOT NULL, %s VARCHAR(36) NOT NULL, usuario_id VARCHAR(36) NOT NULL, rol_id VARCHAR(36) NOT NULL, asignado_por_usuario_id VARCHAR(36) DEFAULT NULL, asignado_en DATETIME NOT NULL, PRIMARY KEY(id), CONSTRAINT FK_USUARIO_ROL_USUARIO FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE, CONSTRAINT FK_USUARIO_ROL_ROL FOREIGN KEY (rol_id) REFERENCES rol (id) ON DELETE CASCADE)', $usuarioTenantColumn));
        }
        $this->addSql(sprintf('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_USUARIO_ROL_TENANT ON usuario_rol (%s, usuario_id, rol_id)', $usuarioTenantColumn));
        $this->addSql(sprintf('CREATE INDEX IF NOT EXISTS IDX_USUARIO_ROL_TENANT ON usuario_rol (%s)', $usuarioTenantColumn));

        if (!$schemaManager->listTableDetails('trabajador')->hasColumn('usuario_id')) {
            $this->addSql('ALTER TABLE trabajador ADD usuario_id VARCHAR(36) DEFAULT NULL');
        }
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_TRABAJADOR_USUARIO ON trabajador (usuario_id)');
        $this->addSql(sprintf('CREATE INDEX IF NOT EXISTS IDX_TRABAJADOR_TENANT_USUARIO ON trabajador (%s, usuario_id)', $trabajadorTenantColumn));
        if ($this->connection->getDatabasePlatform()->getName() !== 'sqlite') {
            $this->addSql('ALTER TABLE trabajador ADD CONSTRAINT FK_TRABAJADOR_USUARIO FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE SET NULL');
        }

        $usuarioColumns = $schemaManager->listTableColumns('usuario');
        if (!array_key_exists('activo', $usuarioColumns)) {
            $this->addSql('ALTER TABLE usuario ADD activo BOOLEAN DEFAULT 1 NOT NULL');
        }
        if (!array_key_exists('intentos_web_fallidos', $usuarioColumns)) {
            $this->addSql('ALTER TABLE usuario ADD intentos_web_fallidos INTEGER DEFAULT 0 NOT NULL');
        }
        if (!array_key_exists('bloqueado_hasta', $usuarioColumns)) {
            $this->addSql('ALTER TABLE usuario ADD bloqueado_hasta DATETIME DEFAULT NULL');
        }
        if (!array_key_exists('password_actualizado_en', $usuarioColumns)) {
            $this->addSql('ALTER TABLE usuario ADD password_actualizado_en DATETIME DEFAULT NULL');
        }

        $rolColumns = $schemaManager->listTableColumns('rol');
        if (array_key_exists('codigo', $rolColumns) && array_key_exists('nombre', $rolColumns) && array_key_exists('descripcion', $rolColumns) && array_key_exists('es_sistema', $rolColumns)) {
            $this->addSql("INSERT INTO rol (id, codigo, nombre, descripcion, es_sistema) SELECT 'rol-owner', 'owner_tenant', 'Owner tenant', 'Control total del tenant', 1 WHERE NOT EXISTS (SELECT 1 FROM rol WHERE codigo = 'owner_tenant')");
            $this->addSql("INSERT INTO rol (id, codigo, nombre, descripcion, es_sistema) SELECT 'rol-rrhh', 'gestor_rrhh', 'Gestor RRHH', 'Gestion de trabajadores y horarios', 1 WHERE NOT EXISTS (SELECT 1 FROM rol WHERE codigo = 'gestor_rrhh')");
            $this->addSql("INSERT INTO rol (id, codigo, nombre, descripcion, es_sistema) SELECT 'rol-responsable', 'responsable_equipo', 'Responsable equipo', 'Operacion de equipo', 1 WHERE NOT EXISTS (SELECT 1 FROM rol WHERE codigo = 'responsable_equipo')");
            $this->addSql("INSERT INTO rol (id, codigo, nombre, descripcion, es_sistema) SELECT 'rol-trabajador', 'trabajador', 'Trabajador', 'Autogestion propia', 1 WHERE NOT EXISTS (SELECT 1 FROM rol WHERE codigo = 'trabajador')");
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform()->getName() === 'sqlite') {
            $this->addSql('DROP INDEX IF EXISTS UNIQ_USUARIO_TENANT_EMAIL');
        } else {
            $this->addSql('DROP INDEX UNIQ_USUARIO_TENANT_EMAIL ON usuario');
        }
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USUARIO_EMAIL ON usuario (email)');
        if ($this->connection->getDatabasePlatform()->getName() !== 'sqlite') {
            $this->addSql('ALTER TABLE trabajador DROP CONSTRAINT FK_TRABAJADOR_USUARIO');
        }
        $this->addSql('DROP INDEX UNIQ_TRABAJADOR_USUARIO');
        $this->addSql('DROP INDEX IDX_TRABAJADOR_TENANT_USUARIO');
        $this->addSql('ALTER TABLE trabajador DROP usuario_id');
        $this->addSql('ALTER TABLE usuario DROP activo');
        $this->addSql('ALTER TABLE usuario DROP intentos_web_fallidos');
        $this->addSql('ALTER TABLE usuario DROP bloqueado_hasta');
        $this->addSql('ALTER TABLE usuario DROP password_actualizado_en');
        $this->addSql('DROP TABLE usuario_rol');
        $this->addSql('DROP TABLE rol');
    }
}
