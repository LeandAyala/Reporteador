<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250826203429 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'se crea el campo json en la tabla facturas.reportes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facturas.reportes ADD json JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE facturas.reportes DROP json');
    }
}
