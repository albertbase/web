<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521073600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email_verification_requested_at to user table for verification token expiration control.';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['user'])) {
            return;
        }

        $columns = $schemaManager->listTableColumns('user');
        if (!array_key_exists('email_verification_requested_at', $columns)) {
            $this->addSql('ALTER TABLE user ADD email_verification_requested_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['user'])) {
            return;
        }

        $columns = $schemaManager->listTableColumns('user');
        if (array_key_exists('email_verification_requested_at', $columns)) {
            $this->addSql('ALTER TABLE user DROP email_verification_requested_at');
        }
    }
}
