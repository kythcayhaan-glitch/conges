<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409070035 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime le champ matricule de la table agents.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__agents AS SELECT id, nom, prenom, email, leave_balance_value, created_at, updated_at FROM agents');
        $this->addSql('DROP TABLE agents');
        $this->addSql('CREATE TABLE agents (id VARCHAR(36) NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, leave_balance_value DOUBLE PRECISION NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_agent_email ON agents (email)');
        $this->addSql('INSERT INTO agents (id, nom, prenom, email, leave_balance_value, created_at, updated_at) SELECT id, nom, prenom, email, leave_balance_value, created_at, updated_at FROM __temp__agents');
        $this->addSql('DROP TABLE __temp__agents');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__agents AS SELECT id, nom, prenom, email, leave_balance_value, created_at, updated_at FROM agents');
        $this->addSql('DROP TABLE agents');
        $this->addSql('CREATE TABLE agents (id VARCHAR(36) NOT NULL, matricule VARCHAR(20) NOT NULL DEFAULT \'\', nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, leave_balance_value DOUBLE PRECISION NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_agent_matricule ON agents (matricule)');
        $this->addSql('CREATE UNIQUE INDEX uniq_agent_email ON agents (email)');
        $this->addSql('INSERT INTO agents (id, matricule, nom, prenom, email, leave_balance_value, created_at, updated_at) SELECT id, \'\', nom, prenom, email, leave_balance_value, created_at, updated_at FROM __temp__agents');
        $this->addSql('DROP TABLE __temp__agents');
    }
}
