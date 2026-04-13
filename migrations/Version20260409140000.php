<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add journee_entiere column to leave_requests';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE leave_requests ADD COLUMN journee_entiere BOOLEAN NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
