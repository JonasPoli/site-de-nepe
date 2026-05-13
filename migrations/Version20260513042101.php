<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260513042101 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article CHANGE short_description short_description LONGTEXT DEFAULT NULL, CHANGE seo_description seo_description LONGTEXT DEFAULT NULL, CHANGE canonical_url canonical_url LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE page CHANGE seo_description seo_description LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article CHANGE short_description short_description VARCHAR(500) DEFAULT NULL, CHANGE seo_description seo_description VARCHAR(500) DEFAULT NULL, CHANGE canonical_url canonical_url VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE page CHANGE seo_description seo_description VARCHAR(500) DEFAULT NULL');
    }
}
