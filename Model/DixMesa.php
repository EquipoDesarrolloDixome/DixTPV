<?php

namespace FacturaScripts\Plugins\DixTPV\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Dinamic\Model\DixTipoMesa;

class DixMesa extends ModelClass {

    use ModelTrait;

    // Propiedades que mapean a la estructura XML
    public $idmesa;
    public $idsalon;
    public $nombre;
    public $comensales;
    public $observaciones;
    public $tipo_mesa;
    public $idcomanda;
    public $estado;
    public $posx;
    public $posy;

    public function clear(): void {
        parent::clear();
    }

    public static function primaryColumn(): string {
        return 'idmesa';
    }

    public static function tableName(): string {
        return 'dix_mesa';
    }

    public function test(): bool {
        return parent::test();
    }

    public function install(): string {
        return parent::install();
    }
}
