<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260330125312 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE evento (id INT AUTO_INCREMENT NOT NULL, nome VARCHAR(255) NOT NULL, descricao LONGTEXT DEFAULT NULL, chave_pix VARCHAR(255) NOT NULL, beneficiario_pix VARCHAR(255) DEFAULT NULL, cidade_pix VARCHAR(100) DEFAULT NULL, mensagem_sucesso LONGTEXT DEFAULT NULL, banner_name VARCHAR(255) DEFAULT NULL, logo_name VARCHAR(255) DEFAULT NULL, data_inicio DATE DEFAULT NULL, data_fim DATE DEFAULT NULL, status VARCHAR(20) DEFAULT \'ativo\' NOT NULL, token VARCHAR(100) NOT NULL, cor_background VARCHAR(20) DEFAULT \'#ffffff\', cor_texto VARCHAR(20) DEFAULT \'#000000\', cor_texto_secundario VARCHAR(20) DEFAULT \'#333333\', cor_botao_primario VARCHAR(20) DEFAULT \'#007bff\', cor_botao_secundario VARCHAR(20) DEFAULT \'#6c757d\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_47860B055F37A13B (token), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE inscricao (id INT AUTO_INCREMENT NOT NULL, evento_id INT NOT NULL, nome_cadastrante VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_AE0E7EEA87A5F842 (evento_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE inscrito (id INT AUTO_INCREMENT NOT NULL, inscricao_id INT NOT NULL, tipo_inscricao_id INT NOT NULL, nome_completo VARCHAR(255) NOT NULL, nome_cracha VARCHAR(255) DEFAULT NULL, data_nascimento DATE DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, cpf VARCHAR(20) DEFAULT NULL, whatsapp VARCHAR(20) DEFAULT NULL, nome_contato_emergencia VARCHAR(255) DEFAULT NULL, telefone_contato_emergencia VARCHAR(20) DEFAULT NULL, cidade VARCHAR(100) DEFAULT NULL, estado VARCHAR(2) DEFAULT NULL, restricao_alimentar LONGTEXT DEFAULT NULL, alergia LONGTEXT DEFAULT NULL, aceite_lgpd TINYINT(1) DEFAULT 0 NOT NULL, aceite_imagem TINYINT(1) DEFAULT 0 NOT NULL, status VARCHAR(20) DEFAULT \'ativo\' NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_324579309430E997 (inscricao_id), INDEX IDX_324579302763B797 (tipo_inscricao_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE inscrito_item_adicional (inscrito_id INT NOT NULL, item_adicional_id INT NOT NULL, INDEX IDX_E4BF1494BA615732 (inscrito_id), INDEX IDX_E4BF1494636F8F7A (item_adicional_id), PRIMARY KEY(inscrito_id, item_adicional_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE item_adicional (id INT AUTO_INCREMENT NOT NULL, tipo_inscricao_id INT NOT NULL, descricao VARCHAR(255) NOT NULL, valor NUMERIC(10, 2) NOT NULL, status VARCHAR(20) DEFAULT \'ativo\' NOT NULL, INDEX IDX_E8E82EEC2763B797 (tipo_inscricao_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tipo_inscricao (id INT AUTO_INCREMENT NOT NULL, evento_id INT NOT NULL, nome VARCHAR(255) NOT NULL, valor_base NUMERIC(10, 2) NOT NULL, status VARCHAR(20) DEFAULT \'ativo\' NOT NULL, INDEX IDX_4C60C1A487A5F842 (evento_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE inscricao ADD CONSTRAINT FK_AE0E7EEA87A5F842 FOREIGN KEY (evento_id) REFERENCES evento (id)');
        $this->addSql('ALTER TABLE inscrito ADD CONSTRAINT FK_324579309430E997 FOREIGN KEY (inscricao_id) REFERENCES inscricao (id)');
        $this->addSql('ALTER TABLE inscrito ADD CONSTRAINT FK_324579302763B797 FOREIGN KEY (tipo_inscricao_id) REFERENCES tipo_inscricao (id)');
        $this->addSql('ALTER TABLE inscrito_item_adicional ADD CONSTRAINT FK_E4BF1494BA615732 FOREIGN KEY (inscrito_id) REFERENCES inscrito (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inscrito_item_adicional ADD CONSTRAINT FK_E4BF1494636F8F7A FOREIGN KEY (item_adicional_id) REFERENCES item_adicional (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE item_adicional ADD CONSTRAINT FK_E8E82EEC2763B797 FOREIGN KEY (tipo_inscricao_id) REFERENCES tipo_inscricao (id)');
        $this->addSql('ALTER TABLE tipo_inscricao ADD CONSTRAINT FK_4C60C1A487A5F842 FOREIGN KEY (evento_id) REFERENCES evento (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE inscricao DROP FOREIGN KEY FK_AE0E7EEA87A5F842');
        $this->addSql('ALTER TABLE inscrito DROP FOREIGN KEY FK_324579309430E997');
        $this->addSql('ALTER TABLE inscrito DROP FOREIGN KEY FK_324579302763B797');
        $this->addSql('ALTER TABLE inscrito_item_adicional DROP FOREIGN KEY FK_E4BF1494BA615732');
        $this->addSql('ALTER TABLE inscrito_item_adicional DROP FOREIGN KEY FK_E4BF1494636F8F7A');
        $this->addSql('ALTER TABLE item_adicional DROP FOREIGN KEY FK_E8E82EEC2763B797');
        $this->addSql('ALTER TABLE tipo_inscricao DROP FOREIGN KEY FK_4C60C1A487A5F842');
        $this->addSql('DROP TABLE evento');
        $this->addSql('DROP TABLE inscricao');
        $this->addSql('DROP TABLE inscrito');
        $this->addSql('DROP TABLE inscrito_item_adicional');
        $this->addSql('DROP TABLE item_adicional');
        $this->addSql('DROP TABLE tipo_inscricao');
    }
}
