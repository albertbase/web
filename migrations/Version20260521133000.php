<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Demote misclassified customer widaxet507@bittnex.com from staff to customer role';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'UPDATE `user` SET roles = \'["ROLE_USER"]\' WHERE LOWER(username) = \'widaxet507@bittnex.com\' AND roles LIKE \'%"ROLE_STAFF"%\' AND roles NOT LIKE \'%"ROLE_ADMIN"%\''
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'UPDATE `user` SET roles = \'["ROLE_STAFF","ROLE_USER"]\' WHERE LOWER(username) = \'widaxet507@bittnex.com\' AND roles = \'["ROLE_USER"]\''
        );
    }
}
