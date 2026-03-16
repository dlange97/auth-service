<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260306230118 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user CHANGE id id VARCHAR(36) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE created_by created_by INT DEFAULT NULL, CHANGE updated_by updated_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user RENAME INDEX uniq_user_email TO UNIQ_8D93D649E7927C74');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `user` CHANGE id id CHAR(36) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_by created_by CHAR(36) DEFAULT NULL, CHANGE updated_by updated_by CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` RENAME INDEX uniq_8d93d649e7927c74 TO UNIQ_USER_EMAIL');
    }
}
