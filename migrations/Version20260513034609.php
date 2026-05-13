<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260513034609 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE video_material (id INT AUTO_INCREMENT NOT NULL, video_id INT NOT NULL, label VARCHAR(255) NOT NULL, filename VARCHAR(255) NOT NULL, extension VARCHAR(10) DEFAULT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', position INT DEFAULT 0 NOT NULL, INDEX IDX_D43C472929C1004E (video_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE video_material ADD CONSTRAINT FK_D43C472929C1004E FOREIGN KEY (video_id) REFERENCES video_support (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE video_material DROP FOREIGN KEY FK_D43C472929C1004E');
        $this->addSql('DROP TABLE video_material');
    }
}
