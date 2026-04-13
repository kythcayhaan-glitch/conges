<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add RTT: rtt_balance_value to users, type to leave_requests';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN rtt_balance_value FLOAT NOT NULL DEFAULT 0');
        $this->addSql("ALTER TABLE leave_requests ADD COLUMN type VARCHAR(10) NOT NULL DEFAULT 'CONGE'");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
