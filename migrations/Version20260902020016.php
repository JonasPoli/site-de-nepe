<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260902020016 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE biblia_book (id INT NOT NULL, testment_id INT NOT NULL, position INT NOT NULL, name VARCHAR(255) NOT NULL, abbreviation VARCHAR(255) NOT NULL, bible_com_abreviation VARCHAR(255) DEFAULT NULL, human_long VARCHAR(255) DEFAULT NULL, INDEX IDX_36785C871A816EDA (testment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE biblia_testament (id INT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE biblia_verse (id INT NOT NULL, version_id INT NOT NULL, book_id INT NOT NULL, external_id_id INT DEFAULT NULL, chapter INT NOT NULL, verse INT NOT NULL, text LONGTEXT NOT NULL, subject VARCHAR(2048) DEFAULT NULL, INDEX IDX_F0086DD94BBC2705 (version_id), INDEX IDX_F0086DD916A2B381 (book_id), INDEX IDX_F0086DD94D09EDD4 (external_id_id), INDEX verse_lookup_idx (version_id, book_id, chapter, verse), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE biblia_verse_ext (id INT NOT NULL, book_id INT NOT NULL, chapter INT NOT NULL, verse INT NOT NULL, year INT DEFAULT NULL, year_description VARCHAR(255) DEFAULT NULL, place VARCHAR(255) DEFAULT NULL, translated INT DEFAULT 0 NOT NULL, INDEX IDX_228F598C16A2B381 (book_id), INDEX verse_ext_book_ch_v_idx (book_id, chapter, verse), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE biblia_version (id INT NOT NULL, name VARCHAR(255) NOT NULL, bible_com_abreviation VARCHAR(255) DEFAULT NULL, abbreviation VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE biblia_book ADD CONSTRAINT FK_36785C871A816EDA FOREIGN KEY (testment_id) REFERENCES biblia_testament (id)');
        $this->addSql('ALTER TABLE biblia_verse ADD CONSTRAINT FK_F0086DD94BBC2705 FOREIGN KEY (version_id) REFERENCES biblia_version (id)');
        $this->addSql('ALTER TABLE biblia_verse ADD CONSTRAINT FK_F0086DD916A2B381 FOREIGN KEY (book_id) REFERENCES biblia_book (id)');
        $this->addSql('ALTER TABLE biblia_verse ADD CONSTRAINT FK_F0086DD94D09EDD4 FOREIGN KEY (external_id_id) REFERENCES biblia_verse_ext (id)');
        $this->addSql('ALTER TABLE biblia_verse_ext ADD CONSTRAINT FK_228F598C16A2B381 FOREIGN KEY (book_id) REFERENCES biblia_book (id)');
        $this->addSql('ALTER TABLE article ADD biblia_book_id INT DEFAULT NULL, ADD biblia_chapter INT DEFAULT NULL, ADD biblia_verse_start INT DEFAULT NULL, ADD biblia_verse_end INT DEFAULT NULL');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66CAEB2355 FOREIGN KEY (biblia_book_id) REFERENCES biblia_book (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_23A0E66CAEB2355 ON article (biblia_book_id)');
        $this->addSql('CREATE INDEX article_biblia_idx ON article (biblia_book_id, biblia_chapter, biblia_verse_start, biblia_verse_end)');
        $this->addSql('ALTER TABLE page ADD biblia_book_id INT DEFAULT NULL, ADD biblia_chapter INT DEFAULT NULL, ADD biblia_verse_start INT DEFAULT NULL, ADD biblia_verse_end INT DEFAULT NULL');
        $this->addSql('ALTER TABLE page ADD CONSTRAINT FK_140AB620CAEB2355 FOREIGN KEY (biblia_book_id) REFERENCES biblia_book (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_140AB620CAEB2355 ON page (biblia_book_id)');
        $this->addSql('CREATE INDEX page_biblia_idx ON page (biblia_book_id, biblia_chapter, biblia_verse_start, biblia_verse_end)');
        $this->addSql('ALTER TABLE study ADD biblia_book_id INT DEFAULT NULL, ADD biblia_chapter INT DEFAULT NULL, ADD biblia_verse_start INT DEFAULT NULL, ADD biblia_verse_end INT DEFAULT NULL');
        $this->addSql('ALTER TABLE study ADD CONSTRAINT FK_E67F9749CAEB2355 FOREIGN KEY (biblia_book_id) REFERENCES biblia_book (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_E67F9749CAEB2355 ON study (biblia_book_id)');
        $this->addSql('CREATE INDEX study_biblia_idx ON study (biblia_book_id, biblia_chapter, biblia_verse_start, biblia_verse_end)');
        $this->addSql('ALTER TABLE video_support ADD biblia_book_id INT DEFAULT NULL, ADD biblia_chapter INT DEFAULT NULL, ADD biblia_verse_start INT DEFAULT NULL, ADD biblia_verse_end INT DEFAULT NULL');
        $this->addSql('ALTER TABLE video_support ADD CONSTRAINT FK_65E90208CAEB2355 FOREIGN KEY (biblia_book_id) REFERENCES biblia_book (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_65E90208CAEB2355 ON video_support (biblia_book_id)');
        $this->addSql('CREATE INDEX video_biblia_idx ON video_support (biblia_book_id, biblia_chapter, biblia_verse_start, biblia_verse_end)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E66CAEB2355');
        $this->addSql('ALTER TABLE page DROP FOREIGN KEY FK_140AB620CAEB2355');
        $this->addSql('ALTER TABLE study DROP FOREIGN KEY FK_E67F9749CAEB2355');
        $this->addSql('ALTER TABLE video_support DROP FOREIGN KEY FK_65E90208CAEB2355');
        $this->addSql('ALTER TABLE biblia_book DROP FOREIGN KEY FK_36785C871A816EDA');
        $this->addSql('ALTER TABLE biblia_verse DROP FOREIGN KEY FK_F0086DD94BBC2705');
        $this->addSql('ALTER TABLE biblia_verse DROP FOREIGN KEY FK_F0086DD916A2B381');
        $this->addSql('ALTER TABLE biblia_verse DROP FOREIGN KEY FK_F0086DD94D09EDD4');
        $this->addSql('ALTER TABLE biblia_verse_ext DROP FOREIGN KEY FK_228F598C16A2B381');
        $this->addSql('DROP TABLE biblia_book');
        $this->addSql('DROP TABLE biblia_testament');
        $this->addSql('DROP TABLE biblia_verse');
        $this->addSql('DROP TABLE biblia_verse_ext');
        $this->addSql('DROP TABLE biblia_version');
        $this->addSql('DROP INDEX IDX_23A0E66CAEB2355 ON article');
        $this->addSql('DROP INDEX article_biblia_idx ON article');
        $this->addSql('ALTER TABLE article DROP biblia_book_id, DROP biblia_chapter, DROP biblia_verse_start, DROP biblia_verse_end');
        $this->addSql('DROP INDEX IDX_140AB620CAEB2355 ON page');
        $this->addSql('DROP INDEX page_biblia_idx ON page');
        $this->addSql('ALTER TABLE page DROP biblia_book_id, DROP biblia_chapter, DROP biblia_verse_start, DROP biblia_verse_end');
        $this->addSql('DROP INDEX IDX_E67F9749CAEB2355 ON study');
        $this->addSql('DROP INDEX study_biblia_idx ON study');
        $this->addSql('ALTER TABLE study DROP biblia_book_id, DROP biblia_chapter, DROP biblia_verse_start, DROP biblia_verse_end');
        $this->addSql('DROP INDEX IDX_65E90208CAEB2355 ON video_support');
        $this->addSql('DROP INDEX video_biblia_idx ON video_support');
        $this->addSql('ALTER TABLE video_support DROP biblia_book_id, DROP biblia_chapter, DROP biblia_verse_start, DROP biblia_verse_end');
    }
}
