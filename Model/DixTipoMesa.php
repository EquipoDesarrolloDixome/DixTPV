<?php

namespace FacturaScripts\Plugins\DixTPV\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

class DixTipoMesa extends ModelClass {

    use ModelTrait;

    // Propiedades que mapean a la estructura XML
    public $idtipomesa;
    public $nombre;
    public $personasaprox;

    public function clear(): void {
        parent::clear();
    }

    public static function primaryColumn(): string {
        return 'idtipomesa';
    }

    public static function tableName(): string {
        return 'dix_tipo_mesa';
    }

    public function test(): bool {
        return parent::test();
    }

    public function install(): string {

        return parent::install();
    }
}
