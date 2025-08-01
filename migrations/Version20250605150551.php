<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250605150551 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Se crean las tablas facturas.factura y usuarios.usuario';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA facturas');
        $this->addSql('CREATE SCHEMA usuarios');
        $this->addSql('CREATE SEQUENCE facturas.factura_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE usuarios.usuario_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE facturas.factura (id INT NOT NULL, usuario_id INT DEFAULT NULL, usu_crea_id INT DEFAULT NULL, numero INT DEFAULT NULL, fecha DATE DEFAULT NULL, valor DOUBLE PRECISION DEFAULT NULL, fecha_crea TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_92880F46DB38439E ON facturas.factura (usuario_id)');
        $this->addSql('CREATE INDEX IDX_92880F46784298FD ON facturas.factura (usu_crea_id)');
        $this->addSql('CREATE TABLE usuarios.usuario (id INT NOT NULL, nombre TEXT DEFAULT NULL, direccion TEXT DEFAULT NULL, telefono TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE facturas.factura ADD CONSTRAINT FK_92880F46DB38439E FOREIGN KEY (usuario_id) REFERENCES usuarios.usuario (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE facturas.factura ADD CONSTRAINT FK_92880F46784298FD FOREIGN KEY (usu_crea_id) REFERENCES usuarios.usuario (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE facturas.factura_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE usuarios.usuario_id_seq CASCADE');
        $this->addSql('ALTER TABLE facturas.factura DROP CONSTRAINT FK_92880F46DB38439E');
        $this->addSql('ALTER TABLE facturas.factura DROP CONSTRAINT FK_92880F46784298FD');
        $this->addSql('DROP TABLE facturas.factura');
        $this->addSql('DROP TABLE usuarios.usuario');
    }
}
