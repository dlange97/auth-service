<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260317000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add language column to user table (default: en)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE user ADD language VARCHAR(5) NOT NULL DEFAULT 'en'");
        $this->addSql("UPDATE user SET language = 'en' WHERE language IS NULL OR language = ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP language');
    }
}
