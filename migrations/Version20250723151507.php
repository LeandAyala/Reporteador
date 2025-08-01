<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250723151507 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Se crean los campos: precio, almacen, grupo_contable y códgio en la tabla productos.producto';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE productos.producto ADD precio DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE productos.producto ADD almacen TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE productos.producto ADD grupo_contable TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE productos.producto ADD codigo TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE productos.producto DROP precio');
        $this->addSql('ALTER TABLE productos.producto DROP almacen');
        $this->addSql('ALTER TABLE productos.producto DROP grupo_contable');
        $this->addSql('ALTER TABLE productos.producto DROP codigo');
    }
}
