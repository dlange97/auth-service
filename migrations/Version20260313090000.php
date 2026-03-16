<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260313090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add jwt_session_setting table with default 30-day JWT session';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            CREATE TABLE jwt_session_setting (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(80) NOT NULL,
                ttl_seconds INT NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<SQL
            INSERT INTO jwt_session_setting (name, ttl_seconds, created_at, updated_at)
            VALUES ('Default JWT Session', 2592000, NOW(), NOW())
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE jwt_session_setting');
    }
}
