<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250606162035 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Se crea la tabla productos.producto';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA productos');
        $this->addSql('CREATE SEQUENCE productos.producto_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE productos.producto (id INT NOT NULL, nombre TEXT DEFAULT NULL, estado INT NOT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE productos.producto_id_seq CASCADE');
        $this->addSql('DROP TABLE producto');
    }
}
