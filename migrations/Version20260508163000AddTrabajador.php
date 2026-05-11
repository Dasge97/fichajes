<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508163000AddTrabajador extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea tabla trabajador para modulo de trabajadores';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS trabajador (id VARCHAR(36) NOT NULL, tenantId VARCHAR(36) NOT NULL, trabajadorId VARCHAR(50) NOT NULL, nombre VARCHAR(120) NOT NULL, email VARCHAR(180) DEFAULT NULL, activo BOOLEAN NOT NULL, fechaAlta DATETIME NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_TRABAJADOR_TENANT ON trabajador (tenantId)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_TRABAJADOR_TENANT_ID ON trabajador (tenantId, trabajadorId)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS trabajador');
    }
}
