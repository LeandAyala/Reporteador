<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250606195816 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Se crea el campo permite_activar en la tabla facturas.factura';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facturas.factura ADD permite_activar BOOLEAN DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE facturas.factura DROP permite_activar');
    }
}
