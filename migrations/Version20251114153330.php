<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251114153330 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Se crea el informe Test 1 en el módulo de Consumo';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        /*$this->addSql(
        "
            DO $$
            BEGIN
                IF 
                    NOT EXISTS(SELECT * FROM central.reportes WHERE modulo = 'Consumo' AND nombre = 'Test 1' AND estado = 1)
                THEN 
                    INSERT INTO central.reportes(id,tipo,modulo,nombre,sql,json,estado,migracion) 
                    VALUES (NEXTVAL('central.reportes_id_seq'), 1, 'Consumo', 'Test 1', '',"."'".'{"rutaFrameInforme":"","periodo":"","anchoTabla":"","cabecera":[],"campos":[],"agrupamiento":[{"campos":[],"totalizacion":[]}],"paginacion":10,"totalizacion":[],"pdf":{"tipoHoja":"","orientacion":"","ruta":""},"excel":"","rutaFrameResumen":""}'."'".", 1, 'Version20251114153330');
                ELSE
                    UPDATE central.reportes SET migracion = 'Version20251114153330' WHERE id = 262;
                END IF;
            END $$
        ");*/
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
