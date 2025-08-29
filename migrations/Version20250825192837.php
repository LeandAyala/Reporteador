<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250825192837 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Se crean los campos almacen_id y grupo_contable_id en la tabla productos.producto. Se agrega el campo bodega_id en la tabla facturas.factura';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facturas.factura ADD bodega_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE facturas.factura ADD CONSTRAINT FK_92880F468B1FDE9D FOREIGN KEY (bodega_id) REFERENCES bodega.bodegas (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_92880F468B1FDE9D ON facturas.factura (bodega_id)');
        $this->addSql('ALTER TABLE productos.producto ADD almacen_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE productos.producto ADD grupo_contable_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE productos.producto ADD CONSTRAINT FK_CD53C2979C9C9E68 FOREIGN KEY (almacen_id) REFERENCES almacen.almacenes (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE productos.producto ADD CONSTRAINT FK_CD53C2978E205331 FOREIGN KEY (grupo_contable_id) REFERENCES grupocontable.grupocontables (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_CD53C2979C9C9E68 ON productos.producto (almacen_id)');
        $this->addSql('CREATE INDEX IDX_CD53C2978E205331 ON productos.producto (grupo_contable_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE productos.producto DROP CONSTRAINT FK_CD53C2979C9C9E68');
        $this->addSql('ALTER TABLE facturas.factura DROP CONSTRAINT FK_92880F468B1FDE9D');
        $this->addSql('ALTER TABLE productos.producto DROP CONSTRAINT FK_CD53C2978E205331');
        $this->addSql('DROP SEQUENCE almacen.almacenes_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE bodega.bodegas_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE grupocontable.grupocontables_id_seq CASCADE');
        $this->addSql('ALTER TABLE bodega.bodegas DROP CONSTRAINT FK_76303B959C9C9E68');
        $this->addSql('ALTER TABLE grupocontable.grupocontables DROP CONSTRAINT FK_959C6CE79C9C9E68');
        $this->addSql('DROP TABLE almacen.almacenes');
        $this->addSql('DROP TABLE bodega.bodegas');
        $this->addSql('DROP TABLE grupocontable.grupocontables');
        $this->addSql('DROP INDEX IDX_CD53C2979C9C9E68');
        $this->addSql('DROP INDEX IDX_CD53C2978E205331');
        $this->addSql('ALTER TABLE productos.producto ADD almacen TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE productos.producto ADD grupo_contable TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE productos.producto DROP almacen_id');
        $this->addSql('ALTER TABLE productos.producto DROP grupo_contable_id');
        $this->addSql('DROP INDEX IDX_92880F468B1FDE9D');
        $this->addSql('ALTER TABLE facturas.factura DROP bodega_id');
    }
}
