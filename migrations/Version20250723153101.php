<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250723153101 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Se crea la tabla facturas.reportes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE facturas.reportes_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE facturas.reportes (id INT NOT NULL, nombre TEXT DEFAULT NULL, sql TEXT DEFAULT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE facturas.reportes_id_seq CASCADE');
        $this->addSql('DROP TABLE facturas.reportes');
    }
}
