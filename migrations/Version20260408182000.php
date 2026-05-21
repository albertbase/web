<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408182000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email verification token to user.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD email_verification_token VARCHAR(64) DEFAULT NULL, ADD UNIQUE INDEX UNIQ_8D93D64968D5FF35 (email_verification_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP email_verification_token');
    }
}
