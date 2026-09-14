<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914021959 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fluxo de aprovação para estudos e vídeos: status, data de publicação e tabelas de aprovações';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE study_approval (id INT AUTO_INCREMENT NOT NULL, study_id INT NOT NULL, reviewer_id INT NOT NULL, comment LONGTEXT DEFAULT NULL, approved_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_B56980A2E7B003E9 (study_id), INDEX IDX_B56980A270574616 (reviewer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE video_support_approval (id INT AUTO_INCREMENT NOT NULL, video_id INT NOT NULL, reviewer_id INT NOT NULL, comment LONGTEXT DEFAULT NULL, approved_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_FDC080329C1004E (video_id), INDEX IDX_FDC080370574616 (reviewer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE study_approval ADD CONSTRAINT FK_B56980A2E7B003E9 FOREIGN KEY (study_id) REFERENCES study (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE study_approval ADD CONSTRAINT FK_B56980A270574616 FOREIGN KEY (reviewer_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE video_support_approval ADD CONSTRAINT FK_FDC080329C1004E FOREIGN KEY (video_id) REFERENCES video_support (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE video_support_approval ADD CONSTRAINT FK_FDC080370574616 FOREIGN KEY (reviewer_id) REFERENCES user (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE study ADD status VARCHAR(20) DEFAULT \'draft\' NOT NULL, ADD published_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE video_support ADD status VARCHAR(20) DEFAULT \'draft\' NOT NULL, ADD published_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');

        // O que já estava no ar continua publicado: estudos ativos e todos os vídeos. Estudos inativos viram rascunho.
        $this->addSql('UPDATE study SET status = IF(active = 1, \'published\', \'draft\'), published_at = IF(active = 1, created_at, NULL)');
        $this->addSql('UPDATE video_support SET status = \'published\', published_at = created_at');

        $this->addSql('ALTER TABLE study DROP active');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE study_approval DROP FOREIGN KEY FK_B56980A2E7B003E9');
        $this->addSql('ALTER TABLE study_approval DROP FOREIGN KEY FK_B56980A270574616');
        $this->addSql('ALTER TABLE video_support_approval DROP FOREIGN KEY FK_FDC080329C1004E');
        $this->addSql('ALTER TABLE video_support_approval DROP FOREIGN KEY FK_FDC080370574616');
        $this->addSql('DROP TABLE study_approval');
        $this->addSql('DROP TABLE video_support_approval');

        $this->addSql('ALTER TABLE study ADD active TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('UPDATE study SET active = IF(status = \'published\', 1, 0)');
        $this->addSql('ALTER TABLE study DROP status, DROP published_at');
        $this->addSql('ALTER TABLE video_support DROP status, DROP published_at');
    }
}
