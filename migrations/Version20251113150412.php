<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251113150412 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Se crea el campo migracion en la tabla central.reportes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE central.reportes ADD migracion TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE central.reportes DROP migracion');
    }
}
