<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508174500AddTrabajadorClaveAcceso extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega clave de acceso para herramienta de fichaje';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('trabajador') && $schema->getTable('trabajador')->hasColumn('claveAccesoHash')) {
            return;
        }

        $this->addSql('ALTER TABLE trabajador ADD COLUMN claveAccesoHash VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // SQLite no soporta DROP COLUMN simple en todas las versiones.
    }
}
