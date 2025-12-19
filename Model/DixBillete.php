<?php

namespace FacturaScripts\Plugins\DixTPV\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Plugins\DixTPV\Model\DixCaja as DinDixCaja;

class DixBillete extends ModelClass {

    use ModelTrait;

    //propiedades que mapean a la estructura del XML
    public $idbillete;
    public $coddivisa;
    public $icono;
    public $valor;
    public $cantidad;
    public $pertenenciacaja;

    public function clear(): void {
        parent::clear();
    }

    public static function primaryColumn(): string {
        return 'idbillete';
    }

    public static function tableName(): string {
        return 'dix_billetes';
    }

    public function test(): bool {
        return parent::test();
    }

    public function install(): string {
        return parent::install();
    }
}
