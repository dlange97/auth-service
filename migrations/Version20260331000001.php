<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260331000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create instance and checkout_invite tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE instance (
            id            VARCHAR(36)  NOT NULL,
            name          VARCHAR(100) NOT NULL,
            subdomain     VARCHAR(63)  NOT NULL,
            created_at    DATETIME     NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at    DATETIME     NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            created_by    INT          DEFAULT NULL,
            updated_by    INT          DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY UNIQ_instance_subdomain (subdomain)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE checkout_invite (
            id         INT          NOT NULL AUTO_INCREMENT,
            hash       VARCHAR(64)  NOT NULL,
            used_at    DATETIME     DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            created_at DATETIME     NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY (id),
            UNIQUE KEY UNIQ_checkout_invite_hash (hash)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE checkout_invite');
        $this->addSql('DROP TABLE instance');
    }
}
