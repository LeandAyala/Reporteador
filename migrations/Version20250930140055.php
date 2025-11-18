<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250930140055 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Se crean los campos: tipo y modulo en la tabla facturas.reportes';
    }

    public function up(Schema $schema): void
    {
        /*$this->addSql('ALTER TABLE facturas.reportes ADD tipo INT DEFAULT NULL');
        $this->addSql('ALTER TABLE facturas.reportes ADD modulo TEXT DEFAULT NULL');*/
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE facturas.reportes DROP tipo');
        $this->addSql('ALTER TABLE facturas.reportes DROP modulo');
    }
}
