<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260331100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_instance pivot table for many-to-many user ↔ instance relationship';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE user_instance (
                user_id     VARCHAR(36) NOT NULL,
                instance_id VARCHAR(36) NOT NULL,
                PRIMARY KEY (user_id, instance_id),
                INDEX IDX_user_instance_user     (user_id),
                INDEX IDX_user_instance_instance  (instance_id),
                CONSTRAINT FK_user_instance_user     FOREIGN KEY (user_id)     REFERENCES `user` (id) ON DELETE CASCADE,
                CONSTRAINT FK_user_instance_instance FOREIGN KEY (instance_id) REFERENCES instance (id)  ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL);

        // Back-fill from legacy single-column relationship
        $this->addSql(<<<'SQL'
            INSERT INTO user_instance (user_id, instance_id)
            SELECT id, instance_id FROM `user` WHERE instance_id IS NOT NULL
            ON DUPLICATE KEY UPDATE user_id = user_id
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_instance');
    }
}
