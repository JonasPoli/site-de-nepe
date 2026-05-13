<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260513152017 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE study (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, category_id INT DEFAULT NULL, author_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, cover_image VARCHAR(255) DEFAULT NULL, cover_image_updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', description LONGTEXT DEFAULT NULL, materials_html LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_E67F97499033212A (tenant_id), INDEX IDX_E67F974912469DE2 (category_id), INDEX IDX_E67F9749F675F31B (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE study_material (id INT AUTO_INCREMENT NOT NULL, study_id INT NOT NULL, label VARCHAR(255) NOT NULL, filename VARCHAR(255) NOT NULL, extension VARCHAR(10) DEFAULT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', position INT DEFAULT 0 NOT NULL, INDEX IDX_DF37601CE7B003E9 (study_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE study ADD CONSTRAINT FK_E67F97499033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE study ADD CONSTRAINT FK_E67F974912469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE study ADD CONSTRAINT FK_E67F9749F675F31B FOREIGN KEY (author_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE study_material ADD CONSTRAINT FK_DF37601CE7B003E9 FOREIGN KEY (study_id) REFERENCES study (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE video_support ADD author_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE video_support ADD CONSTRAINT FK_65E90208F675F31B FOREIGN KEY (author_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_65E90208F675F31B ON video_support (author_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE study DROP FOREIGN KEY FK_E67F97499033212A');
        $this->addSql('ALTER TABLE study DROP FOREIGN KEY FK_E67F974912469DE2');
        $this->addSql('ALTER TABLE study DROP FOREIGN KEY FK_E67F9749F675F31B');
        $this->addSql('ALTER TABLE study_material DROP FOREIGN KEY FK_DF37601CE7B003E9');
        $this->addSql('DROP TABLE study');
        $this->addSql('DROP TABLE study_material');
        $this->addSql('ALTER TABLE video_support DROP FOREIGN KEY FK_65E90208F675F31B');
        $this->addSql('DROP INDEX IDX_65E90208F675F31B ON video_support');
        $this->addSql('ALTER TABLE video_support DROP author_id');
    }
}
