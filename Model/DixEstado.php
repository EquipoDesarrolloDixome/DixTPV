<?php

namespace FacturaScripts\Plugins\DixTPV\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

class DixEstado extends ModelClass {

    use ModelTrait;

    // Propiedades que mapean a la estructura XML
    public $idestado;
    public $nombre;
    public $descripcion;

    public function clear(): void {
        parent::clear();
        $this->nombre = '';
        $this->descripcion = '';
    }

    public static function primaryColumn(): string {
        return 'idestado';
    }

    public static function tableName(): string {
        return 'dix_estado';
    }

    public function test(): bool {
        return parent::test();
    }

    public function install(): string {

        return parent::install();
    }
}
