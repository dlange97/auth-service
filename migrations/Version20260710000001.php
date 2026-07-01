<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260710000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_invite table for secure single-use invitation links';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE user_invite (
                id          INT AUTO_INCREMENT NOT NULL,
                reference   VARCHAR(36)  NOT NULL,
                token_hash  VARCHAR(64)  NOT NULL,
                user_id     VARCHAR(36)  NOT NULL,
                email       VARCHAR(180) NOT NULL,
                status      VARCHAR(20)  NOT NULL DEFAULT 'pending',
                expires_at  DATETIME     NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                accepted_at DATETIME     DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                created_at  DATETIME     NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY (id),
                UNIQUE INDEX UNIQ_user_invite_reference  (reference),
                UNIQUE INDEX UNIQ_user_invite_token_hash (token_hash),
                INDEX IDX_user_invite_user_id (user_id),
                INDEX IDX_user_invite_status  (status)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_invite');
    }
}
