<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260407130103 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE agents (id VARCHAR(36) NOT NULL, matricule VARCHAR(20) NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, leave_balance_value DOUBLE PRECISION DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_agent_matricule ON agents (matricule)');
        $this->addSql('CREATE UNIQUE INDEX uniq_agent_email ON agents (email)');
        $this->addSql('CREATE TABLE leave_audit_logs (id VARCHAR(36) NOT NULL, leave_request_id VARCHAR(36) NOT NULL, ancien_statut VARCHAR(20) DEFAULT NULL, nouveau_statut VARCHAR(20) NOT NULL, commentaire CLOB DEFAULT NULL, auteur_id VARCHAR(36) DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE leave_requests (id VARCHAR(36) NOT NULL, agent_id VARCHAR(36) NOT NULL, heures DOUBLE PRECISION NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, statut VARCHAR(20) NOT NULL, motif CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE users (id VARCHAR(36) NOT NULL, email VARCHAR(180) NOT NULL, agent_id VARCHAR(36) DEFAULT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON users (email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE agents');
        $this->addSql('DROP TABLE leave_audit_logs');
        $this->addSql('DROP TABLE leave_requests');
        $this->addSql('DROP TABLE users');
    }
}
