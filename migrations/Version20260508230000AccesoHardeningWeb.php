<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508230000AccesoHardeningWeb extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reset token one-time con expiracion y bloqueo web por usuario+IP';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS usuario_reset_token (id VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, usuario_id VARCHAR(36) NOT NULL, token_hash VARCHAR(64) NOT NULL, expira_en DATETIME NOT NULL, creado_en DATETIME NOT NULL, usado_en DATETIME DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_USUARIO_RESET_TOKEN_HASH ON usuario_reset_token (token_hash)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_USUARIO_RESET_TOKEN_TENANT_USUARIO ON usuario_reset_token (tenant_id, usuario_id)');

        $this->addSql('CREATE TABLE IF NOT EXISTS login_bloqueo_web (id VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, email VARCHAR(180) NOT NULL, ip VARCHAR(64) NOT NULL, intentos INTEGER NOT NULL, bloqueado_hasta DATETIME DEFAULT NULL, actualizado_en DATETIME NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_LOGIN_BLOQUEO_SCOPE ON login_bloqueo_web (tenant_id, email, ip)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS login_bloqueo_web');
        $this->addSql('DROP TABLE IF EXISTS usuario_reset_token');
    }
}
