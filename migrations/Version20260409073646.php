<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260409073646 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Merge Agent into User: add profile columns to users, rename agent_id→user_id in leave_requests, drop agents table';
    }

    public function up(Schema $schema): void
    {
        // 1. Add agent profile columns to users (SQLite: no function defaults in ALTER TABLE)
        $this->addSql('ALTER TABLE users ADD COLUMN nom VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD COLUMN prenom VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD COLUMN leave_balance_value DOUBLE PRECISION DEFAULT 0');
        $this->addSql('ALTER TABLE users ADD COLUMN updated_at DATETIME DEFAULT NULL');
        $this->addSql("UPDATE users SET updated_at = datetime('now') WHERE updated_at IS NULL");

        // 2. Copy data from agents to users (match by email)
        $this->addSql('UPDATE users SET nom = (SELECT a.nom FROM agents a WHERE a.email = users.email), prenom = (SELECT a.prenom FROM agents a WHERE a.email = users.email), leave_balance_value = COALESCE((SELECT a.leave_balance_value FROM agents a WHERE a.email = users.email), 0.0) WHERE EXISTS (SELECT 1 FROM agents a WHERE a.email = users.email)');

        // 3. Rename agent_id → user_id in leave_requests
        //    Also remap the stored agent UUID to the corresponding user UUID
        $this->addSql('CREATE TEMPORARY TABLE __temp__leave_requests AS SELECT id, agent_id, heures, date_debut, date_fin, statut, motif, created_at, updated_at FROM leave_requests');
        $this->addSql('DROP TABLE leave_requests');
        $this->addSql('CREATE TABLE leave_requests (id VARCHAR(36) NOT NULL, user_id VARCHAR(36) NOT NULL, heures DOUBLE PRECISION NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, statut VARCHAR(20) NOT NULL, motif CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql('INSERT INTO leave_requests (id, user_id, heures, date_debut, date_fin, statut, motif, created_at, updated_at) SELECT t.id, COALESCE((SELECT u.id FROM users u INNER JOIN agents a ON a.email = u.email WHERE a.id = t.agent_id), t.agent_id), t.heures, t.date_debut, t.date_fin, t.statut, t.motif, t.created_at, t.updated_at FROM __temp__leave_requests t');
        $this->addSql('DROP TABLE __temp__leave_requests');

        // 4. Drop agents table
        $this->addSql('DROP TABLE agents');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
