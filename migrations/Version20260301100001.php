<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260301100001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Auth service: create user table (INT autoincrement id, createdBy/updatedBy)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS `user`');

        $this->addSql(<<<'SQL'
            CREATE TABLE `user` (
                id         CHAR(36)         NOT NULL,
                email      VARCHAR(180)     NOT NULL,
                first_name VARCHAR(100)     DEFAULT NULL,
                last_name  VARCHAR(100)     DEFAULT NULL,
                roles      JSON             NOT NULL,
                password   VARCHAR(255)     DEFAULT NULL,
                created_at DATETIME         NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME         NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                created_by CHAR(36)         DEFAULT NULL,
                updated_by CHAR(36)         DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE INDEX UNIQ_USER_EMAIL (email)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS `user`');
    }
}
