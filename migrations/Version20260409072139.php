<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409072139 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime la colonne agent_id de la table users (lien user↔agent par email).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__users AS SELECT id, username, email, roles, password FROM users');
        $this->addSql('DROP TABLE users');
        $this->addSql('CREATE TABLE users (id VARCHAR(36) NOT NULL, username VARCHAR(50) NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON users (email)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_username ON users (username)');
        $this->addSql('INSERT INTO users (id, username, email, roles, password) SELECT id, username, email, roles, password FROM __temp__users');
        $this->addSql('DROP TABLE __temp__users');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__users AS SELECT id, username, email, roles, password FROM users');
        $this->addSql('DROP TABLE users');
        $this->addSql('CREATE TABLE users (id VARCHAR(36) NOT NULL, username VARCHAR(50) NOT NULL, email VARCHAR(180) NOT NULL, agent_id VARCHAR(36) DEFAULT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON users (email)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_username ON users (username)');
        $this->addSql('INSERT INTO users (id, username, email, agent_id, roles, password) SELECT id, username, email, NULL, roles, password FROM __temp__users');
        $this->addSql('DROP TABLE __temp__users');
    }
}
