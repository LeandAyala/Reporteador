<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250825191203 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Se crean las tablas almacen.almacenes, bodega.bodegas y grupocontable.gruposcontables';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA almacen');
        $this->addSql('CREATE SCHEMA bodega');
        $this->addSql('CREATE SCHEMA grupocontable');
        $this->addSql('CREATE SEQUENCE almacen.almacenes_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE bodega.bodegas_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE grupocontable.grupocontables_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE almacen.almacenes (id INT NOT NULL, nombre TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE bodega.bodegas (id INT NOT NULL, almacen_id INT DEFAULT NULL, nombre TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_76303B959C9C9E68 ON bodega.bodegas (almacen_id)');
        $this->addSql('CREATE TABLE grupocontable.grupocontables (id INT NOT NULL, almacen_id INT DEFAULT NULL, nombre TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_959C6CE79C9C9E68 ON grupocontable.grupocontables (almacen_id)');
        $this->addSql('ALTER TABLE bodega.bodegas ADD CONSTRAINT FK_76303B959C9C9E68 FOREIGN KEY (almacen_id) REFERENCES almacen.almacenes (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE grupocontable.grupocontables ADD CONSTRAINT FK_959C6CE79C9C9E68 FOREIGN KEY (almacen_id) REFERENCES almacen.almacenes (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE almacen.almacenes_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE bodega.bodegas_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE grupocontable.grupocontables_id_seq CASCADE');
        $this->addSql('ALTER TABLE bodega.bodegas DROP CONSTRAINT FK_76303B959C9C9E68');
        $this->addSql('ALTER TABLE grupocontable.grupocontables DROP CONSTRAINT FK_959C6CE79C9C9E68');
        $this->addSql('DROP TABLE almacen.almacenes');
        $this->addSql('DROP TABLE bodega.bodegas');
        $this->addSql('DROP TABLE grupocontable.grupocontables');
    }
}
