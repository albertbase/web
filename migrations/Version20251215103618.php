<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251215103618 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Safely add created_by_id to product and order tables';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!array_key_exists('created_by_id', $schemaManager->listTableColumns('order'))) {
            $this->addSql('ALTER TABLE `order` ADD created_by_id INT DEFAULT NULL');
        }
        if (!array_key_exists('created_by_id', $schemaManager->listTableColumns('product'))) {
            $this->addSql('ALTER TABLE product ADD created_by_id INT DEFAULT NULL');
        }

        // ✅ 3. Fill existing rows with admin user ID (assumed ID = 1)
        $this->addSql('UPDATE `order` SET created_by_id = 1 WHERE created_by_id IS NULL');
        $this->addSql('UPDATE product SET created_by_id = 1 WHERE created_by_id IS NULL');

        // ✅ 4. Make columns NOT NULL
        $this->addSql('ALTER TABLE `order` MODIFY created_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE product MODIFY created_by_id INT NOT NULL');

        // ✅ 5. Add foreign keys
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_ORDER_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_PRODUCT_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES user (id)');

        // ✅ 6. Add indexes
        $this->addSql('CREATE INDEX IDX_ORDER_CREATED_BY ON `order` (created_by_id)');
        $this->addSql('CREATE INDEX IDX_PRODUCT_CREATED_BY ON product (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_ORDER_CREATED_BY');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_PRODUCT_CREATED_BY');

        $this->addSql('DROP INDEX IDX_ORDER_CREATED_BY ON `order`');
        $this->addSql('DROP INDEX IDX_PRODUCT_CREATED_BY ON product');

        $this->addSql('ALTER TABLE `order` DROP created_by_id');
        $this->addSql('ALTER TABLE product DROP created_by_id');
    }
}
