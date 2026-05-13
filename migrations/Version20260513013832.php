<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260513013832 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE article (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, category_id INT DEFAULT NULL, author_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, short_description VARCHAR(500) DEFAULT NULL, content LONGTEXT DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, image_alt VARCHAR(255) DEFAULT NULL, published_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', seo_title VARCHAR(255) DEFAULT NULL, seo_description VARCHAR(500) DEFAULT NULL, canonical_url VARCHAR(500) DEFAULT NULL, is_no_index TINYINT(1) DEFAULT 0 NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_23A0E669033212A (tenant_id), INDEX IDX_23A0E6612469DE2 (category_id), INDEX IDX_23A0E66F675F31B (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE article_approval (id INT AUTO_INCREMENT NOT NULL, article_id INT NOT NULL, reviewer_id INT NOT NULL, comment LONGTEXT DEFAULT NULL, approved_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_430861407294869C (article_id), INDEX IDX_4308614070574616 (reviewer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, INDEX IDX_64C19C19033212A (tenant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE contact_message (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, sender_name VARCHAR(255) NOT NULL, sender_email VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', is_read TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_2C9211FE9033212A (tenant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE hero_banner (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, title VARCHAR(255) NOT NULL, subtitle VARCHAR(500) DEFAULT NULL, cta_text VARCHAR(255) DEFAULT NULL, cta_link VARCHAR(500) DEFAULT NULL, background_image VARCHAR(255) DEFAULT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_6A02D0FB9033212A (tenant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, sender_id INT NOT NULL, recipient_id INT NOT NULL, parent_id INT DEFAULT NULL, subject VARCHAR(255) DEFAULT NULL, content LONGTEXT NOT NULL, sent_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', read_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', status VARCHAR(20) DEFAULT \'unread\' NOT NULL, context_type VARCHAR(50) DEFAULT NULL, context_id JSON DEFAULT NULL, INDEX IDX_B6BD307FF624B39D (sender_id), INDEX IDX_B6BD307FE92F8F78 (recipient_id), INDEX IDX_B6BD307F727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE newsletter_subscriber (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, subscribed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_401562C39033212A (tenant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE page (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, show_in_header TINYINT(1) DEFAULT 0 NOT NULL, show_in_footer TINYINT(1) DEFAULT 0 NOT NULL, seo_title VARCHAR(255) DEFAULT NULL, seo_description VARCHAR(500) DEFAULT NULL, INDEX IDX_140AB6209033212A (tenant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE page_block (id INT AUTO_INCREMENT NOT NULL, section_id INT NOT NULL, pre_title VARCHAR(255) DEFAULT NULL, title VARCHAR(255) DEFAULT NULL, text LONGTEXT DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_E59A68F4D823E37A (section_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE page_section (id INT AUTO_INCREMENT NOT NULL, page_id INT NOT NULL, title_part1 VARCHAR(255) DEFAULT NULL, title_part2 VARCHAR(255) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_D713917AC4663E4 (page_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE research_line (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, icon VARCHAR(100) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, INDEX IDX_84A484269033212A (tenant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tenant (id INT AUTO_INCREMENT NOT NULL, domain VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, logo VARCHAR(255) DEFAULT NULL, primary_color VARCHAR(7) DEFAULT NULL, secondary_color VARCHAR(7) DEFAULT NULL, contact_email VARCHAR(255) DEFAULT NULL, youtube_link VARCHAR(255) DEFAULT NULL, instagram_link VARCHAR(255) DEFAULT NULL, required_approvals INT DEFAULT 1 NOT NULL, theme VARCHAR(50) DEFAULT \'nepe\' NOT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_4E59C462A7A91E0B (domain), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE video_support (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, category_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, youtube_id VARCHAR(30) NOT NULL, description LONGTEXT DEFAULT NULL, materials_html LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_65E902089033212A (tenant_id), INDEX IDX_65E9020812469DE2 (category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E669033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E6612469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66F675F31B FOREIGN KEY (author_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE article_approval ADD CONSTRAINT FK_430861407294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article_approval ADD CONSTRAINT FK_4308614070574616 FOREIGN KEY (reviewer_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C19033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contact_message ADD CONSTRAINT FK_2C9211FE9033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hero_banner ADD CONSTRAINT FK_6A02D0FB9033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF624B39D FOREIGN KEY (sender_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FE92F8F78 FOREIGN KEY (recipient_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F727ACA70 FOREIGN KEY (parent_id) REFERENCES message (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE newsletter_subscriber ADD CONSTRAINT FK_401562C39033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE page ADD CONSTRAINT FK_140AB6209033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE page_block ADD CONSTRAINT FK_E59A68F4D823E37A FOREIGN KEY (section_id) REFERENCES page_section (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE page_section ADD CONSTRAINT FK_D713917AC4663E4 FOREIGN KEY (page_id) REFERENCES page (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE research_line ADD CONSTRAINT FK_84A484269033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE video_support ADD CONSTRAINT FK_65E902089033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE video_support ADD CONSTRAINT FK_65E9020812469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE user ADD tenant_id INT DEFAULT NULL, ADD name VARCHAR(255) NOT NULL, ADD email VARCHAR(255) DEFAULT NULL, ADD work_group INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D6499033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON user (email)');
        $this->addSql('CREATE INDEX IDX_8D93D6499033212A ON user (tenant_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D6499033212A');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E669033212A');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E6612469DE2');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E66F675F31B');
        $this->addSql('ALTER TABLE article_approval DROP FOREIGN KEY FK_430861407294869C');
        $this->addSql('ALTER TABLE article_approval DROP FOREIGN KEY FK_4308614070574616');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C19033212A');
        $this->addSql('ALTER TABLE contact_message DROP FOREIGN KEY FK_2C9211FE9033212A');
        $this->addSql('ALTER TABLE hero_banner DROP FOREIGN KEY FK_6A02D0FB9033212A');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FF624B39D');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FE92F8F78');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F727ACA70');
        $this->addSql('ALTER TABLE newsletter_subscriber DROP FOREIGN KEY FK_401562C39033212A');
        $this->addSql('ALTER TABLE page DROP FOREIGN KEY FK_140AB6209033212A');
        $this->addSql('ALTER TABLE page_block DROP FOREIGN KEY FK_E59A68F4D823E37A');
        $this->addSql('ALTER TABLE page_section DROP FOREIGN KEY FK_D713917AC4663E4');
        $this->addSql('ALTER TABLE research_line DROP FOREIGN KEY FK_84A484269033212A');
        $this->addSql('ALTER TABLE video_support DROP FOREIGN KEY FK_65E902089033212A');
        $this->addSql('ALTER TABLE video_support DROP FOREIGN KEY FK_65E9020812469DE2');
        $this->addSql('DROP TABLE article');
        $this->addSql('DROP TABLE article_approval');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE contact_message');
        $this->addSql('DROP TABLE hero_banner');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE newsletter_subscriber');
        $this->addSql('DROP TABLE page');
        $this->addSql('DROP TABLE page_block');
        $this->addSql('DROP TABLE page_section');
        $this->addSql('DROP TABLE research_line');
        $this->addSql('DROP TABLE tenant');
        $this->addSql('DROP TABLE video_support');
        $this->addSql('DROP INDEX UNIQ_8D93D649E7927C74 ON user');
        $this->addSql('DROP INDEX IDX_8D93D6499033212A ON user');
        $this->addSql('ALTER TABLE user DROP tenant_id, DROP name, DROP email, DROP work_group');
    }
}
