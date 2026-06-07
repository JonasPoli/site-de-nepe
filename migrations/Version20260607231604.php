<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260607231604 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add multi-type block support: type, config, embedUrl, itemCount, relatedCategory columns + 4 auxiliary tables (gallery, testimonials, partner_logos, team_members). Existing blocks default to text_image type.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE page_block_image (id INT AUTO_INCREMENT NOT NULL, block_id INT NOT NULL, filename VARCHAR(255) DEFAULT NULL, caption VARCHAR(255) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_15909CCBE9ED820C (block_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE page_block_partner_logo (id INT AUTO_INCREMENT NOT NULL, block_id INT NOT NULL, name VARCHAR(255) DEFAULT NULL, logo_filename VARCHAR(255) DEFAULT NULL, url VARCHAR(500) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_F0EB8DB4E9ED820C (block_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE page_block_team_member (id INT AUTO_INCREMENT NOT NULL, block_id INT NOT NULL, name VARCHAR(255) NOT NULL, role VARCHAR(255) DEFAULT NULL, bio LONGTEXT DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, linkedin_url VARCHAR(255) DEFAULT NULL, facebook_url VARCHAR(255) DEFAULT NULL, instagram_url VARCHAR(255) DEFAULT NULL, whatsapp_url VARCHAR(255) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_53F671CDE9ED820C (block_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE page_block_testimonial (id INT AUTO_INCREMENT NOT NULL, block_id INT NOT NULL, name VARCHAR(255) NOT NULL, role VARCHAR(255) DEFAULT NULL, text LONGTEXT NOT NULL, rating SMALLINT DEFAULT 5 NOT NULL, avatar VARCHAR(255) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_B3B4019BE9ED820C (block_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE page_block_image ADD CONSTRAINT FK_15909CCBE9ED820C FOREIGN KEY (block_id) REFERENCES page_block (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE page_block_partner_logo ADD CONSTRAINT FK_F0EB8DB4E9ED820C FOREIGN KEY (block_id) REFERENCES page_block (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE page_block_team_member ADD CONSTRAINT FK_53F671CDE9ED820C FOREIGN KEY (block_id) REFERENCES page_block (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE page_block_testimonial ADD CONSTRAINT FK_B3B4019BE9ED820C FOREIGN KEY (block_id) REFERENCES page_block (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE page_block ADD related_category_id INT DEFAULT NULL, ADD type VARCHAR(50) DEFAULT \'text_image\' NOT NULL, ADD config JSON DEFAULT NULL, ADD embed_url VARCHAR(1000) DEFAULT NULL, ADD item_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE page_block ADD CONSTRAINT FK_E59A68F4D9ADE366 FOREIGN KEY (related_category_id) REFERENCES category (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_E59A68F4D9ADE366 ON page_block (related_category_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE page_block_image DROP FOREIGN KEY FK_15909CCBE9ED820C');
        $this->addSql('ALTER TABLE page_block_partner_logo DROP FOREIGN KEY FK_F0EB8DB4E9ED820C');
        $this->addSql('ALTER TABLE page_block_team_member DROP FOREIGN KEY FK_53F671CDE9ED820C');
        $this->addSql('ALTER TABLE page_block_testimonial DROP FOREIGN KEY FK_B3B4019BE9ED820C');
        $this->addSql('DROP TABLE page_block_image');
        $this->addSql('DROP TABLE page_block_partner_logo');
        $this->addSql('DROP TABLE page_block_team_member');
        $this->addSql('DROP TABLE page_block_testimonial');
        $this->addSql('ALTER TABLE page_block DROP FOREIGN KEY FK_E59A68F4D9ADE366');
        $this->addSql('DROP INDEX IDX_E59A68F4D9ADE366 ON page_block');
        $this->addSql('ALTER TABLE page_block DROP related_category_id, DROP type, DROP config, DROP embed_url, DROP item_count');
    }
}
