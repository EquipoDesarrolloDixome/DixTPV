<?php

namespace FacturaScripts\Plugins\DixTPV\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Dinamic\Model\Tarifa as DinTarifa;
use FacturaScripts\Dinamic\Model\DixMesa as DinDixMesa;

class DixSalon extends ModelClass {

    use ModelTrait;

    // Propiedades que mapean a la estructura XML
    public $idsalon;
    public $nombre;
    public $observaciones;
    public $codsalon;
    public $codtarifa;
    public $comansales;

    public function clear(): void {
        parent::clear();
        $this->total = 0.0;
        $this->observaciones = '';
        $this->nombre = '';
    }

    public static function primaryColumn(): string {
        return 'idsalon';
    }

    public static function tableName(): string {
        return 'dix_salones';
    }

    public function test(): bool {
        return parent::test();
    }

    public function install(): string {
        return parent::install();
    }
}
