<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251118162516 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Se actualiza el informe Informe Cardex Consumo en el módulo de Consumo';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(
        "
            UPDATE central.reportes SET tipo = 1, modulo = 'Consumo', nombre = 'Informe Cardex Consumo', sql = 'select fecha,tipo_movimiento, comprobante,numero, tercero,detalle, 
                    case when orden = 0 then 0 else entradas end as entradas,salidas,vr_unidad,
                    sub_total,
                    case when saldo>=0 then round(saldo::decimal, 2) else 0 end as saldo,
                    case when vr_saldo>=0 then vr_saldo else 0 end as vr_saldo,
                    case when round(saldo::decimal,2)>0.004 and round(vr_saldo::decimal,2)>0.004 then round((vr_saldo/saldo)::decimal,2) else 0 end as costo_promedio,
                    centrocosto 
                    from (
                        select orden,producto_id,bodega_id,fecha,tipo_movimiento, comprobante,numero, tercero,detalle, entradas,salidas,vr_unidad,
                        sub_total,
                        sum(entradas-salidas) OVER (PARTITION BY producto_id order by orden asc) as saldo,
                        sum(sub_total_trx) OVER (PARTITION BY producto_id order by orden asc) as vr_saldo,centrocosto
                        from(
                    select 0 as orden,producto_id,bodega_id,''[desde]'' as fecha,''Saldo anterior'' as tipo_movimiento,''Saldo anterior'' as comprobante,
                                null as numero,null as tercero,''Saldo con corte a [desde]'' as detalle,sum(cantidad) as entradas,0 as salidas,
                                0 as vr_unidad,sum(sub_total) as sub_total_trx,sum(sub_total) as sub_total,''-'' as centrocosto
                            FROM consumo.saldos_ini_view
                            WHERE saldos_ini_view.fecha::date<''[desde]'' and producto_id=222 and bodega_id=[bodega]
                            group by producto_id,bodega_id
                            UNION
                            select orden,producto_id,bodega_id,fecha
                    ,tipo_movimiento, comprobante,numero, tercero,detalle, entradas,salidas,vr_unidad,
                                case when entradas>0 then sub_total
                                when salidas>0 then sub_total*-1 when tipo_movimiento = ''Salidas ajuste'' then sub_total*-1 else sub_total end as sub_total_trx,sub_total,centrocosto
                            FROM consumo.cuerpo_cardex_view
                            WHERE cuerpo_cardex_view.fecha::date >=''[desde]'' and cuerpo_cardex_view.fecha::date <=''[hasta]''
                            and bodega_id=[bodega]
                            and producto_id=222
                        ) as tb1
                    ORDER BY orden asc
                    )tbl2', json = "."'".'{"rutaFrameInforme":"","periodo":"","anchoTabla":"","cabecera":[],"campos":[{"nombre":"fecha","titulo":"Fecha","tipoDato":"fecha","alineacionCampo":"centro","alineacionTitulo":"centro","ruta":"","html":""},{"nombre":"tipo_movimiento","titulo":"Tipo movimiento","tipoDato":"texto","alineacionCampo":"centro","alineacionTitulo":"centro","ruta":"","html":""},{"nombre":"comprobante","titulo":"Comprobante","tipoDato":"texto","alineacionCampo":"centro","alineacionTitulo":"centro","ruta":"","html":""},{"nombre":"numero","titulo":"Numero","tipoDato":"texto","alineacionCampo":"centro","alineacionTitulo":"centro","ruta":"","html":""},{"nombre":"tercero","titulo":"Tercero","tipoDato":"texto","alineacionCampo":"centro","alineacionTitulo":"centro","ruta":"","html":""},{"nombre":"detalle","titulo":"Detalle","tipoDato":"texto","alineacionCampo":"centro","alineacionTitulo":"centro","ruta":"","html":""},{"nombre":"entradas","titulo":"Entradas","tipoDato":"texto","alineacionCampo":"centro","alineacionTitulo":"centro","ruta":"","html":""},{"nombre":"salidas","titulo":"Salidas","tipoDato":"texto","alineacionCampo":"centro","alineacionTitulo":"centro","ruta":"","html":""},{"nombre":"vr_unidad","titulo":"Vr unidad","tipoDato":"texto","alineacionCampo":"centro","alineacionTitulo":"centro","ruta":"","html":""},{"nombre":"sub_total","titulo":"Sub total","tipoDato":"texto","alineacionCampo":"centro","alineacionTitulo":"centro","ruta":"","html":""},{"nombre":"saldo","titulo":"Saldo","tipoDato":"texto","alineacionCampo":"centro","alineacionTitulo":"centro","ruta":"","html":""},{"nombre":"vr_saldo","titulo":"Vr saldo","tipoDato":"texto","alineacionCampo":"centro","alineacionTitulo":"centro","ruta":"","html":""},{"nombre":"costo_promedio","titulo":"Costo promedio","tipoDato":"texto","alineacionCampo":"centro","alineacionTitulo":"centro","ruta":"","html":""},{"nombre":"centrocosto","titulo":"Centrocosto","tipoDato":"texto","alineacionCampo":"centro","alineacionTitulo":"centro","ruta":"","html":""}],"agrupamiento":[{"campos":[],"totalizacion":[]}],"paginacion":"10","totalizacion":[],"pdf":{"tipoHoja":"","orientacion":"","ruta":""},"excel":"","rutaFrameResumen":""}'."'".", migracion = 'Version20251118162516' WHERE id = 276;
        ");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
