<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251114145329 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Se crea el informe Informe vacío en el módulo de Consumo';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(
        "
            DO $$
            BEGIN
                IF 
                    NOT EXISTS(SELECT * FROM central.reportes WHERE modulo = 'Consumo' AND nombre = 'Informe vacío' AND estado = 1)
                THEN 
                    INSERT INTO central.reportes(id,tipo,modulo,nombre,sql,json,estado,migracion) 
                    VALUES (NEXTVAL('central.reportes_id_seq'), 1, 'Consumo', 'Informe vacío', '',"."'".'{"rutaFrameInforme":[],"periodo":"","anchoTabla":"","cabecera":[],"campos":[],"agrupamiento":[{"campos":[],"totalizacion":[]}],"paginacion":10,"totalizacion":[],"pdf":{"tipoHoja":"","orientacion":"","ruta":[]},"excel":{"ruta":[]},"rutaFrameResumen":[]}'."'".", 1, 'Version20251114145329');
                ELSE
                    UPDATE central.reportes SET migracion = 'Version20251114145329' WHERE id = 242;
                END IF;
            END $$
        ");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
