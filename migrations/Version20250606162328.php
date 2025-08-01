<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250606162328 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Se crea el campo producto_id en facturas.factura';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facturas.factura ADD producto_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE facturas.factura ADD CONSTRAINT FK_92880F467645698E FOREIGN KEY (producto_id) REFERENCES productos.producto (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_92880F467645698E ON facturas.factura (producto_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE facturas.factura DROP CONSTRAINT FK_92880F467645698E');
        $this->addSql('DROP INDEX IDX_92880F467645698E');
        $this->addSql('ALTER TABLE facturas.factura DROP producto_id');
    }
}
