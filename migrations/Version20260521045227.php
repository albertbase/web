<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521045227 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize category/order/product/order_item constraints and align cake customization/user schema metadata.';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if ($schemaManager->tablesExist(['cake_customization'])) {
            $columns = $schemaManager->listTableColumns('cake_customization');
            if ($this->hasColumn($columns, 'frosting')) {
                $this->addSql('ALTER TABLE cake_customization DROP COLUMN frosting');
            }
            if ($this->hasColumn($columns, 'theme')) {
                $this->addSql('ALTER TABLE cake_customization DROP COLUMN theme');
            }
        }

        if ($schemaManager->tablesExist(['category'])) {
            $indexes = $schemaManager->listTableIndexes('category');

            if (!$this->hasIndex($indexes, 'UNIQ_CATEGORY_NAME')) {
                $duplicateCategoryCount = (int) $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM (SELECT LOWER(name) AS normalized_name, COUNT(*) AS c FROM category GROUP BY normalized_name HAVING c > 1) AS duplicates'
                );

                if ($duplicateCategoryCount > 0) {
                    throw new \RuntimeException(
                        'Cannot add unique category index: duplicate category names exist (case-insensitive).'
                    );
                }

                $this->addSql('CREATE UNIQUE INDEX UNIQ_CATEGORY_NAME ON category (name)');
            }

            if (!$this->hasIndex($indexes, 'IDX_CATEGORY_NAME')) {
                $this->addSql('CREATE INDEX IDX_CATEGORY_NAME ON category (name)');
            }
        }

        if ($schemaManager->tablesExist(['order'])) {
            $indexes = $schemaManager->listTableIndexes('order');

            if (!$this->hasIndex($indexes, 'IDX_ORDER_STATUS')) {
                $this->addSql('CREATE INDEX IDX_ORDER_STATUS ON `order` (status)');
            }

            if (!$this->hasIndex($indexes, 'IDX_ORDER_CREATED_AT')) {
                $this->addSql('CREATE INDEX IDX_ORDER_CREATED_AT ON `order` (created_at)');
            }
        }

        if ($schemaManager->tablesExist(['order_item'])) {
            $columns = $schemaManager->listTableColumns('order_item');
            $foreignKeys = $schemaManager->listTableForeignKeys('order_item');

            if ($this->hasColumn($columns, 'customer_order_id')) {
                if ($this->hasForeignKey($foreignKeys, 'FK_52EA1F09A15A2E17')) {
                    $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F09A15A2E17');
                }

                $this->addSql(
                    'ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F09A15A2E17 '.
                    'FOREIGN KEY (customer_order_id) REFERENCES `order` (id) ON DELETE CASCADE'
                );
            }
        }

        if ($schemaManager->tablesExist(['product'])) {
            $indexes = $schemaManager->listTableIndexes('product');
            $columns = $schemaManager->listTableColumns('product');
            $foreignKeys = $schemaManager->listTableForeignKeys('product');

            if ($this->hasColumn($columns, 'category_id')) {
                if ($this->hasForeignKey($foreignKeys, 'FK_D34A04AD12469DE2')) {
                    $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD12469DE2');
                }

                $this->addSql(
                    'ALTER TABLE product ADD CONSTRAINT FK_D34A04AD12469DE2 '.
                    'FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE SET NULL'
                );
            }

            if (!$this->hasIndex($indexes, 'IDX_PRODUCT_NAME')) {
                $this->addSql('CREATE INDEX IDX_PRODUCT_NAME ON product (name)');
            }

            if (!$this->hasIndex($indexes, 'IDX_PRODUCT_CREATED_AT')) {
                $this->addSql('CREATE INDEX IDX_PRODUCT_CREATED_AT ON product (created_at)');
            }
        }

        if ($schemaManager->tablesExist(['user'])) {
            $columns = $schemaManager->listTableColumns('user');
            if ($this->hasColumn($columns, 'created_at') && $this->hasColumn($columns, 'status')) {
                $this->addSql(
                    "ALTER TABLE user CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', ".
                    "CHANGE status status VARCHAR(20) DEFAULT 'active' NOT NULL"
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if ($schemaManager->tablesExist(['cake_customization'])) {
            $columns = $schemaManager->listTableColumns('cake_customization');

            if (!$this->hasColumn($columns, 'frosting')) {
                $this->addSql("ALTER TABLE cake_customization ADD frosting VARCHAR(50) NOT NULL DEFAULT 'classic'");
            }

            if (!$this->hasColumn($columns, 'theme')) {
                $this->addSql("ALTER TABLE cake_customization ADD theme VARCHAR(50) NOT NULL DEFAULT 'classic'");
            }
        }

        if ($schemaManager->tablesExist(['category'])) {
            $indexes = $schemaManager->listTableIndexes('category');
            if ($this->hasIndex($indexes, 'UNIQ_CATEGORY_NAME')) {
                $this->addSql('DROP INDEX UNIQ_CATEGORY_NAME ON category');
            }
            if ($this->hasIndex($indexes, 'IDX_CATEGORY_NAME')) {
                $this->addSql('DROP INDEX IDX_CATEGORY_NAME ON category');
            }
        }

        if ($schemaManager->tablesExist(['order'])) {
            $indexes = $schemaManager->listTableIndexes('order');
            if ($this->hasIndex($indexes, 'IDX_ORDER_STATUS')) {
                $this->addSql('DROP INDEX IDX_ORDER_STATUS ON `order`');
            }
            if ($this->hasIndex($indexes, 'IDX_ORDER_CREATED_AT')) {
                $this->addSql('DROP INDEX IDX_ORDER_CREATED_AT ON `order`');
            }
        }

        if ($schemaManager->tablesExist(['order_item'])) {
            $columns = $schemaManager->listTableColumns('order_item');
            $foreignKeys = $schemaManager->listTableForeignKeys('order_item');

            if ($this->hasColumn($columns, 'customer_order_id')) {
                if ($this->hasForeignKey($foreignKeys, 'FK_52EA1F09A15A2E17')) {
                    $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F09A15A2E17');
                }

                $this->addSql(
                    'ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F09A15A2E17 '.
                    'FOREIGN KEY (customer_order_id) REFERENCES `order` (id) ON UPDATE NO ACTION ON DELETE NO ACTION'
                );
            }
        }

        if ($schemaManager->tablesExist(['product'])) {
            $indexes = $schemaManager->listTableIndexes('product');
            $columns = $schemaManager->listTableColumns('product');
            $foreignKeys = $schemaManager->listTableForeignKeys('product');

            if ($this->hasColumn($columns, 'category_id')) {
                if ($this->hasForeignKey($foreignKeys, 'FK_D34A04AD12469DE2')) {
                    $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD12469DE2');
                }

                $this->addSql(
                    'ALTER TABLE product ADD CONSTRAINT FK_D34A04AD12469DE2 '.
                    'FOREIGN KEY (category_id) REFERENCES category (id) ON UPDATE NO ACTION ON DELETE NO ACTION'
                );
            }

            if ($this->hasIndex($indexes, 'IDX_PRODUCT_NAME')) {
                $this->addSql('DROP INDEX IDX_PRODUCT_NAME ON product');
            }

            if ($this->hasIndex($indexes, 'IDX_PRODUCT_CREATED_AT')) {
                $this->addSql('DROP INDEX IDX_PRODUCT_CREATED_AT ON product');
            }
        }

        if ($schemaManager->tablesExist(['user'])) {
            $columns = $schemaManager->listTableColumns('user');
            if ($this->hasColumn($columns, 'created_at') && $this->hasColumn($columns, 'status')) {
                $this->addSql(
                    'ALTER TABLE user CHANGE created_at created_at DATETIME NOT NULL, '.
                    'CHANGE status status VARCHAR(20) NOT NULL'
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $columns
     */
    private function hasColumn(array $columns, string $columnName): bool
    {
        $columnName = strtolower($columnName);
        foreach (array_keys($columns) as $column) {
            if (strtolower((string) $column) === $columnName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $indexes
     */
    private function hasIndex(array $indexes, string $indexName): bool
    {
        $indexName = strtolower($indexName);
        foreach (array_keys($indexes) as $index) {
            if (strtolower((string) $index) === $indexName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, object> $foreignKeys
     */
    private function hasForeignKey(array $foreignKeys, string $foreignKeyName): bool
    {
        $foreignKeyName = strtolower($foreignKeyName);
        foreach ($foreignKeys as $foreignKey) {
            if (strtolower((string) $foreignKey->getName()) === $foreignKeyName) {
                return true;
            }
        }

        return false;
    }
}
