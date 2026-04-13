<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add heure_sup_balance_value to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN heure_sup_balance_value FLOAT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
