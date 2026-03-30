<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251211104925 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_log ADD role VARCHAR(20) NOT NULL, ADD affected_data JSON DEFAULT NULL, ADD created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', DROP details, DROP user_role, DROP timestamp, DROP entity_type, DROP entity_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_log ADD details LONGTEXT DEFAULT NULL, ADD user_role VARCHAR(255) DEFAULT NULL, ADD timestamp DATETIME NOT NULL, ADD entity_type VARCHAR(255) DEFAULT NULL, ADD entity_id INT DEFAULT NULL, DROP role, DROP affected_data, DROP created_at');
    }
}
