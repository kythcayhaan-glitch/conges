<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260413110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace service_number (single) with service_numbers (JSON array)';
    }

    public function up(Schema $schema): void
    {
        // Ajouter la nouvelle colonne JSON
        $this->addSql("ALTER TABLE users ADD COLUMN service_numbers CLOB DEFAULT '[]' NOT NULL");

        // Migrer les données existantes : si service_number non nul, initialiser le tableau avec cette valeur
        $this->addSql("UPDATE users SET service_numbers = json_array(service_number) WHERE service_number IS NOT NULL AND service_number != ''");

        // Supprimer l'ancienne colonne (SQLite ne supporte pas DROP COLUMN avant 3.35 — on la garde inerte)
        // Si votre version SQLite >= 3.35 : décommentez la ligne suivante
        // $this->addSql('ALTER TABLE users DROP COLUMN service_number');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN service_numbers');
    }
}
