<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260513031410 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE inscricao DROP FOREIGN KEY FK_AE0E7EEA87A5F842');
        $this->addSql('ALTER TABLE inscrito DROP FOREIGN KEY FK_324579302763B797');
        $this->addSql('ALTER TABLE inscrito DROP FOREIGN KEY FK_324579309430E997');
        $this->addSql('ALTER TABLE inscrito_item_adicional DROP FOREIGN KEY FK_E4BF1494636F8F7A');
        $this->addSql('ALTER TABLE inscrito_item_adicional DROP FOREIGN KEY FK_E4BF1494BA615732');
        $this->addSql('ALTER TABLE item_adicional DROP FOREIGN KEY FK_E8E82EEC2763B797');
        $this->addSql('ALTER TABLE super_test_fields DROP FOREIGN KEY FK_43206855B736F1D0');
        $this->addSql('ALTER TABLE tipo_inscricao DROP FOREIGN KEY FK_4C60C1A487A5F842');
        $this->addSql('DROP TABLE evento');
        $this->addSql('DROP TABLE image');
        $this->addSql('DROP TABLE inscricao');
        $this->addSql('DROP TABLE inscrito');
        $this->addSql('DROP TABLE inscrito_item_adicional');
        $this->addSql('DROP TABLE item_adicional');
        $this->addSql('DROP TABLE super_test_fields');
        $this->addSql('DROP TABLE test_database');
        $this->addSql('DROP TABLE tipo_inscricao');
        $this->addSql('ALTER TABLE tenant ADD facebook_link VARCHAR(255) DEFAULT NULL, ADD whatsapp_link VARCHAR(255) DEFAULT NULL, ADD linkedin_link VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE evento (id INT AUTO_INCREMENT NOT NULL, nome VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, descricao LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, chave_pix VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, beneficiario_pix VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, cidade_pix VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, mensagem_sucesso LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, banner_name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, logo_name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, data_inicio DATE DEFAULT NULL, data_fim DATE DEFAULT NULL, status VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT \'ativo\' NOT NULL COLLATE `utf8mb4_unicode_ci`, token VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, cor_background VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT \'#ffffff\' COLLATE `utf8mb4_unicode_ci`, cor_texto VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT \'#000000\' COLLATE `utf8mb4_unicode_ci`, cor_texto_secundario VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT \'#333333\' COLLATE `utf8mb4_unicode_ci`, cor_botao_primario VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT \'#007bff\' COLLATE `utf8mb4_unicode_ci`, cor_botao_secundario VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT \'#6c757d\' COLLATE `utf8mb4_unicode_ci`, updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_47860B055F37A13B (token), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE image (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, image_name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE inscricao (id INT AUTO_INCREMENT NOT NULL, evento_id INT NOT NULL, nome_cadastrante VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_AE0E7EEA87A5F842 (evento_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE inscrito (id INT AUTO_INCREMENT NOT NULL, inscricao_id INT NOT NULL, tipo_inscricao_id INT NOT NULL, nome_completo VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, nome_cracha VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, data_nascimento DATE DEFAULT NULL, email VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, cpf VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, whatsapp VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, nome_contato_emergencia VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, telefone_contato_emergencia VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, cidade VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, estado VARCHAR(2) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, restricao_alimentar LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, alergia LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, aceite_lgpd TINYINT(1) DEFAULT 0 NOT NULL, aceite_imagem TINYINT(1) DEFAULT 0 NOT NULL, status VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT \'ativo\' NOT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_324579302763B797 (tipo_inscricao_id), INDEX IDX_324579309430E997 (inscricao_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE inscrito_item_adicional (inscrito_id INT NOT NULL, item_adicional_id INT NOT NULL, INDEX IDX_E4BF1494636F8F7A (item_adicional_id), INDEX IDX_E4BF1494BA615732 (inscrito_id), PRIMARY KEY(inscrito_id, item_adicional_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE item_adicional (id INT AUTO_INCREMENT NOT NULL, tipo_inscricao_id INT NOT NULL, descricao VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, valor NUMERIC(10, 2) NOT NULL, status VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT \'ativo\' NOT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_E8E82EEC2763B797 (tipo_inscricao_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE super_test_fields (id INT AUTO_INCREMENT NOT NULL, choice_type_from_entity_id INT DEFAULT NULL, simple_input_text VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, edit_text_with_editor LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, date_field DATE NOT NULL, date_and_time_field DATETIME NOT NULL, choice_type_from_list VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, sin_nao_int INT NOT NULL, boolean_true_false TINYINT(1) NOT NULL, image VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, img_updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', select_enum VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, email_field VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, numero_simples INT NOT NULL, INDEX IDX_43206855B736F1D0 (choice_type_from_entity_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE test_database (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE tipo_inscricao (id INT AUTO_INCREMENT NOT NULL, evento_id INT NOT NULL, nome VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, valor_base NUMERIC(10, 2) NOT NULL, status VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT \'ativo\' NOT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_4C60C1A487A5F842 (evento_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE inscricao ADD CONSTRAINT FK_AE0E7EEA87A5F842 FOREIGN KEY (evento_id) REFERENCES evento (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE inscrito ADD CONSTRAINT FK_324579302763B797 FOREIGN KEY (tipo_inscricao_id) REFERENCES tipo_inscricao (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE inscrito ADD CONSTRAINT FK_324579309430E997 FOREIGN KEY (inscricao_id) REFERENCES inscricao (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE inscrito_item_adicional ADD CONSTRAINT FK_E4BF1494636F8F7A FOREIGN KEY (item_adicional_id) REFERENCES item_adicional (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inscrito_item_adicional ADD CONSTRAINT FK_E4BF1494BA615732 FOREIGN KEY (inscrito_id) REFERENCES inscrito (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE item_adicional ADD CONSTRAINT FK_E8E82EEC2763B797 FOREIGN KEY (tipo_inscricao_id) REFERENCES tipo_inscricao (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE super_test_fields ADD CONSTRAINT FK_43206855B736F1D0 FOREIGN KEY (choice_type_from_entity_id) REFERENCES test_database (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE tipo_inscricao ADD CONSTRAINT FK_4C60C1A487A5F842 FOREIGN KEY (evento_id) REFERENCES evento (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE tenant DROP facebook_link, DROP whatsapp_link, DROP linkedin_link');
    }
}
