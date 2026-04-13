<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260409114555 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add heure_debut and heure_fin columns to leave_requests';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE leave_requests ADD COLUMN heure_debut VARCHAR(5) DEFAULT NULL');
        $this->addSql('ALTER TABLE leave_requests ADD COLUMN heure_fin VARCHAR(5) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
