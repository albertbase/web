<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251209085815 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE IF NOT EXISTS activity_log (id INT AUTO_INCREMENT NOT NULL, action VARCHAR(255) NOT NULL, details LONGTEXT DEFAULT NULL, username VARCHAR(255) DEFAULT NULL, user_role VARCHAR(255) DEFAULT NULL, timestamp DATETIME NOT NULL, entity_type VARCHAR(255) DEFAULT NULL, entity_id INT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE activity_log');
        $this->addSql('ALTER TABLE user DROP name, DROP created_at, DROP is_active');
    }
}
