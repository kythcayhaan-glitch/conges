<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260415150310 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop orphan column service_number from users table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__users AS SELECT id, username, email, roles, password, nom, prenom, leave_balance_value, updated_at, rtt_balance_value, heure_sup_balance_value, service_numbers FROM users');
        $this->addSql('DROP TABLE users');
        $this->addSql('CREATE TABLE users (id VARCHAR(36) NOT NULL, username VARCHAR(50) NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, nom VARCHAR(100) DEFAULT NULL, prenom VARCHAR(100) DEFAULT NULL, leave_balance_value DOUBLE PRECISION DEFAULT 0 NOT NULL, updated_at DATETIME NOT NULL, rtt_balance_value DOUBLE PRECISION DEFAULT 0 NOT NULL, heure_sup_balance_value DOUBLE PRECISION DEFAULT 0 NOT NULL, service_numbers CLOB DEFAULT \'[]\' NOT NULL, PRIMARY KEY (id))');
        $this->addSql('INSERT INTO users (id, username, email, roles, password, nom, prenom, leave_balance_value, updated_at, rtt_balance_value, heure_sup_balance_value, service_numbers) SELECT id, username, email, roles, password, nom, prenom, leave_balance_value, updated_at, rtt_balance_value, heure_sup_balance_value, service_numbers FROM __temp__users');
        $this->addSql('DROP TABLE __temp__users');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_username ON users (username)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON users (email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__users AS SELECT id, username, email, nom, prenom, leave_balance_value, rtt_balance_value, heure_sup_balance_value, roles, password, service_numbers, updated_at FROM users');
        $this->addSql('DROP TABLE users');
        $this->addSql('CREATE TABLE users (id VARCHAR(36) NOT NULL, username VARCHAR(50) NOT NULL, email VARCHAR(180) NOT NULL, nom VARCHAR(100) DEFAULT NULL, prenom VARCHAR(100) DEFAULT NULL, leave_balance_value DOUBLE PRECISION DEFAULT \'0\', rtt_balance_value DOUBLE PRECISION DEFAULT \'0\' NOT NULL, heure_sup_balance_value DOUBLE PRECISION DEFAULT \'0\' NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, service_numbers CLOB DEFAULT \'[]\' NOT NULL, updated_at DATETIME DEFAULT NULL, service_number VARCHAR(2) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('INSERT INTO users (id, username, email, nom, prenom, leave_balance_value, rtt_balance_value, heure_sup_balance_value, roles, password, service_numbers, updated_at) SELECT id, username, email, nom, prenom, leave_balance_value, rtt_balance_value, heure_sup_balance_value, roles, password, service_numbers, updated_at FROM __temp__users');
        $this->addSql('DROP TABLE __temp__users');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON users (email)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_username ON users (username)');
    }
}
