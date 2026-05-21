<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Google OAuth identity fields to user table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE user ADD google_id VARCHAR(180) DEFAULT NULL, ADD auth_provider VARCHAR(20) NOT NULL DEFAULT 'local'");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_GOOGLE_ID ON user (google_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_IDENTIFIER_GOOGLE_ID ON user');
        $this->addSql('ALTER TABLE user DROP google_id, DROP auth_provider');
    }
}
