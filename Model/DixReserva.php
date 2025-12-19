<?php

namespace FacturaScripts\Plugins\DixTPV\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Dinamic\Model\DixSalon as DinDixSalon;
use FacturaScripts\Dinamic\Model\DixMesa as DinDixMesa;

class DixReserva extends ModelClass {

    use ModelTrait;

    // Propiedades que mapean a la estructura XML
    public $idreserva;
    public $nombrereserva;
    public $idmesa;
    public $idcomanda;
    public $fecha;
    public $horaini;
    public $horafin;
    public $personas;
    public $comentarios;
    public $numtarjeta;

    public function clear(): void {
        parent::clear();
    }

    public static function primaryColumn(): string {
        return 'idreserva';
    }

    public static function tableName(): string {
        return 'dix_reservas';
    }

    public function test(): bool {
        return parent::test();
    }

    public function install(): string {
        return parent::install();
    }
}
