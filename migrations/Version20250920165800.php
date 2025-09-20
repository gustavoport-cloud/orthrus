<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250920165800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema for token service';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();
        if ($platform === 'mysql') {
            // Users
            $this->addSql('CREATE TABLE users (id VARCHAR(36) NOT NULL, email VARCHAR(255) NOT NULL, password_hash VARCHAR(255) NOT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON users (email)');

            // Organizations
            $this->addSql('CREATE TABLE organizations (id VARCHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

            // Memberships
            $this->addSql('CREATE TABLE memberships (id VARCHAR(36) NOT NULL, user_id VARCHAR(36) NOT NULL, org_id VARCHAR(36) NOT NULL, role VARCHAR(64) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('CREATE INDEX idx_membership_user_org ON memberships (user_id, org_id)');
            $this->addSql('ALTER TABLE memberships ADD CONSTRAINT FK_member_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE memberships ADD CONSTRAINT FK_member_org FOREIGN KEY (org_id) REFERENCES organizations (id) ON DELETE CASCADE');

            // OAuth clients
            $this->addSql('CREATE TABLE oauth_clients (id VARCHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, client_id VARCHAR(64) NOT NULL, secret_hash VARCHAR(255) NOT NULL, allowed_scopes JSON DEFAULT NULL, allowed_orgs JSON DEFAULT NULL, is_active TINYINT(1) NOT NULL, last_used_at DATETIME DEFAULT NULL, rotated_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('CREATE UNIQUE INDEX uniq_client_id ON oauth_clients (client_id)');

            // Refresh tokens
            $this->addSql('CREATE TABLE refresh_tokens (id VARCHAR(36) NOT NULL, user_id VARCHAR(36) DEFAULT NULL, client_id VARCHAR(36) DEFAULT NULL, org_id VARCHAR(36) NOT NULL, replaced_by_id VARCHAR(36) DEFAULT NULL, token_hash VARCHAR(255) NOT NULL, jti VARCHAR(255) NOT NULL, ip VARCHAR(255) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, expires_at DATETIME NOT NULL, revoked_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('CREATE UNIQUE INDEX uniq_refresh_token_hash ON refresh_tokens (token_hash)');
            $this->addSql('CREATE INDEX idx_refresh_by_user_org_revoked ON refresh_tokens (user_id, org_id, revoked_at)');
            $this->addSql('CREATE INDEX idx_refresh_by_client_org ON refresh_tokens (client_id, org_id)');
            $this->addSql('CREATE INDEX idx_refresh_jti ON refresh_tokens (jti)');
            $this->addSql('CREATE INDEX idx_refresh_expires ON refresh_tokens (expires_at)');
            $this->addSql('ALTER TABLE refresh_tokens ADD CONSTRAINT FK_rt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE refresh_tokens ADD CONSTRAINT FK_rt_client FOREIGN KEY (client_id) REFERENCES oauth_clients (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE refresh_tokens ADD CONSTRAINT FK_rt_org FOREIGN KEY (org_id) REFERENCES organizations (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE refresh_tokens ADD CONSTRAINT FK_rt_replaced_by FOREIGN KEY (replaced_by_id) REFERENCES refresh_tokens (id) ON DELETE SET NULL');

            // Revoked JTI
            $this->addSql('CREATE TABLE revoked_jti (jti VARCHAR(255) NOT NULL, reason VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(jti)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        } elseif ($platform === 'postgresql') {
            // Users
            $this->addSql('CREATE TABLE users (id VARCHAR(36) NOT NULL, email VARCHAR(255) NOT NULL, password_hash VARCHAR(255) NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
            $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON users (email)');

            // Organizations
            $this->addSql('CREATE TABLE organizations (id VARCHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');

            // Memberships
            $this->addSql('CREATE TABLE memberships (id VARCHAR(36) NOT NULL, user_id VARCHAR(36) NOT NULL, org_id VARCHAR(36) NOT NULL, role VARCHAR(64) NOT NULL, PRIMARY KEY(id))');
            $this->addSql('CREATE INDEX idx_membership_user_org ON memberships (user_id, org_id)');
            $this->addSql('ALTER TABLE memberships ADD CONSTRAINT FK_member_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->addSql('ALTER TABLE memberships ADD CONSTRAINT FK_member_org FOREIGN KEY (org_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

            // OAuth clients
            $this->addSql('CREATE TABLE oauth_clients (id VARCHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, client_id VARCHAR(64) NOT NULL, secret_hash VARCHAR(255) NOT NULL, allowed_scopes JSON DEFAULT NULL, allowed_orgs JSON DEFAULT NULL, is_active BOOLEAN NOT NULL, last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, rotated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
            $this->addSql('CREATE UNIQUE INDEX uniq_client_id ON oauth_clients (client_id)');

            // Refresh tokens
            $this->addSql('CREATE TABLE refresh_tokens (id VARCHAR(36) NOT NULL, user_id VARCHAR(36) DEFAULT NULL, client_id VARCHAR(36) DEFAULT NULL, org_id VARCHAR(36) NOT NULL, replaced_by_id VARCHAR(36) DEFAULT NULL, token_hash VARCHAR(255) NOT NULL, jti VARCHAR(255) NOT NULL, ip VARCHAR(255) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
            $this->addSql('CREATE UNIQUE INDEX uniq_refresh_token_hash ON refresh_tokens (token_hash)');
            $this->addSql('CREATE INDEX idx_refresh_by_user_org_revoked ON refresh_tokens (user_id, org_id, revoked_at)');
            $this->addSql('CREATE INDEX idx_refresh_by_client_org ON refresh_tokens (client_id, org_id)');
            $this->addSql('CREATE INDEX idx_refresh_jti ON refresh_tokens (jti)');
            $this->addSql('CREATE INDEX idx_refresh_expires ON refresh_tokens (expires_at)');
            $this->addSql('ALTER TABLE refresh_tokens ADD CONSTRAINT FK_rt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->addSql('ALTER TABLE refresh_tokens ADD CONSTRAINT FK_rt_client FOREIGN KEY (client_id) REFERENCES oauth_clients (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->addSql('ALTER TABLE refresh_tokens ADD CONSTRAINT FK_rt_org FOREIGN KEY (org_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->addSql('ALTER TABLE refresh_tokens ADD CONSTRAINT FK_rt_replaced_by FOREIGN KEY (replaced_by_id) REFERENCES refresh_tokens (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

            // Revoked JTI
            $this->addSql('CREATE TABLE revoked_jti (jti VARCHAR(255) NOT NULL, reason VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(jti))');
        } else {
            $this->abortIf(true, 'Unsupported database platform for this migration: '.$platform);
        }
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();
        if ($platform === 'mysql' || $platform === 'postgresql') {
            $this->addSql('DROP TABLE IF EXISTS revoked_jti');
            $this->addSql('ALTER TABLE refresh_tokens DROP FOREIGN KEY FK_rt_replaced_by');
            $this->addSql('ALTER TABLE refresh_tokens DROP FOREIGN KEY FK_rt_org');
            $this->addSql('ALTER TABLE refresh_tokens DROP FOREIGN KEY FK_rt_client');
            $this->addSql('ALTER TABLE refresh_tokens DROP FOREIGN KEY FK_rt_user');
            $this->addSql('ALTER TABLE memberships DROP FOREIGN KEY FK_member_org');
            $this->addSql('ALTER TABLE memberships DROP FOREIGN KEY FK_member_user');
            $this->addSql('DROP TABLE IF EXISTS refresh_tokens');
            $this->addSql('DROP TABLE IF EXISTS oauth_clients');
            $this->addSql('DROP TABLE IF EXISTS memberships');
            $this->addSql('DROP TABLE IF EXISTS organizations');
            $this->addSql('DROP TABLE IF EXISTS users');
        } else {
            $this->abortIf(true, 'Unsupported database platform for this migration: '.$platform);
        }
    }
}
