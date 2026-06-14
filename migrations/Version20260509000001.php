<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260509000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create rate_limit_log table for DDoS / rate-limit audit trail';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE rate_limit_log (
                id           INT AUTO_INCREMENT NOT NULL,
                ip           VARCHAR(45)  NOT NULL,
                path         VARCHAR(500) NOT NULL,
                method       VARCHAR(10)  NOT NULL,
                instance_id  VARCHAR(36)  DEFAULT NULL,
                is_sensitive TINYINT(1)   NOT NULL DEFAULT 0,
                user_agent   VARCHAR(500) DEFAULT NULL,
                created_at   DATETIME     NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY (id),
                INDEX IDX_rll_ip         (ip),
                INDEX IDX_rll_created_at (created_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE rate_limit_log');
    }
}
