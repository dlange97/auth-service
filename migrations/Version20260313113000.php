<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260313113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user status column for soft delete support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE user ADD status VARCHAR(20) NOT NULL DEFAULT 'active'");
        $this->addSql("UPDATE user SET status = 'active' WHERE status IS NULL OR status = ''");
        $this->addSql('CREATE INDEX IDX_8D93D6496BF700BD ON user (status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_8D93D6496BF700BD ON user');
        $this->addSql('ALTER TABLE user DROP status');
    }
}
