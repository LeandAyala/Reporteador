<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251114190538 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Se actualiza el informe Test 4 en el módulo de Consumo';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(
        "
            UPDATE central.reportes SET tipo = 1, modulo = 'Consumo', nombre = 'Test 4', sql = '', json = "."'".'{"rutaFrameInforme":"","periodo":"","anchoTabla":"","cabecera":[],"campos":[],"agrupamiento":[{"campos":[],"totalizacion":[]}],"paginacion":"10","totalizacion":[],"pdf":{"tipoHoja":"","orientacion":"","ruta":""},"excel":"","rutaFrameResumen":""}'."'".", migracion = 'Version20251114190538' WHERE id = 266;
        ");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
