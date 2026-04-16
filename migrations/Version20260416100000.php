<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260416100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add initial balance columns to users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD COLUMN initial_leave_balance_value DOUBLE PRECISION NOT NULL DEFAULT 0.0");
        $this->addSql("ALTER TABLE users ADD COLUMN initial_rtt_balance_value DOUBLE PRECISION NOT NULL DEFAULT 0.0");
        $this->addSql("ALTER TABLE users ADD COLUMN initial_heure_sup_balance_value DOUBLE PRECISION NOT NULL DEFAULT 0.0");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users DROP COLUMN initial_leave_balance_value");
        $this->addSql("ALTER TABLE users DROP COLUMN initial_rtt_balance_value");
        $this->addSql("ALTER TABLE users DROP COLUMN initial_heure_sup_balance_value");
    }
}
