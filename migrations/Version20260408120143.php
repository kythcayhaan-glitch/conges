<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260408120143 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__agents AS SELECT id, matricule, nom, prenom, email, leave_balance_value, created_at, updated_at FROM agents');
        $this->addSql('DROP TABLE agents');
        $this->addSql('CREATE TABLE agents (id VARCHAR(36) NOT NULL, matricule VARCHAR(20) NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, leave_balance_value DOUBLE PRECISION DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql('INSERT INTO agents (id, matricule, nom, prenom, email, leave_balance_value, created_at, updated_at) SELECT id, matricule, nom, prenom, email, leave_balance_value, created_at, updated_at FROM __temp__agents');
        $this->addSql('DROP TABLE __temp__agents');
        $this->addSql('CREATE UNIQUE INDEX uniq_agent_email ON agents (email)');
        $this->addSql('CREATE UNIQUE INDEX uniq_agent_matricule ON agents (matricule)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__agents AS SELECT id, matricule, nom, prenom, email, leave_balance_value, created_at, updated_at FROM agents');
        $this->addSql('DROP TABLE agents');
        $this->addSql('CREATE TABLE agents (id VARCHAR(36) NOT NULL, matricule VARCHAR(20) NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, leave_balance_value DOUBLE PRECISION DEFAULT \'0\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql('INSERT INTO agents (id, matricule, nom, prenom, email, leave_balance_value, created_at, updated_at) SELECT id, matricule, nom, prenom, email, leave_balance_value, created_at, updated_at FROM __temp__agents');
        $this->addSql('DROP TABLE __temp__agents');
        $this->addSql('CREATE UNIQUE INDEX uniq_agent_matricule ON agents (matricule)');
        $this->addSql('CREATE UNIQUE INDEX uniq_agent_email ON agents (email)');
    }
}
