<?php

namespace FacturaScripts\Plugins\DixTPV\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

class DixTarifa extends ModelClass {

    use ModelTrait;

    // Propiedades que mapean a la estructura XML
    public $codtarifa;
    public $nombre;
    public $horaini;
    public $horafin;
    public $fechainitarifa;
    public $fechafintarifa;
    public $incremento1;
    public $incremento2;
    public $descuento1;
    public $descuento2;

    public function clear(): void {
        parent::clear();
    }

    public static function primaryColumn(): string {
        return 'codtarifa';
    }

    public static function tableName(): string {
        return 'dix_tarifas';
    }

    public function test(): bool {
        return parent::test();
    }

    public function install(): string {

        return parent::install();
    }
}
