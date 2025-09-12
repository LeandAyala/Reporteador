<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250905204117 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Se crea la tabla central.meses';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA central');
        $this->addSql('CREATE SEQUENCE central.meses_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE central.meses (id INT NOT NULL, nombre TEXT DEFAULT NULL, numero INT DEFAULT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE central.meses_id_seq CASCADE');
        $this->addSql('DROP TABLE central.meses');
    }
}
