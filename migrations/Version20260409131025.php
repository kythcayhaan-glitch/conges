<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260409131025 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add matin and apres_midi columns to leave_requests';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE leave_requests ADD COLUMN matin BOOLEAN NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE leave_requests ADD COLUMN apres_midi BOOLEAN NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
