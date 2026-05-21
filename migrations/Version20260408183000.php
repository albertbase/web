<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename verification token index to match Doctrine metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user RENAME INDEX uniq_8d93d64968d5ff35 TO UNIQ_8D93D649C4995C67');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user RENAME INDEX UNIQ_8D93D649C4995C67 TO uniq_8d93d64968d5ff35');
    }
}
