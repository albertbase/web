<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260327101611 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'No-op migration kept for history; the live schema was already created by earlier migrations.';
    }

    public function up(Schema $schema): void
    {
        // Intentionally left blank.
        // This migration was generated after the schema already existed in the database,
        // so replaying its CREATE TABLE statements would fail on existing installations.
    }

    public function down(Schema $schema): void
    {
        // Intentionally left blank.
    }
}
