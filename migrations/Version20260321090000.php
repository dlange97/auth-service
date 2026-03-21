<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260321090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add dashboard_layout JSON column to user table for dashboard tile preferences';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD dashboard_layout JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP dashboard_layout');
    }
}
