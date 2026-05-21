<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260519171053 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cake_customization (id INT AUTO_INCREMENT NOT NULL, size VARCHAR(50) NOT NULL, flavor VARCHAR(50) NOT NULL, frosting VARCHAR(50) NOT NULL, theme VARCHAR(50) NOT NULL, decorations JSON DEFAULT NULL, message VARCHAR(255) DEFAULT NULL, price DOUBLE PRECISION NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F094584665A');
        $this->addSql('ALTER TABLE order_item ADD cake_customization_id INT DEFAULT NULL, CHANGE product_id product_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F096F616689 FOREIGN KEY (cake_customization_id) REFERENCES cake_customization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F094584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_52EA1F096F616689 ON order_item (cake_customization_id)');
        $this->addSql('ALTER TABLE user RENAME INDEX uniq_identifier_google_id TO UNIQ_8D93D64976F5C865');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F096F616689');
        $this->addSql('DROP TABLE cake_customization');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F094584665A');
        $this->addSql('DROP INDEX UNIQ_52EA1F096F616689 ON order_item');
        $this->addSql('ALTER TABLE order_item DROP cake_customization_id, CHANGE product_id product_id INT NOT NULL');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F094584665A FOREIGN KEY (product_id) REFERENCES product (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE user RENAME INDEX uniq_8d93d64976f5c865 TO UNIQ_IDENTIFIER_GOOGLE_ID');
    }
}
