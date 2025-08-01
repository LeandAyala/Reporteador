<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250614153724 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Se crean las tablas factuas.cotizaciones y facturas.pedidos';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE facturas.cotizaciones_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE facturas.pedidos_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE facturas.cotizaciones (id INT NOT NULL, numero INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE facturas.pedidos (id INT NOT NULL, usuario_id INT DEFAULT NULL, cotizacion_id INT DEFAULT NULL, fecha DATE DEFAULT NULL, cantidad_productos DOUBLE PRECISION DEFAULT NULL, valor DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_C7563E5DB38439E ON facturas.pedidos (usuario_id)');
        $this->addSql('CREATE INDEX IDX_C7563E5307090AA ON facturas.pedidos (cotizacion_id)');
        $this->addSql('ALTER TABLE facturas.pedidos ADD CONSTRAINT FK_C7563E5DB38439E FOREIGN KEY (usuario_id) REFERENCES usuarios.usuario (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE facturas.pedidos ADD CONSTRAINT FK_C7563E5307090AA FOREIGN KEY (cotizacion_id) REFERENCES facturas.cotizaciones (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE facturas.cotizaciones_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE facturas.pedidos_id_seq CASCADE');
        $this->addSql('ALTER TABLE facturas.pedidos DROP CONSTRAINT FK_C7563E5DB38439E');
        $this->addSql('ALTER TABLE facturas.pedidos DROP CONSTRAINT FK_C7563E5307090AA');
        $this->addSql('DROP TABLE facturas.cotizaciones');
        $this->addSql('DROP TABLE facturas.pedidos');
    }
}
