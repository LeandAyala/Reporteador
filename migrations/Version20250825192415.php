<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250825192415 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Se eliminan los campos almacen y grupo_contable de la tabla productos.producto';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE productos.producto DROP almacen');
        $this->addSql('ALTER TABLE productos.producto DROP grupo_contable');
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
        $this->addSql('ALTER TABLE productos.producto ADD almacen TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE productos.producto ADD grupo_contable TEXT DEFAULT NULL');
    }
}
