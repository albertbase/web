<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user presence_status for online/offline session tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `user` ADD presence_status VARCHAR(20) DEFAULT 'offline' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP presence_status');
    }
}
