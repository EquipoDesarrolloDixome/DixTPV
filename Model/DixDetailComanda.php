<?php

namespace FacturaScripts\Plugins\DixTPV\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

class DixDetailComanda extends ModelClass {

    use ModelTrait;

    // Propiedades que mapean a la estructura XML
    public $iddetalle;
    public $idcomanda;
    public $idproducto;
    public $cantidad;
    public $codimpuesto;
    public $precio_unitario;
    public $precio_total;
    public $descripcion;

    public function clear(): void {
        parent::clear();
        $this->cantidad = 1;
        $this->precio_unitario = 0.0;
        $this->precio_total = 0.0;
    }

    public static function primaryColumn(): string {
        return 'iddetalle';
    }

    public static function tableName(): string {
        return 'dix_details_comanda';
    }

    public function test(): bool {
        return parent::test();
    }

    public function install(): string {
        return parent::install();
    }
}
