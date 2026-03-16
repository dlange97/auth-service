<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates role_definition table and seeds the four built-in system roles.
 */
final class Version20260307000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add role_definition table with default system roles';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            CREATE TABLE role_definition (
                id          INT AUTO_INCREMENT NOT NULL,
                name        VARCHAR(100) NOT NULL,
                slug        VARCHAR(100) NOT NULL,
                permissions JSON         NOT NULL,
                is_system   TINYINT(1)   NOT NULL DEFAULT 0,
                UNIQUE INDEX UNIQ_ROLE_SLUG (slug),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        // Seed the four built-in system roles
        $this->addSql(<<<SQL
            INSERT INTO role_definition (name, slug, permissions, is_system) VALUES
            ('Administrator', 'ROLE_ADMIN',   '["dashboard.view","todos.view","todos.manage","shopping.view","shopping.manage","events.view","events.manage","map.view","routes.manage","users.view","users.create","users.assign_roles","settings.view"]', 1),
            ('Manager',       'ROLE_MANAGER', '["dashboard.view","todos.view","todos.manage","shopping.view","shopping.manage","events.view","events.manage","map.view","routes.manage","users.view","users.create","settings.view"]', 1),
            ('Editor',        'ROLE_EDITOR',  '["dashboard.view","todos.view","todos.manage","shopping.view","shopping.manage","events.view","events.manage","map.view","routes.manage"]', 1),
            ('User',          'ROLE_USER',    '["dashboard.view","todos.view","todos.manage","shopping.view","shopping.manage","events.view","events.manage","map.view","routes.manage"]', 1)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE role_definition');
    }
}
