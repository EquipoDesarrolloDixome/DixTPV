<?php

namespace FacturaScripts\Plugins\DixTPV\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Dinamic\Model\DixTerminal as DinDixTerminal;

class DixTPVComandero extends ModelClass {

    use ModelTrait;

    //Propiedades que mapean a la estructura XML
    public $idcomandero;
    public $nombre;
    public $tipocomandero;
    public $idterminal;

    public function clear(): void {
        parent::clear();
    }

    public static function primaryColumn(): string {
        return 'idcomandero';
    }

    public static function tableName(): string {
        return 'dix_comanderos';
    }

    public function test(): bool {
        return parent::test();
    }

    public function install(): string {
        return parent::install();
    }
}
