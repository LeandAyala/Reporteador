<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250908210901 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Se crea la tabla central.compania';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE central.compania_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE central.compania (id INT NOT NULL, nit TEXT DEFAULT NULL, nombre TEXT DEFAULT NULL, direccion TEXT DEFAULT NULL, telefonos TEXT DEFAULT NULL, logocompania TEXT DEFAULT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE central.compania_id_seq CASCADE');
        $this->addSql('DROP TABLE central.compania');
    }
}
