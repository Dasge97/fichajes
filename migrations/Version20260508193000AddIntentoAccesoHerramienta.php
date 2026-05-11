<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508193000AddIntentoAccesoHerramienta extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega tabla para control anti fuerza bruta en herramienta de fichaje';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('intento_acceso_herramienta')) {
            return;
        }

        $this->addSql('CREATE TABLE intento_acceso_herramienta (id CHAR(36) NOT NULL, trabajadorId VARCHAR(50) NOT NULL, ipHash VARCHAR(64) NOT NULL, fallosAcumulados INTEGER NOT NULL, ventanaIniciadaEn DATETIME NOT NULL, bloqueadoHasta DATETIME DEFAULT NULL, actualizadoEn DATETIME NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_intento_herramienta_trabajador_ip ON intento_acceso_herramienta (trabajadorId, ipHash)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE intento_acceso_herramienta');
    }
}
