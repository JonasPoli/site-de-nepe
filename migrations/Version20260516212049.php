<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260516212049 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category ADD parent_id INT DEFAULT NULL, ADD pre_title VARCHAR(255) DEFAULT NULL, ADD description LONGTEXT DEFAULT NULL, ADD show_in_header TINYINT(1) DEFAULT 0 NOT NULL, ADD show_in_footer TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1727ACA70 FOREIGN KEY (parent_id) REFERENCES category (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_64C19C1727ACA70 ON category (parent_id)');
        $this->addSql('ALTER TABLE page_section ADD category_id INT DEFAULT NULL, CHANGE page_id page_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE page_section ADD CONSTRAINT FK_D713917A12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_D713917A12469DE2 ON page_section (category_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1727ACA70');
        $this->addSql('DROP INDEX IDX_64C19C1727ACA70 ON category');
        $this->addSql('ALTER TABLE category DROP parent_id, DROP pre_title, DROP description, DROP show_in_header, DROP show_in_footer');
        $this->addSql('ALTER TABLE page_section DROP FOREIGN KEY FK_D713917A12469DE2');
        $this->addSql('DROP INDEX IDX_D713917A12469DE2 ON page_section');
        $this->addSql('ALTER TABLE page_section DROP category_id, CHANGE page_id page_id INT NOT NULL');
    }
}
