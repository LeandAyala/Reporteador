<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250930200356 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Se crea la tabla central.reportes';
    }

    public function up(Schema $schema): void
    {
        /*$this->addSql('CREATE SEQUENCE central.reportes_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE central.reportes (id INT NOT NULL, tipo INT NOT NULL, modulo TEXT NOT NULL, nombre TEXT NOT NULL, sql TEXT DEFAULT NULL, json JSON DEFAULT NULL, PRIMARY KEY(id))');*/
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE central.reportes_id_seq CASCADE');
        $this->addSql('DROP TABLE central.reportes');
    }
}
