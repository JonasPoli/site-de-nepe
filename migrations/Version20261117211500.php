<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Perfil do usuário no painel: WhatsApp público e foto.
 *
 * Só acrescenta colunas nulas, sem índice novo e sem alterar coluna existente:
 * roda igual no MySQL 5.7.41 de produção. SQL conferido com doctrine:schema:update --dump-sql.
 */
final class Version20261117211500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Perfil do usuário: colunas whatsapp, photo e photo_updated_at em user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD whatsapp VARCHAR(30) DEFAULT NULL, ADD photo VARCHAR(255) DEFAULT NULL, ADD photo_updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP whatsapp, DROP photo, DROP photo_updated_at');
    }
}
